<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $state = State::where('code', 'CUN')->first();

        if (! $state) {
            return;
        }

        City::updateOrCreate(
            [
                'state_id' => $state->id,
                'code' => 'GIR',
            ],
            [
                'name' => 'Girardot',
            ]
        );
    }
}
