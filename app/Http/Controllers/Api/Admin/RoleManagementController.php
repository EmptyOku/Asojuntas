<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditTrailLogger;
use App\Services\ElectoralAccessGuard;
use App\Services\LegacyRbacAuditTrail;
use App\Services\RoleAdministrationGuard;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoleManagementController extends Controller
{
    /**
     * Registra en la bitácora el detalle de permisos de un rol, solo cuando de verdad
     * cambió algo (evita filas vacías cada vez que se edita un rol sin tocar permisos).
     */
    private function logPermissionAssignment(Role $role, array $permissionsBefore, array $permissionsAfter): void
    {
        $before = $permissionsBefore;
        $after = $permissionsAfter;
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        app(AuditTrailLogger::class)->recordSystemEvent('permission_assignment', [
            'role_id' => $role->id,
            'role_name' => $role->display_name,
            'permissions_before' => $permissionsBefore,
            'permissions_after' => $permissionsAfter,
        ], Role::class, $role->id);
    }

    /**
     * Registra en la bitácora la pérdida de un rol para un usuario puntual, con el mismo
     * tipo de evento ('role_assignment') que ya usa UserManagementController::syncRoles,
     * para que se vea igual en el historial sin importar desde dónde se originó el cambio.
     */
    private function logRoleAssignment($user, array $rolesBefore, array $rolesAfter): void
    {
        $before = $rolesBefore;
        $after = $rolesAfter;
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        app(AuditTrailLogger::class)->recordSystemEvent('role_assignment', [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'roles_before' => $rolesBefore,
            'roles_after' => $rolesAfter,
        ], get_class($user), $user->id);
    }

    /**
     * Permisos pedidos (IDs o nombres) más, en cascada, los que exigen según
     * PermissionCatalog (p. ej. slates.promote trae slates.review), para que un
     * rol nunca quede con una acción sin la pantalla o permiso que necesita.
     */
    private function resolvePermissions(array $rawPermissions): Collection
    {
        // IDs y nombres por separado: en Postgres comparar la columna bigint `id`
        // contra un texto ('users.view') es un error, no un simple "no coincide".
        [$ids, $names] = collect($rawPermissions)->partition(fn ($value) => is_int($value) || ctype_digit((string) $value));

        $requested = Permission::where(function ($query) use ($ids, $names) {
            $query->whereIn('id', $ids->map(fn ($id) => (int) $id)->all())
                ->orWhereIn('name', $names->all());
        })->pluck('name');

        return Permission::whereIn('name', PermissionCatalog::withDependencies($requested))->get();
    }

    public function index(Request $request): JsonResponse
    {
        $query = Role::with(['permissions:id,name,display_name,description'])
            ->withCount('users')
            ->orderBy('display_name');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $guard = app(ElectoralAccessGuard::class);

        // requires_neighborhood: el rol solo opera dentro de un barrio, así que
        // quien lo reciba necesita barrio asignado (la UI lo usa para avisar).
        $roles = $query->get()->each(function (Role $role) use ($guard): void {
            $role->setAttribute(
                'requires_neighborhood',
                $guard->permissionsRequireNeighborhood($role->permissions->pluck('name'))
            );
        });

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request) {
            $rawPermissions = $request->validated('permissions', []);

            $permissions = $this->resolvePermissions($rawPermissions);

            $guardName = $permissions->first()?->guard_name ?? 'web';

            $role = Role::create(
                $request->safe()->except('permissions') + ['guard_name' => $guardName]
            );

            if ($permissions->isNotEmpty()) {
                $role->syncPermissions($permissions);
            }
            LegacyRbacAuditTrail::syncRolePermissions($role->id, $permissions->pluck('id'), Auth::id());

            $this->logPermissionAssignment($role, [], $permissions->pluck('display_name')->values()->all());

            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $role->load('permissions:id,name,display_name,description'),
        ], 201);
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        // preserve(): si el cambio deja al sistema sin nadie con roles.manage, se revierte.
        $role = app(RoleAdministrationGuard::class)->preserve(function () use ($request, $id) {
            $role = Role::findOrFail($id);
            $permissionsBefore = $role->permissions()->pluck('display_name')->values()->all();

            $role->update($request->safe()->except('permissions'));

            $rawPermissions = $request->validated('permissions', []);

            $permissions = $this->resolvePermissions($rawPermissions);

            $role->syncPermissions($permissions);
            LegacyRbacAuditTrail::syncRolePermissions($role->id, $permissions->pluck('id'), Auth::id());

            $this->logPermissionAssignment($role, $permissionsBefore, $permissions->pluck('display_name')->values()->all());

            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $role->load('permissions:id,name,display_name,description'),
        ]);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $affectedUsersCount = 0;

        $role = app(RoleAdministrationGuard::class)->preserve(function () use ($id, &$affectedUsersCount) {
            $role = Role::findOrFail($id);
            $isActiveBefore = $role->is_active;
            $activating = ! $isActiveBefore;

            if (! $activating) {
                // Desactivar también revoca el rol a quien ya lo tenga: is_active no se
                // consulta en ningún chequeo de permisos, así que ocultarlo del catálogo
                // sin quitarlo dejaría el acceso intacto para quien ya lo tuviera.
                foreach ($role->users()->get() as $user) {
                    $rolesBefore = $user->roles()->pluck('display_name')->values()->all();
                    $user->removeRole($role);
                    $rolesAfter = $user->roles()->pluck('display_name')->values()->all();

                    LegacyRbacAuditTrail::syncUserRoles($user->id, $user->roles()->pluck('id'), Auth::id());
                    $this->logRoleAssignment($user, $rolesBefore, $rolesAfter);
                    $affectedUsersCount++;
                }
            }

            $role->update(['is_active' => $activating]);

            app(AuditTrailLogger::class)->recordSystemEvent('role_status_change', [
                'role_id' => $role->id,
                'role_name' => $role->display_name,
                'is_active_before' => $isActiveBefore,
                'is_active_after' => $activating,
                'affected_users_count' => $affectedUsersCount,
            ], Role::class, $role->id);

            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $role->fresh()->loadCount('users')->load('permissions:id,name,display_name,description'),
            'affected_users_count' => $affectedUsersCount,
        ]);
    }
}
