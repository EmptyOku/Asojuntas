<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeders principales (entorno real limpio)
        // - Cargan catalogos, seguridad base y geografia
        // - NO crean actas, extracciones OCR, resultados ni candidatos de prueba
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
        ]);

        // Seeders de pruebas/demo (activar solo cuando se necesiten datos simulados)
        // $this->call([
        //     GlobalElectionsSeeder::class,
        //     ScrutinyExtractionSeeder::class,
        //     CandidateDraftSeeder::class,
        //     ElectionExtendedBlocksSeeder::class,
        //     ElectionMvpSeeder::class,
        //     CandidateSeeder::class,
        //     GeographicDemoSeeder::class,
        // ]);
    }
}
