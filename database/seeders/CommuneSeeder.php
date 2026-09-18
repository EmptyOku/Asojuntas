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

        $boundaries = $this->boundaries();

        foreach ($this->communes() as $commune) {
            Commune::updateOrCreate(
                [
                    'city_id' => $city->id,
                    'code' => $commune['code'],
                ],
                [
                    'name' => $commune['name'],
                    'boundary' => $boundaries[$commune['code']] ?? null,
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

    /**
     * Lee database/data/comunas.geojson y convierte cada LineString en un
     * Polygon GeoJSON (cerrando el anillo). El orden de los features del
     * archivo se asume igual al de las comunas 1..5.
     *
     * @return array<string, array{type: string, coordinates: array}>
     */
    private function boundaries(): array
    {
        $path = database_path('data/comunas.geojson');

        if (! is_file($path)) {
            return [];
        }

        $geojson = json_decode((string) file_get_contents($path), true);
        $features = $geojson['features'] ?? [];

        // El archivo trae los dos primeros contornos invertidos respecto a la
        // realidad: el feature 0 es la Comuna 2 y el feature 1 es la Comuna 1.
        $codes = ['COM-02', 'COM-01', 'COM-03', 'COM-04', 'COM-05'];
        $out = [];

        foreach ($features as $i => $feature) {
            if (! isset($codes[$i])) {
                break;
            }

            $coords = $feature['geometry']['coordinates'] ?? [];

            if (count($coords) < 3) {
                continue;
            }

            // Cerrar el anillo si el primer y ultimo punto no coinciden.
            if ($coords[0] !== $coords[count($coords) - 1]) {
                $coords[] = $coords[0];
            }

            $out[$codes[$i]] = [
                'type' => 'Polygon',
                'coordinates' => [$coords],
            ];
        }

        return $out;
    }
}
