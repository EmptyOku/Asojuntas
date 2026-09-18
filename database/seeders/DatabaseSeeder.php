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

        if (! $this->shouldSeedDemoData()) {
            return;
        }

        // Datos simulados: candidatos, actas, extracciones OCR y votos.
        // El orden importa: los bloques y planchas deben existir antes de
        // inscribir candidatos, y los candidatos antes de consolidar.
        $this->call([
            ElectionMvpSeeder::class,
            ElectionExtendedBlocksSeeder::class,
            CandidateSeeder::class,
            CandidateDraftSeeder::class,
            ScrutinyExtractionSeeder::class,
            // Extiende planchas, candidatos, actas y votos al resto de
            // elecciones: los seeders anteriores solo cubren una.
            DemoElectionDataSeeder::class,
            DemoConsolidationSeeder::class,
        ]);

        // Excluidos a proposito:
        // - GeographicDemoSeeder: sus 4 barrios de ejemplo son redundantes
        //   frente a los 117 reales de NeighborhoodSeeder, y su upsert falla
        //   contra los indices unicos parciales de neighborhoods.
        // - GlobalElectionsSeeder: duplica las elecciones que ya crea
        //   NeighborhoodElectionCoverageSeeder (choca con elections.code).
    }

    /**
     * Los datos de demostracion nunca deben entrar a produccion: son actas
     * y resultados electorales falsos. En cualquier otro entorno se cargan
     * salvo que se pida lo contrario con SEED_DEMO_DATA=false.
     */
    private function shouldSeedDemoData(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return filter_var(
            env('SEED_DEMO_DATA', true),
            FILTER_VALIDATE_BOOL
        );
    }
}
