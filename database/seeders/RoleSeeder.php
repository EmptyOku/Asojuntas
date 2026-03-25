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
            ['name' => 'admin_electoral', 'display_name' => 'Electoral Admin', 'description' => 'Administra elecciones, mesas, planchas y candidatos', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'reviewer', 'display_name' => 'Reviewer', 'description' => 'Revisa y aprueba actas y datos extraídos', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'digitizer', 'display_name' => 'Digitizer', 'description' => 'Carga actas y registra información', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'consultant', 'display_name' => 'Consultant', 'description' => 'Consulta información y reportes', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['name'], ['display_name', 'description', 'is_active', 'updated_at']);
    }
}
