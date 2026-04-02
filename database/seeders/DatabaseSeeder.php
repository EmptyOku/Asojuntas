<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DocumentTypeSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            StateSeeder::class,
            CitySeeder::class,
            CommuneSeeder::class,
            NeighborhoodSeeder::class,
            ElectoralCatalogSeeder::class,
            ElectionMvpSeeder::class,
        ]);
    }
}