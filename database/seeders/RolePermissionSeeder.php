<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = DB::table('roles')->pluck('id', 'name');
        $permissions = DB::table('permissions')->pluck('id', 'name');

        $matrix = [
            // Super admin recibe todo lo que declara el catálogo.
            'super_admin' => PermissionCatalog::names(),
            // Administra el proceso electoral completo. Queda fuera lo que es del
            // Super Admin: los catálogos base, las mesas, y eliminar cuentas,
            // elecciones, planchas o candidatos.
            'admin_electoral' => array_values(array_diff(PermissionCatalog::names(), [
                'communes.create', 'communes.update', 'communes.delete',
                'document_types.view', 'document_types.create', 'document_types.update', 'document_types.delete',
                'blocks.view', 'blocks.create', 'blocks.update', 'blocks.delete',
                'positions.view', 'positions.create', 'positions.update', 'positions.delete',
                'polling_tables.create', 'polling_tables.update', 'polling_tables.delete',
                'persons.delete', 'users.delete', 'elections.delete',
                'slates.update', 'slates.delete',
                'candidate_drafts.delete', 'candidate_drafts.extract',
                'candidates.update', 'candidates.delete',
            ])),
            // Jurado: sube el acta de su barrio.
            'digitizer' => ['elections.view', 'polling_tables.view', 'scrutiny_records.create'],
            'consultant' => ['elections.view', 'polling_tables.view'],
        ];

        $rows = [];
        foreach ($matrix as $roleName => $permissionNames) {
            $roleId = $roles[$roleName] ?? null;
            if (! $roleId) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                $permissionId = $permissions[$permissionName] ?? null;
                if (! $permissionId) {
                    continue;
                }

                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'assigned_at' => $now,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Role::find($roleId)?->syncPermissions(Permission::whereIn('name', $permissionNames)->get());
        }

        // Limpiar permisos heredados del rol reviewer (fuera del flujo actual).
        $reviewerRoleId = $roles['reviewer'] ?? null;
        if ($reviewerRoleId) {
            DB::table('role_permissions')->where('role_id', $reviewerRoleId)->delete();
        }

        if (! empty($rows)) {
            DB::table('role_permissions')->upsert(
                $rows,
                ['role_id', 'permission_id'],
                ['assigned_at', 'assigned_by', 'updated_at']
            );
        }
    }
}