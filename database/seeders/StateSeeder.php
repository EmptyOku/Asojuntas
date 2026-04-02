<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        State::updateOrCreate(
            ['code' => 'CUN'],
            ['name' => 'Cundinamarca']
        );
    }
}
