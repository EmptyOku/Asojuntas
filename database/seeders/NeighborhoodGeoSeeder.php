<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class NeighborhoodGeoSeeder extends Seeder
{
    public function run(): void
    {
        $pattern = database_path('data/comuna-*-barrios.geojson');
        $files = glob($pattern);

        if (empty($files)) {
            $this->command?->error("No se encontraron archivos geojson con el patrón: {$pattern}");
            return;
        }

        foreach ($files as $file) {
            $this->command?->info("Procesando archivo: " . basename($file));
            
            $content = file_get_contents($file);
            $geojson = json_decode($content, true);
            
            if (!isset($geojson['features'])) {
                $this->command?->warn("El archivo " . basename($file) . " no tiene la propiedad 'features'.");
                continue;
            }

            $order = 0;

            foreach ($geojson['features'] ?? [] as $feature) {
                if (($feature['geometry']['type'] ?? null) !== 'Point') {
                    continue;
                }

                $mapOrder = $order++;
                [$lng, $lat] = $feature['geometry']['coordinates'];
                $props = $feature['properties'] ?? [];

                $communeCode = $props['commune_code'] ?? null;
                $neighborhoodCode = $props['code'] ?? null;

                $barrio = Neighborhood::query()
                    ->when(
                        ! empty($communeCode),
                        fn ($q) => $q->whereHas('commune', fn ($c) => $c->where('code', $communeCode))
                    )
                    ->where('code', $neighborhoodCode)
                    ->first();

                if (! $barrio) {
                    $this->command?->warn("Barrio no encontrado en BD -> Comuna: {$communeCode} | Barrio Code: {$neighborhoodCode}");
                    continue;
                }

                $barrio->update([
                    'latitude' => (float) $lat,
                    'longitude' => (float) $lng,
                    'map_order' => $mapOrder,
                ]);

                $this->command?->line("Actualizado con éxito: {$barrio->name}");
            }
        }
    }
}