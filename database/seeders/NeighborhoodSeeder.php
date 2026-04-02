<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Commune;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;
use RuntimeException;

class NeighborhoodSeeder extends Seeder
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

        $communes = Commune::query()
            ->where('city_id', $city->id)
            ->pluck('id', 'code');

        foreach ($this->neighborhoodsByCommune() as $communeCode => $rows) {
            $communeId = $communes[$communeCode] ?? null;

            if (! $communeId) {
                throw new RuntimeException("No se encontro la comuna {$communeCode} para Girardot.");
            }

            foreach ($rows as $row) {
                Neighborhood::updateOrCreate(
                    [
                        'commune_id' => $communeId,
                        'code' => $row['code'],
                    ],
                    [
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'source_name' => $row['source_name'] ?? null,
                        'is_verified' => $row['is_verified'] ?? true,
                        'notes' => $row['notes'] ?? null,
                    ]
                );
            }
        }
    }

    private function neighborhoodsByCommune(): array
    {
        return [
            'COM-01' => [
                ['source_name' => 'BARRIO BLANCO', 'name' => 'Blanco', 'type' => 'barrio', 'code' => 'COM01-BLANCO'],
                ['source_name' => 'BARRIO CENTRO', 'name' => 'Centro', 'type' => 'barrio', 'code' => 'COM01-CENTRO'],
                ['source_name' => 'BARRIO GRANADA', 'name' => 'Granada', 'type' => 'barrio', 'code' => 'COM01-GRANADA'],
                ['source_name' => 'DEL BARRIO LAS ACACIAS', 'name' => 'Las Acacias', 'type' => 'barrio', 'code' => 'COM01-LAS-ACACIAS'],
                ['source_name' => 'DEL BARRIO MIRAFLORES', 'name' => 'Miraflores', 'type' => 'barrio', 'code' => 'COM01-MIRAFLORES'],
                ['source_name' => 'DEL BARRIO SAN ANTONIO', 'name' => 'San Antonio', 'type' => 'barrio', 'code' => 'COM01-SAN-ANTONIO'],
                ['source_name' => 'DEL BARRIO SAN MIGUEL', 'name' => 'San Miguel', 'type' => 'barrio', 'code' => 'COM01-SAN-MIGUEL'],
                ['source_name' => 'DEL BARRIO SANTANDER', 'name' => 'Santander', 'type' => 'barrio', 'code' => 'COM01-SANTANDER'],
                ['source_name' => 'LA MAGDALENA', 'name' => 'La Magdalena', 'type' => 'barrio', 'code' => 'COM01-LA-MAGDALENA'],
                ['source_name' => 'SUCRE', 'name' => 'Sucre', 'type' => 'barrio', 'code' => 'COM01-SUCRE'],
            ],
            'COM-02' => [
                ['source_name' => 'DE BARRIO BOCAS DEL BOGOTA', 'name' => 'Bocas del Bogota', 'type' => 'barrio', 'code' => 'COM02-BOCAS-DEL-BOGOTA'],
                ['source_name' => 'DEL BARRIO PUERTO CABRERA', 'name' => 'Puerto Cabrera', 'type' => 'barrio', 'code' => 'COM02-PUERTO-CABRERA'],
                ['source_name' => 'DEL BARRIO 10 DE MAYO', 'name' => '10 de Mayo', 'type' => 'barrio', 'code' => 'COM02-10-DE-MAYO'],
                ['source_name' => 'DEL BARRIO ALTO DEL ROSARIO', 'name' => 'Alto del Rosario', 'type' => 'barrio', 'code' => 'COM02-ALTO-DEL-ROSARIO'],
                ['source_name' => 'DEL BARRIO BARBULA PUERTO MONTERO', 'name' => 'Barbula Puerto Montero', 'type' => 'barrio', 'code' => 'COM02-BARBULA-PUERTO-MONTERO', 'is_verified' => false, 'notes' => 'Validar nombre oficial'],
                ['source_name' => 'DEL BARRIO DE LAS ROSAS', 'name' => 'Las Rosas', 'type' => 'barrio', 'code' => 'COM02-LAS-ROSAS'],
                ['source_name' => 'DEL BARRIO EL PORVENIR', 'name' => 'El Porvenir', 'type' => 'barrio', 'code' => 'COM02-EL-PORVENIR'],
                ['source_name' => 'DEL BARRIO OBRERO ALTO DE LA CRUZ', 'name' => 'Obrero Alto de la Cruz', 'type' => 'barrio', 'code' => 'COM02-OBRERO-ALTO-DE-LA-CRUZ'],
                ['source_name' => 'DEL BARRIO PUERTO MONGUI', 'name' => 'Puerto Mongui', 'type' => 'barrio', 'code' => 'COM02-PUERTO-MONGUI'],
                ['source_name' => 'DEL BARRIO VEINTE DE JULIO', 'name' => 'Veinte de Julio', 'type' => 'barrio', 'code' => 'COM02-VEINTE-DE-JULIO'],
                ['source_name' => 'DEL BARRIO VILLA DEL RIO', 'name' => 'Villa del Rio', 'type' => 'barrio', 'code' => 'COM02-VILLA-DEL-RIO'],
                ['source_name' => 'DIVINO NINO PRIMERA ETAPA', 'name' => 'Divino Nino Primera Etapa', 'type' => 'barrio', 'code' => 'COM02-DIVINO-NINO-I'],
                ['source_name' => 'PARQUES BOCAS BOGOTA', 'name' => 'Parques Bocas Bogota', 'type' => 'urbanizacion', 'code' => 'COM02-PARQUES-BOCAS-BOGOTA'],
                ['source_name' => 'URBANIZACION SANTA MONICA', 'name' => 'Santa Monica', 'type' => 'urbanizacion', 'code' => 'COM02-SANTA-MONICA'],
                ['source_name' => 'JVC ACACIAS II', 'name' => 'Acacias II', 'type' => 'jvc', 'code' => 'COM02-JVC-ACACIAS-II'],
                ['source_name' => 'JVC SAN RAFAEL', 'name' => 'San Rafael', 'type' => 'jvc', 'code' => 'COM02-JVC-SAN-RAFAEL'],
            ],
            'COM-03' => [
                ['source_name' => 'BARRIO CAMBULOS III ETAPA', 'name' => 'Cambulos III Etapa', 'type' => 'barrio', 'code' => 'COM03-CAMBULOS-III'],
                ['source_name' => 'BARRIO LOS ARRAYANES', 'name' => 'Los Arrayanes', 'type' => 'barrio', 'code' => 'COM03-LOS-ARRAYANES'],
                ['source_name' => 'BARRIO POZO AZUL', 'name' => 'Pozo Azul', 'type' => 'barrio', 'code' => 'COM03-POZO-AZUL'],
                ['source_name' => 'BARRIO SANTA PAULA RESORT', 'name' => 'Santa Paula Resort', 'type' => 'barrio', 'code' => 'COM03-SANTA-PAULA-RESORT'],
                ['source_name' => 'BARRIO VILLAMPISS', 'name' => 'Villampiss', 'type' => 'barrio', 'code' => 'COM03-VILLAMPISS', 'is_verified' => false, 'notes' => 'Validar spelling oficial'],
                ['source_name' => 'DE EL BARRIO LA COLINA', 'name' => 'La Colina', 'type' => 'barrio', 'code' => 'COM03-LA-COLINA'],
                ['source_name' => 'DE LA URBANIZACION LOS CAMBULOS', 'name' => 'Los Cambulos', 'type' => 'urbanizacion', 'code' => 'COM03-LOS-CAMBULOS'],
                ['source_name' => 'DE LOS BARRIO GOLGOTA Y CENTENARIO', 'name' => 'Golgota y Centenario', 'type' => 'sector', 'code' => 'COM03-GOLGOTA-CENTENARIO'],
                ['source_name' => 'DEL BARRIO BUENOS AIRES', 'name' => 'Buenos Aires', 'type' => 'barrio', 'code' => 'COM03-BUENOS-AIRES'],
                ['source_name' => 'DEL BARRIO GOLGOTA', 'name' => 'Golgota', 'type' => 'barrio', 'code' => 'COM03-GOLGOTA'],
                ['source_name' => 'DEL BARRIO JORGE ELIECER GAITAN', 'name' => 'Jorge Eliecer Gaitan', 'type' => 'barrio', 'code' => 'COM03-JORGE-ELIECER-GAITAN'],
                ['source_name' => 'DEL BARRIO LA ESPERANZA', 'name' => 'La Esperanza', 'type' => 'barrio', 'code' => 'COM03-LA-ESPERANZA'],
                ['source_name' => 'DEL BARRIO LA ESPERANZA VI ETAPA', 'name' => 'La Esperanza VI Etapa', 'type' => 'barrio', 'code' => 'COM03-LA-ESPERANZA-VI'],
                ['source_name' => 'DEL BARRIO LA ESTACION', 'name' => 'La Estacion', 'type' => 'barrio', 'code' => 'COM03-LA-ESTACION'],
                ['source_name' => 'DEL BARRIO MENESES', 'name' => 'Meneses', 'type' => 'barrio', 'code' => 'COM03-MENESES'],
                ['source_name' => 'DEL BARRIO SAAVEDRA GALINDO QUINTAS F', 'name' => 'Saavedra Galindo Quintas F', 'type' => 'barrio', 'code' => 'COM03-SAAVEDRA-GALINDO-QUINTAS-F'],
                ['source_name' => 'DEL BARRIO SANTA ELENA', 'name' => 'Santa Elena', 'type' => 'barrio', 'code' => 'COM03-SANTA-ELENA'],
                ['source_name' => 'DEL BARRIO SANTA ISABEL', 'name' => 'Santa Isabel', 'type' => 'barrio', 'code' => 'COM03-SANTA-ISABEL'],
                ['source_name' => 'DEL BARRIO VILLA ALEXANDER', 'name' => 'Villa Alexander', 'type' => 'barrio', 'code' => 'COM03-VILLA-ALEXANDER'],
                ['source_name' => 'LA CIUDADELA CAFAM DEL SOL ETAPA I', 'name' => 'Ciudadela Cafam del Sol Etapa I', 'type' => 'ciudadela', 'code' => 'COM03-CAFAM-DEL-SOL-I'],
                ['source_name' => 'LA CIUDADELA CAFAM DEL SOL ETAPA II', 'name' => 'Ciudadela Cafam del Sol Etapa II', 'type' => 'ciudadela', 'code' => 'COM03-CAFAM-DEL-SOL-II'],
                ['source_name' => 'QUINTO PATIO', 'name' => 'Quinto Patio', 'type' => 'barrio', 'code' => 'COM03-QUINTO-PATIO'],
                ['source_name' => 'URBANIZACION HACIENDA GIRARDOT I', 'name' => 'Hacienda Girardot I', 'type' => 'urbanizacion', 'code' => 'COM03-HACIENDA-GIRARDOT-I'],
                ['source_name' => 'URBANIZACION HACIENDA GIRARDOT ETAPA II', 'name' => 'Hacienda Girardot Etapa II', 'type' => 'urbanizacion', 'code' => 'COM03-HACIENDA-GIRARDOT-II'],
                ['source_name' => 'URBANIZACION NUESTRA SENORA DEL CARMEN', 'name' => 'Nuestra Senora del Carmen', 'type' => 'urbanizacion', 'code' => 'COM03-NUESTRA-SENORA-DEL-CARMEN'],
                ['source_name' => 'URBANIZACION VILLA CAROLINA I', 'name' => 'Villa Carolina I', 'type' => 'urbanizacion', 'code' => 'COM03-VILLA-CAROLINA-I'],
                ['source_name' => 'URBANIZACION VILLA CAROLINA II', 'name' => 'Villa Carolina II', 'type' => 'urbanizacion', 'code' => 'COM03-VILLA-CAROLINA-II'],
                ['source_name' => 'URBANIZACION VILLA CECILIA', 'name' => 'Villa Cecilia', 'type' => 'urbanizacion', 'code' => 'COM03-VILLA-CECILIA'],
                ['source_name' => 'VIVISOL', 'name' => 'Vivisol', 'type' => 'barrio', 'code' => 'COM03-VIVISOL', 'is_verified' => false, 'notes' => 'Validar si es nombre oficial o comercial'],
                ['source_name' => 'JVC LA MILAGROSA', 'name' => 'La Milagrosa', 'type' => 'jvc', 'code' => 'COM03-JVC-LA-MILAGROSA'],
                ['source_name' => 'JVC NUEVO POZO AZUL', 'name' => 'Nuevo Pozo Azul', 'type' => 'jvc', 'code' => 'COM03-JVC-NUEVO-POZO-AZUL'],
            ],
            'COM-04' => [
                ['source_name' => 'BARRIO CIUDAD MONTES', 'name' => 'Ciudad Montes', 'type' => 'barrio', 'code' => 'COM04-CIUDAD-MONTES'],
                ['source_name' => 'BARRIO JUAN PABLO II', 'name' => 'Juan Pablo II', 'type' => 'barrio', 'code' => 'COM04-JUAN-PABLO-II'],
                ['source_name' => 'BARRIO LA ESMERALDA', 'name' => 'La Esmeralda', 'type' => 'barrio', 'code' => 'COM04-LA-ESMERALDA'],
                ['source_name' => 'BARRIO LA ESMERALDA SEGUNDA ETAPA', 'name' => 'La Esmeralda Segunda Etapa', 'type' => 'barrio', 'code' => 'COM04-LA-ESMERALDA-II'],
                ['source_name' => 'BARRIO LOS NARANJOS', 'name' => 'Los Naranjos', 'type' => 'barrio', 'code' => 'COM04-LOS-NARANJOS'],
                ['source_name' => 'DE LA URBANIZACION ALGARROBOS IV ETAPA BRISAS DE GIRARDOT', 'name' => 'Algarrobos IV Etapa Brisas de Girardot', 'type' => 'urbanizacion', 'code' => 'COM04-ALGARROBOS-IV-BRISAS', 'is_verified' => false, 'notes' => 'Validar si se divide en dos registros'],
                ['source_name' => 'DE LA URBANIZACION CORAZON DE CUNDINAMARCA', 'name' => 'Corazon de Cundinamarca', 'type' => 'urbanizacion', 'code' => 'COM04-CORAZON-DE-CUNDINAMARCA'],
                ['source_name' => 'DE LA URBANIZACION VALLE DEL SOL', 'name' => 'Valle del Sol', 'type' => 'urbanizacion', 'code' => 'COM04-VALLE-DEL-SOL'],
                ['source_name' => 'DEL BARRIO ROSA BLANCA', 'name' => 'Rosa Blanca', 'type' => 'barrio', 'code' => 'COM04-ROSA-BLANCA'],
                ['source_name' => 'DEL BARRIO EL DIAMANTE', 'name' => 'El Diamante', 'type' => 'barrio', 'code' => 'COM04-EL-DIAMANTE'],
                ['source_name' => 'DEL BARRIO ROSABLANCA SEGUNDO SECTOR', 'name' => 'Rosablanca Segundo Sector', 'type' => 'sector', 'code' => 'COM04-ROSABLANCA-SEGUNDO-SECTOR'],
                ['source_name' => 'DEL BARRIO SAN FERNANDO', 'name' => 'San Fernando', 'type' => 'barrio', 'code' => 'COM04-SAN-FERNANDO'],
                ['source_name' => 'DEL BARRIO SANTA RITA', 'name' => 'Santa Rita', 'type' => 'barrio', 'code' => 'COM04-SANTA-RITA'],
                ['source_name' => 'DEL BARRIO ZULIA', 'name' => 'Zulia', 'type' => 'barrio', 'code' => 'COM04-ZULIA'],
                ['source_name' => 'DEL SECTOR ESPERANZA NORTE DE LA VEREDA PORTACHUELO', 'name' => 'Esperanza Norte de Portachuelo', 'type' => 'sector', 'code' => 'COM04-ESPERANZA-NORTE-PORTACHUELO'],
                ['source_name' => 'SECTOR NORORIENTAL DEL BARRIO DIAMANTE', 'name' => 'Sector Nororiental del Barrio Diamante', 'type' => 'sector', 'code' => 'COM04-SECTOR-NORORIENTAL-DIAMANTE'],
                ['source_name' => 'URBANIZACION ALTOS DEL PENON', 'name' => 'Altos del Penon', 'type' => 'urbanizacion', 'code' => 'COM04-ALTOS-DEL-PENON'],
                ['source_name' => 'URBANIZACION BOSQUES DE VISCAYA', 'name' => 'Bosques de Viscaya', 'type' => 'urbanizacion', 'code' => 'COM04-BOSQUES-DE-VISCAYA', 'is_verified' => false, 'notes' => 'Validar spelling vs Vizcaya'],
                ['source_name' => 'URBANIZACION BOSQUES DE VIZCAYA II', 'name' => 'Bosques de Vizcaya II', 'type' => 'urbanizacion', 'code' => 'COM04-BOSQUES-DE-VIZCAYA-II'],
                ['source_name' => 'URBANIZACION EL DIAMANTE V ETAPA', 'name' => 'El Diamante V Etapa', 'type' => 'urbanizacion', 'code' => 'COM04-EL-DIAMANTE-V'],
                ['source_name' => 'URBANIZACION EL TALISMAN', 'name' => 'El Talisman', 'type' => 'urbanizacion', 'code' => 'COM04-EL-TALISMAN'],
                ['source_name' => 'URBANIZACION ESMERALDA III ETAPA', 'name' => 'Esmeralda III Etapa', 'type' => 'urbanizacion', 'code' => 'COM04-ESMERALDA-III'],
                ['source_name' => 'URBANIZACION LOS ALGARROBOS III ETAPA', 'name' => 'Los Algarrobos III Etapa', 'type' => 'urbanizacion', 'code' => 'COM04-LOS-ALGARROBOS-III'],
                ['source_name' => 'URBANIZACION RAMON BUENO', 'name' => 'Ramon Bueno', 'type' => 'urbanizacion', 'code' => 'COM04-RAMON-BUENO'],
                ['source_name' => 'URBANIZACION SOLARIS', 'name' => 'Solaris', 'type' => 'urbanizacion', 'code' => 'COM04-SOLARIS'],
                ['source_name' => 'JVC URBANIZACION POPULAR EL DIAMANTE', 'name' => 'Urbanizacion Popular El Diamante', 'type' => 'jvc', 'code' => 'COM04-JVC-POPULAR-EL-DIAMANTE'],
            ],
            'COM-05' => [
                ['source_name' => 'BARRIO EL TRIUNFO', 'name' => 'El Triunfo', 'type' => 'barrio', 'code' => 'COM05-EL-TRIUNFO'],
                ['source_name' => 'BARRIO KENNEDY II SECTOR', 'name' => 'Kennedy II Sector', 'type' => 'barrio', 'code' => 'COM05-KENNEDY-II'],
                ['source_name' => 'BARRIO KENNEDY SECTOR I', 'name' => 'Kennedy Sector I', 'type' => 'barrio', 'code' => 'COM05-KENNEDY-I'],
                ['source_name' => 'BARRIO PORTACHUELO', 'name' => 'Portachuelo', 'type' => 'barrio', 'code' => 'COM05-PORTACHUELO'],
                ['source_name' => 'CEDRO VILLAOLARTE', 'name' => 'Cedro Villaolarte', 'type' => 'barrio', 'code' => 'COM05-CEDRO-VILLAOLARTE', 'is_verified' => false, 'notes' => 'Validar si son dos sectores o un solo nombre'],
                ['source_name' => 'DE BARRIO OBRERO', 'name' => 'Obrero', 'type' => 'barrio', 'code' => 'COM05-OBRERO'],
                ['source_name' => 'DE SAN JORGE', 'name' => 'San Jorge', 'type' => 'barrio', 'code' => 'COM05-SAN-JORGE'],
                ['source_name' => 'DEL BARRIO SUBNORMAL LA VICTORIA', 'name' => 'Subnormal La Victoria', 'type' => 'sector', 'code' => 'COM05-SUBNORMAL-LA-VICTORIA'],
                ['source_name' => 'DEL BARRIO BRISAS DEL BOGOTA', 'name' => 'Brisas del Bogota', 'type' => 'barrio', 'code' => 'COM05-BRISAS-DEL-BOGOTA'],
                ['source_name' => 'DEL BARRIO KENNEDY III SECTOR', 'name' => 'Kennedy III Sector', 'type' => 'barrio', 'code' => 'COM05-KENNEDY-III'],
                ['source_name' => 'DEL BARRIO PRIMERO DE ENERO', 'name' => 'Primero de Enero', 'type' => 'barrio', 'code' => 'COM05-PRIMERO-DE-ENERO'],
                ['source_name' => 'DEL BARRIO SALSIPUEDES', 'name' => 'Salsipuedes', 'type' => 'barrio', 'code' => 'COM05-SALSIPUEDES'],
                ['source_name' => 'DEL BARRIO SANTA FE', 'name' => 'Santa Fe', 'type' => 'barrio', 'code' => 'COM05-SANTA-FE'],
                ['source_name' => 'DEL BARRIO VILLA KENNEDY', 'name' => 'Villa Kennedy', 'type' => 'barrio', 'code' => 'COM05-VILLA-KENNEDY'],
                ['source_name' => 'IV SECTOR DEL BARRIO KENNEDY', 'name' => 'Kennedy IV Sector', 'type' => 'barrio', 'code' => 'COM05-KENNEDY-IV'],
                ['source_name' => 'URBANIZACION EL COROZO', 'name' => 'El Corozo', 'type' => 'urbanizacion', 'code' => 'COM05-EL-COROZO'],
                ['source_name' => 'URBANIZACION LA CAROLINA A Y B', 'name' => 'La Carolina A y B', 'type' => 'urbanizacion', 'code' => 'COM05-LA-CAROLINA-AYB'],
                ['source_name' => 'URBANIZACION MAGDALENA III', 'name' => 'Magdalena III', 'type' => 'urbanizacion', 'code' => 'COM05-MAGDALENA-III'],
            ],
            'VRD-N' => [
                ['source_name' => 'BARZALOSA NORTE', 'name' => 'Barzalosa Norte', 'type' => 'vereda', 'code' => 'VRDN-BARZALOSA-NORTE'],
                ['source_name' => 'DE LA URBANIZACION LOS PRADOS II DEL SECTOR RURAL', 'name' => 'Los Prados II del Sector Rural', 'type' => 'urbanizacion', 'code' => 'VRDN-LOS-PRADOS-II'],
                ['source_name' => 'DE LA URBANIZACION LUIS CARLOS GALAN', 'name' => 'Luis Carlos Galan', 'type' => 'urbanizacion', 'code' => 'VRDN-LUIS-CARLOS-GALAN'],
                ['source_name' => 'DE LA VEREDA BARZALOSA SECTOR CEMENTERIO', 'name' => 'Barzalosa Sector Cementerio', 'type' => 'sector', 'code' => 'VRDN-BARZALOSA-CEMENTERIO'],
                ['source_name' => 'DE LA VEREDA DE BARZALOSA', 'name' => 'Barzalosa', 'type' => 'vereda', 'code' => 'VRDN-BARZALOSA'],
                ['source_name' => 'DE LA VEREDA GUABINAL', 'name' => 'Guabinal', 'type' => 'vereda', 'code' => 'VRDN-GUABINAL'],
                ['source_name' => 'DE VEREDA BERLIN', 'name' => 'Berlin', 'type' => 'vereda', 'code' => 'VRDN-BERLIN'],
                ['source_name' => 'DE VEREDA PIAMONTE', 'name' => 'Piamonte', 'type' => 'vereda', 'code' => 'VRDN-PIAMONTE'],
                ['source_name' => 'DEL SECTOR CERRO DE LA VEREDA GUABINAL', 'name' => 'Cerro de la Vereda Guabinal', 'type' => 'sector', 'code' => 'VRDN-CERRO-GUABINAL'],
                ['source_name' => 'URBANIZACION LOS PRADOS PRIMER SECTOR', 'name' => 'Los Prados Primer Sector', 'type' => 'urbanizacion', 'code' => 'VRDN-LOS-PRADOS-I'],
                ['source_name' => 'VEREDA PRESIDENTE', 'name' => 'Presidente', 'type' => 'vereda', 'code' => 'VRDN-PRESIDENTE'],
                ['source_name' => 'JVC SIMON BOLIVAR', 'name' => 'Simon Bolivar', 'type' => 'jvc', 'code' => 'VRDN-JVC-SIMON-BOLIVAR'],
            ],
            'VRD-S' => [
                ['source_name' => 'DE LA VEREDA AGUA BLANCA', 'name' => 'Agua Blanca', 'type' => 'vereda', 'code' => 'VRDS-AGUA-BLANCA'],
                ['source_name' => 'DE LA VEREDA DE SAN LORENZO', 'name' => 'San Lorenzo', 'type' => 'vereda', 'code' => 'VRDS-SAN-LORENZO'],
                ['source_name' => 'DE LA VEREDA ZUMBAMICOS', 'name' => 'Zumbamicos', 'type' => 'vereda', 'code' => 'VRDS-ZUMBAMICOS'],
            ],
        ];
    }
}
