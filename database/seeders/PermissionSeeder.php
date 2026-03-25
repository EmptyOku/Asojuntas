<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('permissions')->upsert([
            ['name' => 'users.view', 'display_name' => 'View users', 'description' => 'Ver usuarios', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'users.create', 'display_name' => 'Create users', 'description' => 'Crear usuarios', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'users.update', 'display_name' => 'Update users', 'description' => 'Editar usuarios', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'users.delete', 'display_name' => 'Delete users', 'description' => 'Eliminar usuarios', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['name' => 'roles.view', 'display_name' => 'View roles', 'description' => 'Ver roles', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'roles.assign', 'display_name' => 'Assign roles', 'description' => 'Asignar roles', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['name' => 'elections.view', 'display_name' => 'View elections', 'description' => 'Ver elecciones', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'elections.create', 'display_name' => 'Create elections', 'description' => 'Crear elecciones', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'elections.update', 'display_name' => 'Update elections', 'description' => 'Editar elecciones', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['name' => 'records.upload', 'display_name' => 'Upload records', 'description' => 'Subir actas', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'records.review', 'display_name' => 'Review records', 'description' => 'Revisar actas', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'records.approve', 'display_name' => 'Approve records', 'description' => 'Aprobar actas', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['name' => 'reports.view', 'display_name' => 'View reports', 'description' => 'Ver reportes', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'audit.view', 'display_name' => 'View audit log', 'description' => 'Ver auditoría', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['name'], ['display_name', 'description', 'is_active', 'updated_at']);
    }
}