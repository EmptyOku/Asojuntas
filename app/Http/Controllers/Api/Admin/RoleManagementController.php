<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RoleManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with(['permissions:id,name,display_name'])
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

            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $role->load('permissions:id,name,display_name')
        ], 201);
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = DB::transaction(function () use ($request, $id) {
            $role = Role::findOrFail($id);
            $role->update($request->safe()->except('permissions'));

            $rawPermissions = $request->validated('permissions', []);

            $permissions = Permission::where(function ($query) use ($rawPermissions) {
                $query->whereIn('id', $rawPermissions)
                      ->orWhereIn('name', $rawPermissions);
            })->get();

            $role->syncPermissions($permissions);

            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $role->load('permissions:id,name,display_name')
        ]);
    }
}