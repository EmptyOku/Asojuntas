<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // La contraseña inicial no vive en el código (quedaría publicada en el
        // repositorio): se lee del .env y solo se usa al CREAR cada usuario.
        $password = (string) env('SEED_USER_PASSWORD', '');

        if (mb_strlen($password) < 8) {
            throw new \RuntimeException(
                'Define SEED_USER_PASSWORD en el .env (mínimo 8 caracteres) antes de ejecutar UserSeeder.'
            );
        }

        // Usuarios base.
        $seedUsers = [
            [
                'username' => 'superadmin',
                'email' => 'superadmin@jac.local',
                'roles' => ['super_admin'],
            ],
            [
                'username' => 'electoraladmin',
                'email' => 'electoraladmin@jac.local',
                'roles' => ['admin_electoral'],
            ],
            [
                // Rol territorial: para que pueda cargar actas hay que
                // vincularlo a una persona con barrio desde la aplicación.
                'username' => 'jurado',
                'email' => 'digitizer1@jac.local',
                'roles' => ['digitizer'],
            ],
        ];

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $userIdsByEmail = [];

        foreach ($seedUsers as $entry) {
            $existingId = DB::table('users')->where('email', $entry['email'])->value('id');

            // Si ya existe no se toca su contraseña: volver a correr los
            // seeders no debe reemplazar la que el usuario haya cambiado.
            if ($existingId) {
                $userIdsByEmail[$entry['email']] = $existingId;
                continue;
            }

            $userIdsByEmail[$entry['email']] = DB::table('users')->insertGetId([
                'person_id' => null,
                'username' => $entry['username'],
                'email' => $entry['email'],
                'password' => Hash::make($password),
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

        foreach ($seedUsers as $entry) {
            $seedUser = User::where('email', $entry['email'])->first();
            if ($seedUser) {
                $seedUser->syncRoles(Role::whereIn('name', $entry['roles'])->get());
            }
        }

        // El usuario reviewer de semilla queda deshabilitado en este flujo.
        DB::table('users')
            ->where('email', 'reviewer1@jac.local')
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);
    }
}
