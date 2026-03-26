<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeographicDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('states')->upsert([
            [
                'code' => 'CUN',
                'name' => 'Cundinamarca',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], ['name', 'updated_at']);

        $stateId = DB::table('states')->where('code', 'CUN')->value('id');
        if (! $stateId) {
            return;
        }

        DB::table('cities')->upsert([
            [
                'state_id' => $stateId,
                'code' => 'GIR',
                'name' => 'Girardot',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['state_id', 'code'], ['name', 'updated_at']);

        $cityId = DB::table('cities')
            ->where('state_id', $stateId)
            ->where('code', 'GIR')
            ->value('id');

        if (! $cityId) {
            return;
        }

        DB::table('communes')->upsert([
            [
                'city_id' => $cityId,
                'code' => 'COM-01',
                'name' => 'Comuna 1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'city_id' => $cityId,
                'code' => 'COM-02',
                'name' => 'Comuna 2',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['city_id', 'code'], ['name', 'updated_at']);

        $communeByCode = DB::table('communes')
            ->where('city_id', $cityId)
            ->whereIn('code', ['COM-01', 'COM-02'])
            ->pluck('id', 'code');

        DB::table('neighborhoods')->upsert([
            [
                'commune_id' => $communeByCode['COM-01'] ?? null,
                'code' => 'STA-RITA',
                'name' => 'Santa Rita',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'commune_id' => $communeByCode['COM-01'] ?? null,
                'code' => 'LA-ESP',
                'name' => 'La Esperanza',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'commune_id' => $communeByCode['COM-02'] ?? null,
                'code' => 'SAN-JOR',
                'name' => 'San Jorge',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'commune_id' => $communeByCode['COM-02'] ?? null,
                'code' => 'EL-PAR',
                'name' => 'El Paraiso',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['commune_id', 'code'], ['name', 'updated_at']);
    }
}
