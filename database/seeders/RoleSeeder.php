<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('roles')->upsert([
            ['name' => 'super_admin', 'display_name' => 'Super Admin', 'description' => 'Acceso total al sistema', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'admin_electoral', 'display_name' => 'Admin Electoral', 'description' => 'Administra elecciones, mesas, planchas y candidatos', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'digitizer', 'display_name' => 'Jurado', 'description' => 'Carga actas y registra información', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'consultant', 'display_name' => 'Consultant', 'description' => 'Consulta información y reportes', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['name'], ['display_name', 'description', 'is_active', 'updated_at']);

        // Reviewer queda fuera del flujo actual.
        DB::table('roles')
            ->where('name', 'reviewer')
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);
    }
}
