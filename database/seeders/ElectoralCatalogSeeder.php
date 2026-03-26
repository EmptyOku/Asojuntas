<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ElectoralCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('blocks')->upsert([
            [
                'code' => 'DIR',
                'name' => 'Directiva',
                'description' => 'Bloque directivo principal de la JAC',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'DEL',
                'name' => 'Delegados Asojuntas',
                'description' => 'Bloque de delegados de representacion',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'FIS',
                'name' => 'Fiscal',
                'description' => 'Bloque de fiscalizacion',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], ['name', 'description', 'is_active', 'updated_at']);

        $blockByCode = DB::table('blocks')->whereIn('code', ['DIR', 'DEL', 'FIS'])->pluck('id', 'code');

        DB::table('positions')->upsert([
            [
                'block_id' => $blockByCode['DIR'] ?? null,
                'code' => 'DIR_PRES',
                'name' => 'Presidente',
                'order_number' => 1,
                'description' => 'Representante principal de la JAC',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DIR'] ?? null,
                'code' => 'DIR_VICE',
                'name' => 'Vicepresidente',
                'order_number' => 2,
                'description' => 'Suplencia de presidencia',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DIR'] ?? null,
                'code' => 'DIR_TESO',
                'name' => 'Tesorero',
                'order_number' => 3,
                'description' => 'Gestion financiera',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DEL'] ?? null,
                'code' => 'DEL_1',
                'name' => 'Delegado 1',
                'order_number' => 1,
                'description' => 'Delegado principal',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['DEL'] ?? null,
                'code' => 'DEL_2',
                'name' => 'Delegado 2',
                'order_number' => 2,
                'description' => 'Delegado suplente',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'block_id' => $blockByCode['FIS'] ?? null,
                'code' => 'FIS_PRIN',
                'name' => 'Fiscal',
                'order_number' => 1,
                'description' => 'Control y vigilancia',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['block_id', 'code'], ['name', 'order_number', 'description', 'is_active', 'updated_at']);
    }
}
