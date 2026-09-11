<?php

namespace App\Console\Commands;

use App\Models\Neighborhood;
use Illuminate\Console\Command;

/**
 * Importa la ubicacion (lat/lng) de los barrios / JAC desde un archivo.
 *
 * Formatos aceptados:
 *
 *  1. GeoJSON: FeatureCollection de puntos. Cada Feature:
 *     - geometry.coordinates = [longitud, latitud]
 *     - properties.code           (codigo del barrio, obligatorio)
 *     - properties.commune_code   (codigo de la comuna, opcional pero recomendado)
 *
 *  2. CSV con encabezado. Columnas: code, latitude, longitude
 *     y opcionalmente commune_code. Tambien acepta "name" en vez de "code".
 *
 * Uso:  php artisan barrios:import-geo storage/app/barrios.geojson
 */
class ImportBarriosGeo extends Command
{
    protected $signature = 'barrios:import-geo {file : Ruta al archivo .geojson o .csv} {--dry-run : Solo muestra lo que haria}';

    protected $description = 'Importa las coordenadas de los barrios / JAC desde un GeoJSON o CSV';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $rows = str_ends_with(strtolower($path), '.csv')
            ? $this->readCsv($path)
            : $this->readGeoJson($path);

        if ($rows === []) {
            $this->warn('El archivo no contiene puntos utilizables.');

            return self::SUCCESS;
        }

        $updated = 0;
        $missing = [];

        foreach ($rows as $row) {
            $query = Neighborhood::query();

            if (! empty($row['commune_code'])) {
                $query->whereHas('commune', fn ($q) => $q->where('code', $row['commune_code']));
            }

            if (! empty($row['code'])) {
                $query->where('code', $row['code']);
            } elseif (! empty($row['name'])) {
                $query->where('name', $row['name']);
            }

            $matches = $query->get();

            if ($matches->count() !== 1) {
                $missing[] = ($row['commune_code'] ?? '?').'/'.($row['code'] ?? $row['name'] ?? '?')
                    .' ('.$matches->count().' coincidencias)';

                continue;
            }

            $barrio = $matches->first();

            $this->line(sprintf(
                '  %s → %.6f, %.6f',
                $barrio->name,
                $row['latitude'],
                $row['longitude']
            ));

            if (! $this->option('dry-run')) {
                $barrio->update([
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                ]);
            }

            $updated++;
        }

        $this->newLine();
        $this->info(($this->option('dry-run') ? '[dry-run] ' : '')."Barrios ubicados: {$updated}");

        if ($missing !== []) {
            $this->warn('Sin coincidencia exacta ('.count($missing).'):');
            foreach ($missing as $m) {
                $this->warn("  - {$m}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{code?: string, name?: string, commune_code?: string, latitude: float, longitude: float}>
     */
    private function readGeoJson(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true);
        $features = $data['features'] ?? [];
        $out = [];

        foreach ($features as $feature) {
            $geometry = $feature['geometry'] ?? [];

            if (($geometry['type'] ?? null) !== 'Point') {
                continue;
            }

            [$lng, $lat] = $geometry['coordinates'] ?? [null, null];

            if ($lng === null || $lat === null) {
                continue;
            }

            $props = $feature['properties'] ?? [];

            $out[] = [
                'code' => $props['code'] ?? null,
                'name' => $props['name'] ?? null,
                'commune_code' => $props['commune_code'] ?? null,
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{code?: string, name?: string, commune_code?: string, latitude: float, longitude: float}>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $out = [];

        while (($line = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $line);

            if (! isset($row['latitude'], $row['longitude'])) {
                continue;
            }

            $out[] = [
                'code' => $row['code'] ?? null,
                'name' => $row['name'] ?? null,
                'commune_code' => $row['commune_code'] ?? null,
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
            ];
        }

        fclose($handle);

        return $out;
    }
}
