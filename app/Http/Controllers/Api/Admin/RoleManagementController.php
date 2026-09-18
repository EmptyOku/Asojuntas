<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditTrailLogger;
use App\Services\LegacyRbacAuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): JsonResponse
    {
        $query = Role::with(['permissions:id,name,display_name,description'])
            ->withCount('users')
            ->orderBy('display_name');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request) {
            $rawPermissions = $request->validated('permissions', []);

            $permissions = Permission::where(function ($query) use ($rawPermissions) {
                $query->whereIn('id', $rawPermissions)
                    ->orWhereIn('name', $rawPermissions);
            })->get();

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
        $role = DB::transaction(function () use ($request, $id) {
            $role = Role::findOrFail($id);
            $permissionsBefore = $role->permissions()->pluck('display_name')->values()->all();

            $role->update($request->safe()->except('permissions'));

            $rawPermissions = $request->validated('permissions', []);

            $permissions = Permission::where(function ($query) use ($rawPermissions) {
                $query->whereIn('id', $rawPermissions)
                    ->orWhereIn('name', $rawPermissions);
            })->get();

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

        $role = DB::transaction(function () use ($id, &$affectedUsersCount) {
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
