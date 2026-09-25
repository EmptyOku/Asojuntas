<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Candidate;
use App\Models\City;
use App\Models\Commune;
use App\Models\DocumentType;
use App\Models\Election;
use App\Models\ElectionBlock;
use App\Models\ElectionBlockPosition;
use App\Models\Neighborhood;
use App\Models\Person;
use App\Models\PollingTable;
use App\Models\Position;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyRecord;
use App\Models\Slate;
use App\Models\SlateBlock;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * El mapa electoral depende de que communes-geo y neighborhoods/geo calculen
 * bien el semaforo por envio de actas, las mesas atrasadas y el ganador por
 * barrio. Estas pruebas fijan un escenario conocido y verifican el numero
 * exacto que debe salir, no solo que la ruta responda 200.
 */
class ElectoralMapGeoTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private function makeCommune(string $code, string $name): Commune
    {
        $state = State::firstOrCreate(['code' => 'CUN'], ['name' => 'Cundinamarca']);
        $city = City::firstOrCreate(['state_id' => $state->id, 'code' => 'GIR'], ['name' => 'Girardot']);

        return Commune::create([
            'city_id' => $city->id,
            'code' => $code,
            'name' => $name,
            'boundary' => [
                'type' => 'Polygon',
                'coordinates' => [[[-74.81, 4.30], [-74.80, 4.30], [-74.80, 4.31], [-74.81, 4.31], [-74.81, 4.30]]],
            ],
        ]);
    }

    private function makeGeoNeighborhood(Commune $commune, string $name, string $code, int $order, float $lat, float $lng): Neighborhood
    {
        return Neighborhood::create([
            'commune_id' => $commune->id,
            'name' => $name,
            'code' => $code,
            'latitude' => $lat,
            'longitude' => $lng,
            'map_order' => $order,
        ]);
    }

    private function makeActiveElection(Neighborhood $neighborhood, string $electionDate): Election
    {
        return Election::create([
            'neighborhood_id' => $neighborhood->id,
            'name' => 'Eleccion '.$neighborhood->name,
            'code' => 'EL-'.$neighborhood->code,
            'election_date' => $electionDate,
            'is_active' => true,
        ]);
    }

    private function makeScrutinyRecord(Election $election, PollingTable $pollingTable, string $status): ScrutinyRecord
    {
        return ScrutinyRecord::create([
            'election_id' => $election->id,
            'polling_table_id' => $pollingTable->id,
            'record_number' => 'ACTA-'.$election->id,
            'record_date' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    /**
     * Crea el bloque Directiva con dos planchas (P1 gana, P2 pierde) y un
     * candidato a presidente por cada una, para poder calcular un ganador.
     */
    private function makeDirectivaWinner(Election $election): array
    {
        // RefreshDatabase solo migra, no siembra el catalogo (blocks/positions).
        $block = Block::firstOrCreate(['code' => 'DIR'], ['name' => 'Directiva', 'is_active' => true]);
        $posPres = Position::firstOrCreate(
            ['code' => 'DIR_PRES'],
            ['block_id' => $block->id, 'name' => 'Presidente', 'order_number' => 1, 'is_active' => true]
        );
        $blockId = $block->id;
        $posPresId = $posPres->id;

        $electionBlock = ElectionBlock::create(['election_id' => $election->id, 'block_id' => $blockId, 'is_active' => true]);
        $ebp = ElectionBlockPosition::create([
            'election_block_id' => $electionBlock->id, 'block_id' => $blockId, 'position_id' => $posPresId, 'vacancies' => 1, 'is_active' => true,
        ]);

        $slateGanadora = Slate::create(['election_id' => $election->id, 'code' => 'P1', 'name' => 'Plancha A', 'is_active' => true]);
        $slatePerdedora = Slate::create(['election_id' => $election->id, 'code' => 'P2', 'name' => 'Plancha B', 'is_active' => true]);

        $slateBlockGanadora = SlateBlock::create(['election_id' => $election->id, 'slate_id' => $slateGanadora->id, 'election_block_id' => $electionBlock->id, 'is_active' => true]);
        $slateBlockPerdedora = SlateBlock::create(['election_id' => $election->id, 'slate_id' => $slatePerdedora->id, 'election_block_id' => $electionBlock->id, 'is_active' => true]);

        $documentTypeId = DocumentType::firstOrCreate(['code' => 'CC'], ['name' => 'Cédula de ciudadanía'])->id;

        $personGanador = Person::create(['document_type_id' => $documentTypeId, 'document_number' => 'W'.$election->id, 'first_name' => 'Ganadora', 'last_name' => 'Prueba']);
        $personPerdedor = Person::create(['document_type_id' => $documentTypeId, 'document_number' => 'L'.$election->id, 'first_name' => 'Perdedor', 'last_name' => 'Prueba']);

        Candidate::create(['election_id' => $election->id, 'person_id' => $personGanador->id, 'slate_block_id' => $slateBlockGanadora->id, 'election_block_position_id' => $ebp->id, 'is_active' => true]);
        Candidate::create(['election_id' => $election->id, 'person_id' => $personPerdedor->id, 'slate_block_id' => $slateBlockPerdedora->id, 'election_block_position_id' => $ebp->id, 'is_active' => true]);

        return [$electionBlock->id, $slateBlockGanadora->id, $slateBlockPerdedora->id];
    }

    #[Test]
    public function communes_geo_calcula_semaforo_y_mesas_atrasadas(): void
    {
        $commune = $this->makeCommune('T-01', 'Comuna Prueba');

        // Barrio A: con acta recibida.
        $barrioA = $this->makeGeoNeighborhood($commune, 'Barrio A', 'T01-A', 0, 4.305, -74.805);
        $electionA = $this->makeActiveElection($barrioA, now()->toDateString());
        $mesaA = PollingTable::create(['election_id' => $electionA->id, 'name' => 'Mesa A', 'code' => 'MA', 'is_active' => true]);
        $this->makeScrutinyRecord($electionA, $mesaA, 'pending_review');

        // Barrio B: eleccion vencida ayer, sin ninguna acta -> atrasada.
        $barrioB = $this->makeGeoNeighborhood($commune, 'Barrio B', 'T01-B', 1, 4.306, -74.806);
        $electionB = $this->makeActiveElection($barrioB, now()->subDay()->toDateString());
        PollingTable::create(['election_id' => $electionB->id, 'name' => 'Mesa B', 'code' => 'MB', 'is_active' => true]);

        $admin = $this->makeUser(['map.view']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/neighborhoods/communes-geo')
            ->assertOk();

        $feature = collect($response->json('features'))
            ->firstWhere('properties.code', 'T-01');

        $this->assertNotNull($feature, 'La comuna de prueba debia aparecer en communes-geo.');
        $props = $feature['properties'];

        $this->assertSame(2, $props['mesas_total']);
        $this->assertSame(1, $props['mesas_recibidas']);
        $this->assertSame(1, $props['mesas_atrasadas']);
        $this->assertSame(50, $props['actas_pct']);
        $this->assertSame('amarillo', $props['semaforo']);
    }

    #[Test]
    public function communes_geo_es_verde_cuando_todas_las_mesas_tienen_acta(): void
    {
        $commune = $this->makeCommune('T-02', 'Comuna Verde');
        $barrio = $this->makeGeoNeighborhood($commune, 'Barrio Unico', 'T02-A', 0, 4.31, -74.81);
        $election = $this->makeActiveElection($barrio, now()->toDateString());
        $mesa = PollingTable::create(['election_id' => $election->id, 'name' => 'Mesa', 'code' => 'M', 'is_active' => true]);
        $this->makeScrutinyRecord($election, $mesa, 'consolidated');

        $admin = $this->makeUser(['map.view']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/neighborhoods/communes-geo')
            ->assertOk();

        $feature = collect($response->json('features'))->firstWhere('properties.code', 'T-02');

        $this->assertSame(100, $feature['properties']['actas_pct']);
        $this->assertSame('verde', $feature['properties']['semaforo']);
        $this->assertSame(0, $feature['properties']['mesas_atrasadas']);
    }

    #[Test]
    public function neighborhoods_geo_marca_atrasada_y_calcula_el_ganador(): void
    {
        $commune = $this->makeCommune('T-03', 'Comuna Mixta');

        // Verde: acta consolidada + resultados -> debe traer el nombre del ganador.
        $barrioVerde = $this->makeGeoNeighborhood($commune, 'Barrio Verde', 'T03-VERDE', 0, 4.30, -74.80);
        $electionVerde = $this->makeActiveElection($barrioVerde, now()->toDateString());
        $mesaVerde = PollingTable::create(['election_id' => $electionVerde->id, 'name' => 'Mesa', 'code' => 'MV', 'is_active' => true]);
        $record = $this->makeScrutinyRecord($electionVerde, $mesaVerde, 'consolidated');
        [$electionBlockId, $slateBlockGanadora, $slateBlockPerdedora] = $this->makeDirectivaWinner($electionVerde);
        ScrutinyBlockResult::create(['scrutiny_record_id' => $record->id, 'election_id' => $electionVerde->id, 'election_block_id' => $electionBlockId, 'slate_block_id' => $slateBlockGanadora, 'votes' => 80]);
        ScrutinyBlockResult::create(['scrutiny_record_id' => $record->id, 'election_id' => $electionVerde->id, 'election_block_id' => $electionBlockId, 'slate_block_id' => $slateBlockPerdedora, 'votes' => 20]);

        // Atrasado: eleccion de ayer, sin acta.
        $barrioAtrasado = $this->makeGeoNeighborhood($commune, 'Barrio Atrasado', 'T03-ATRASADO', 1, 4.31, -74.81);
        $this->makeActiveElection($barrioAtrasado, now()->subDay()->toDateString());

        // Al dia (control): eleccion de manana, sin acta -> nunca atrasado.
        $barrioAlDia = $this->makeGeoNeighborhood($commune, 'Barrio Al Dia', 'T03-ALDIA', 2, 4.32, -74.82);
        $this->makeActiveElection($barrioAlDia, now()->addDay()->toDateString());

        $admin = $this->makeUser(['map.view']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/neighborhoods/geo')
            ->assertOk();

        $byCode = collect($response->json('features'))->keyBy('properties.code');

        $verde = $byCode->get('T03-VERDE')['properties'];
        $this->assertTrue($verde['has_acta']);
        $this->assertFalse($verde['atrasada']);
        $this->assertSame('Ganadora Prueba', $verde['winner']);

        $atrasado = $byCode->get('T03-ATRASADO')['properties'];
        $this->assertFalse($atrasado['has_acta']);
        $this->assertTrue($atrasado['atrasada']);
        $this->assertNull($atrasado['winner']);

        $alDia = $byCode->get('T03-ALDIA')['properties'];
        $this->assertFalse($alDia['has_acta']);
        $this->assertFalse($alDia['atrasada']);
        $this->assertNull($alDia['winner']);
    }

    #[Test]
    public function las_rutas_geo_exigen_permiso_de_mapa(): void
    {
        $jurado = $this->makeUser(['records.upload']);

        $this->actingAs($jurado)
            ->getJson('/api/admin/neighborhoods/communes-geo')
            ->assertForbidden();

        $this->actingAs($jurado)
            ->getJson('/api/admin/neighborhoods/geo')
            ->assertForbidden();
    }

    #[Test]
    public function un_admin_con_permiso_puede_ubicar_un_barrio_manualmente(): void
    {
        $commune = $this->makeCommune('T-04', 'Comuna Manual');
        $barrio = Neighborhood::create(['commune_id' => $commune->id, 'name' => 'Sin Ubicar', 'code' => 'T04-SINUB']);
        $admin = $this->makeUser(['map.view', 'geography.manage']);

        $this->actingAs($admin)
            ->putJson("/api/admin/neighborhoods/{$barrio->id}/location", [
                'latitude' => 4.305,
                'longitude' => -74.805,
            ])
            ->assertOk()
            ->assertJsonPath('data.latitude', 4.305)
            ->assertJsonPath('data.longitude', -74.805)
            ->assertJsonPath('data.map_order', 0);

        $barrio->refresh();
        $this->assertSame(4.305, $barrio->latitude);
        $this->assertSame(-74.805, $barrio->longitude);
        $this->assertSame(0, $barrio->map_order);

        // Aparece de inmediato en neighborhoods/geo.
        $geo = $this->actingAs($admin)->getJson('/api/admin/neighborhoods/geo')->assertOk();
        $this->assertTrue(collect($geo->json('features'))->contains(fn ($f) => $f['properties']['code'] === 'T04-SINUB'));

        // Y se puede quitar.
        $this->actingAs($admin)
            ->deleteJson("/api/admin/neighborhoods/{$barrio->id}/location")
            ->assertOk();

        $this->assertNull($barrio->refresh()->latitude);
    }

    #[Test]
    public function la_coordenada_manual_se_valida_y_exige_permiso(): void
    {
        $commune = $this->makeCommune('T-05', 'Comuna Validacion');
        $barrio = Neighborhood::create(['commune_id' => $commune->id, 'name' => 'Barrio X', 'code' => 'T05-X']);
        $admin = $this->makeUser(['map.view', 'geography.manage']);

        $this->actingAs($admin)
            ->putJson("/api/admin/neighborhoods/{$barrio->id}/location", ['latitude' => 999, 'longitude' => -74.8])
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');

        $jurado = $this->makeUser(['records.upload']);
        $this->actingAs($jurado)
            ->putJson("/api/admin/neighborhoods/{$barrio->id}/location", ['latitude' => 4.3, 'longitude' => -74.8])
            ->assertForbidden();
    }

    #[Test]
    public function un_admin_puede_crear_renombrar_y_eliminar_un_barrio_manualmente(): void
    {
        $commune = $this->makeCommune('T-06', 'Comuna CRUD');
        $admin = $this->makeUser(['map.view', 'geography.manage']);

        // Crear.
        $create = $this->actingAs($admin)
            ->postJson('/api/admin/neighborhoods', ['commune_id' => $commune->id, 'name' => 'Barrio Nuevo'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Barrio Nuevo')
            ->assertJsonPath('data.has_coordinates', false);

        $id = $create->json('data.id');
        $this->assertSame('T-06-BARRIO-NUEVO', $create->json('data.code'));

        // No se permite duplicar el nombre en la misma comuna.
        $this->actingAs($admin)
            ->postJson('/api/admin/neighborhoods', ['commune_id' => $commune->id, 'name' => 'Barrio Nuevo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        // Renombrar.
        $this->actingAs($admin)
            ->putJson("/api/admin/neighborhoods/{$id}", ['name' => 'Barrio Renombrado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Barrio Renombrado');

        $this->assertSame('Barrio Renombrado', Neighborhood::find($id)->name);

        // Eliminar (borrado suave).
        $this->actingAs($admin)
            ->deleteJson("/api/admin/neighborhoods/{$id}")
            ->assertOk();

        $this->assertSoftDeleted('neighborhoods', ['id' => $id]);
        $this->assertNull(Neighborhood::find($id)); // el scope global de SoftDeletes lo oculta
    }

    #[Test]
    public function no_se_puede_eliminar_un_barrio_con_actas_registradas(): void
    {
        $commune = $this->makeCommune('T-07', 'Comuna Protegida');
        $barrio = $this->makeGeoNeighborhood($commune, 'Barrio Con Actas', 'T07-A', 0, 4.3, -74.8);
        $election = $this->makeActiveElection($barrio, now()->toDateString());
        $mesa = PollingTable::create(['election_id' => $election->id, 'name' => 'Mesa', 'code' => 'M', 'is_active' => true]);
        $this->makeScrutinyRecord($election, $mesa, 'consolidated');

        $admin = $this->makeUser(['geography.manage']);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/neighborhoods/{$barrio->id}")
            ->assertStatus(422);

        $this->assertNotNull(Neighborhood::find($barrio->id));
    }

    #[Test]
    public function crear_y_editar_barrios_exige_permiso(): void
    {
        $commune = $this->makeCommune('T-08', 'Comuna Permisos');
        $barrio = Neighborhood::create(['commune_id' => $commune->id, 'name' => 'Barrio Y', 'code' => 'T08-Y']);
        $jurado = $this->makeUser(['records.upload']);

        $this->actingAs($jurado)
            ->postJson('/api/admin/neighborhoods', ['commune_id' => $commune->id, 'name' => 'Otro'])
            ->assertForbidden();

        $this->actingAs($jurado)
            ->putJson("/api/admin/neighborhoods/{$barrio->id}", ['name' => 'Cambiado'])
            ->assertForbidden();

        $this->actingAs($jurado)
            ->deleteJson("/api/admin/neighborhoods/{$barrio->id}")
            ->assertForbidden();
    }

    #[Test]
    public function no_se_puede_ubicar_un_barrio_con_una_coordenada_de_otra_comuna(): void
    {
        $comuna1 = $this->makeCommune('T-09-A', 'Comuna Uno');
        $barrio = Neighborhood::create(['commune_id' => $comuna1->id, 'name' => 'Barrio Z', 'code' => 'T09-Z']);
        $admin = $this->makeUser(['geography.manage']);

        // Un punto claramente fuera del contorno de Comuna Uno (4.30-4.31 / -74.81..-74.80) se rechaza.
        $this->actingAs($admin)
            ->putJson("/api/admin/neighborhoods/{$barrio->id}/location", ['latitude' => 4.50, 'longitude' => -74.95])
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');

        $this->assertNull($barrio->refresh()->latitude);

        // Una coordenada dentro del contorno de su propia comuna si se acepta.
        $this->actingAs($admin)
            ->putJson("/api/admin/neighborhoods/{$barrio->id}/location", ['latitude' => 4.305, 'longitude' => -74.805])
            ->assertOk();

        $this->assertSame(4.305, $barrio->refresh()->latitude);
    }
}
