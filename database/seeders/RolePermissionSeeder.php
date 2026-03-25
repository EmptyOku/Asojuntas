<?php

namespace Database\Seeders;

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
            'super_admin' => [
                'users.view', 'users.create', 'users.update', 'users.delete',
                'roles.view', 'roles.assign',
                'elections.view', 'elections.create', 'elections.update',
                'records.upload', 'records.review', 'records.approve',
                'reports.view', 'audit.view',
            ],
            'admin_electoral' => [
                'users.view', 'users.create', 'users.update',
                'roles.view', 'roles.assign',
                'elections.view', 'elections.create', 'elections.update',
                'records.upload', 'records.review', 'records.approve',
                'reports.view', 'audit.view',
            ],
            'reviewer' => [
                'elections.view',
                'records.review', 'records.approve',
                'reports.view', 'audit.view',
            ],
            'digitizer' => [
                'elections.view',
                'records.upload',
                'reports.view',
            ],
            'consultant' => [
                'elections.view',
                'reports.view',
            ],
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