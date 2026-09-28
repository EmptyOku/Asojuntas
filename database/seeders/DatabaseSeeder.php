<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Solo lo que la aplicación necesita para funcionar: catálogos, seguridad
     * base, geografía (con los contornos y puntos del mapa) y una elección
     * activa con su mesa por barrio. No crea personas, actas, candidatos ni
     * resultados: esos datos se registran desde la aplicación.
     */
    public function run(): void
    {
        // El orden importa: los usuarios necesitan los roles, los barrios
        // necesitan sus comunas y la cobertura electoral necesita los barrios.
        $this->call([
            DocumentTypeSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            StateSeeder::class,
            CitySeeder::class,
            CommuneSeeder::class,
            NeighborhoodSeeder::class,
            NeighborhoodGeoSeeder::class,
            NeighborhoodElectionCoverageSeeder::class,
            ElectoralCatalogSeeder::class,
        ]);
    }
}
