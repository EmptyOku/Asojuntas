<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Usuarios iniciales para pruebas de acceso por rol.
        $seedUsers = [
            [
                'username' => 'superadmin',
                'email' => 'superadmin@jac.local',
                'password' => 'Admin123*',
                'roles' => ['super_admin'],
            ],
            [
                'username' => 'electoraladmin',
                'email' => 'electoraladmin@jac.local',
                'password' => 'Admin123*',
                'roles' => ['admin_electoral'],
            ],
            [
                'username' => 'reviewer1',
                'email' => 'reviewer1@jac.local',
                'password' => 'Admin123*',
                'roles' => ['reviewer'],
            ],
            [
                'username' => 'digitizer1',
                'email' => 'digitizer1@jac.local',
                'password' => 'Admin123*',
                'roles' => ['digitizer'],
            ],
        ];

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $userIdsByEmail = [];

        foreach ($seedUsers as $entry) {
            $existingId = DB::table('users')->where('email', $entry['email'])->value('id');

            if ($existingId) {
                DB::table('users')
                    ->where('id', $existingId)
                    ->update([
                        'username' => $entry['username'],
                        'password' => Hash::make($entry['password']),
                        'is_active' => true,
                        'email_verified_at' => $now,
                        'updated_at' => $now,
                    ]);

                $userIdsByEmail[$entry['email']] = $existingId;
                continue;
            }

            $userIdsByEmail[$entry['email']] = DB::table('users')->insertGetId([
                'person_id' => null,
                'username' => $entry['username'],
                'email' => $entry['email'],
                'password' => Hash::make($entry['password']),
                'email_verified_at' => $now,
                'is_active' => true,
                'last_login_at' => null,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $assignedBy = $userIdsByEmail['superadmin@jac.local'] ?? null;
        $userRoleRows = [];

        foreach ($seedUsers as $entry) {
            $userId = $userIdsByEmail[$entry['email']] ?? null;
            if (! $userId) {
                continue;
            }

            foreach ($entry['roles'] as $roleName) {
                $roleId = $roleIds[$roleName] ?? null;
                if (! $roleId) {
                    continue;
                }

                $userRoleRows[] = [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned_at' => $now,
                    'assigned_by' => $entry['email'] === 'superadmin@jac.local' ? null : $assignedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($userRoleRows)) {
            DB::table('user_roles')->upsert(
                $userRoleRows,
                ['user_id', 'role_id'],
                ['assigned_at', 'assigned_by', 'updated_at']
            );
        }
    }
}