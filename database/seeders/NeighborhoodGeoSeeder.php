<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

/**
 * Carga la ubicacion (lat/lng) de los barrios / JAC desde los archivos
 * database/data/comuna-*-barrios.geojson (FeatureCollection de puntos con
 * properties.code y properties.commune_code).
 */
class NeighborhoodGeoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (glob(database_path('data/comuna-*-barrios.geojson')) as $file) {
            $geojson = json_decode((string) file_get_contents($file), true);

            foreach ($geojson['features'] ?? [] as $feature) {
                if (($feature['geometry']['type'] ?? null) !== 'Point') {
                    continue;
                }

                [$lng, $lat] = $feature['geometry']['coordinates'];
                $props = $feature['properties'] ?? [];

                $barrio = Neighborhood::query()
                    ->when(
                        ! empty($props['commune_code']),
                        fn ($q) => $q->whereHas('commune', fn ($c) => $c->where('code', $props['commune_code']))
                    )
                    ->where('code', $props['code'] ?? null)
                    ->first();

                if (! $barrio) {
                    $this->command?->warn("Barrio no encontrado: {$props['commune_code']}/{$props['code']}");

                    continue;
                }

                $barrio->update([
                    'latitude' => (float) $lat,
                    'longitude' => (float) $lng,
                ]);
            }
        }
    }
}
