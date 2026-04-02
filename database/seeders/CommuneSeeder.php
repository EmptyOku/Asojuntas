<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Commune;
use Illuminate\Database\Seeder;

class CommuneSeeder extends Seeder
{
    public function run(): void
    {
        $city = City::query()
            ->where('code', 'GIR')
            ->whereHas('state', fn ($query) => $query->where('code', 'CUN'))
            ->first();

        if (! $city) {
            return;
        }

        foreach ($this->communes() as $commune) {
            Commune::updateOrCreate(
                [
                    'city_id' => $city->id,
                    'code' => $commune['code'],
                ],
                [
                    'name' => $commune['name'],
                ]
            );
        }
    }

    private function communes(): array
    {
        return [
            ['code' => 'COM-01', 'name' => 'Comuna 1'],
            ['code' => 'COM-02', 'name' => 'Comuna 2'],
            ['code' => 'COM-03', 'name' => 'Comuna 3'],
            ['code' => 'COM-04', 'name' => 'Comuna 4'],
            ['code' => 'COM-05', 'name' => 'Comuna 5'],
            ['code' => 'VRD-N', 'name' => 'Veredas del Norte'],
            ['code' => 'VRD-S', 'name' => 'Veredas del Sur'],
        ];
    }
}
