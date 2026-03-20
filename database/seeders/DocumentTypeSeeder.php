<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('document_types')->insert([
            ['code' => 'CC', 'name' => 'Citizenship Card', 'description' => 'Cédula de ciudadanía', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TI', 'name' => 'Identity Card', 'description' => 'Tarjeta de identidad', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CE', 'name' => 'Foreigner ID', 'description' => 'Cédula de extranjería', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'RC', 'name' => 'Civil Registry', 'description' => 'Registro civil', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PASSPORT', 'name' => 'Passport', 'description' => 'Pasaporte', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PPT', 'name' => 'Temporary Protection Permit', 'description' => 'Permiso por protección temporal', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PEP', 'name' => 'Special Stay Permit', 'description' => 'Permiso especial de permanencia', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}