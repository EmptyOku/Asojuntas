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

    public function index(): JsonResponse
    {
        $roles = Role::with(['permissions:id,name,display_name,description'])
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
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
}
