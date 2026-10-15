<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Admin\NeighborhoodDirectoryController;
use App\Models\Block;
use App\Models\CandidateDraft;
use App\Models\ElectionBlock;
use App\Models\ScrutinyRecord;
use App\Models\Slate;
use App\Models\SlateBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Reglas que protegen el proceso: quien solo captura planchas trabaja con su
 * barrio, una elección cerrada no recibe actas y un empate no se resuelve solo.
 */
class ElectoralSafeguardsTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private const CAPTURE = ['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update'];

    private function plancha(int $electionId, string $name = 'Ana Prueba', string $document = '5550001'): array
    {
        return [
            'election_id' => $electionId,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'PRESIDENTE', 'nombre' => $name, 'identificacion' => $document],
            ]]]],
        ];
    }

    #[Test]
    public function quien_solo_captura_planchas_trabaja_con_las_de_su_barrio(): void
    {
        $barrioA = $this->makeNeighborhood('Barrio A');
        $barrioB = $this->makeNeighborhood('Barrio B');
        $eleccionA = $this->makeElection($barrioA);
        $eleccionB = $this->makeElection($barrioB);

        $deA = $this->makeUser(self::CAPTURE, $barrioA);
        $deB = $this->makeUser(self::CAPTURE, $barrioB);
        $revisor = $this->makeUser([...self::CAPTURE, 'candidate_drafts.approve', 'candidate_drafts.promote']);

        // Registrar: en su barrio sí, en el otro no.
        $batch = $this->actingAs($deA)->postJson('/api/secretary/planchas/drafts', $this->plancha($eleccionA->id))
            ->assertCreated()->json('data.capture_batch_uuid');
        $this->actingAs($deA)->postJson('/api/secretary/planchas/drafts', $this->plancha($eleccionB->id, 'Luis Otro', '5550002'))
            ->assertForbidden();
        $this->assertSame(0, CandidateDraft::where('election_id', $eleccionB->id)->count());

        $draft = CandidateDraft::where('capture_batch_uuid', $batch)->firstOrFail();

        // El del otro barrio no la ve, no la edita y no le sube fotos.
        $this->assertSame([], $this->actingAs($deB)->getJson('/api/secretary/planchas/drafts')->assertOk()->json('data.data'));
        $this->assertSame([], $this->actingAs($deB)->getJson('/api/secretary/planchas/drafts/grouped')->assertOk()->json('data'));
        $this->actingAs($deB)->getJson('/api/secretary/planchas/drafts?capture_batch_uuid='.$batch)->assertOk()->assertJsonCount(0, 'data.data');
        $this->actingAs($deB)->putJson("/api/secretary/planchas/drafts/{$draft->id}", ['first_name' => 'Otro', 'last_name' => 'Nombre'])->assertForbidden();
        $this->actingAs($deB)->getJson('/api/secretary/planchas/evidence/'.$batch)->assertForbidden();
        Storage::fake('local');
        $this->actingAs($deB)->post('/api/secretary/planchas/evidence', [
            'capture_batch_uuid' => $batch,
            'document_files' => [UploadedFile::fake()->create('foto.jpg', 20, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertForbidden();
        $this->assertSame('Ana', $draft->fresh()->first_name);

        // El buscador de barrios solo le ofrece el suyo.
        $found = collect($this->actingAs($deB)->getJson('/api/secretary/neighborhoods/search?q=Barrio')->assertOk()->json('data'))->pluck('name');
        $this->assertSame(['Barrio B'], $found->all());

        // El dueño sí la ve y la corrige.
        $this->assertCount(1, $this->actingAs($deA)->getJson('/api/secretary/planchas/drafts')->assertOk()->json('data.data'));
        $this->actingAs($deA)->putJson("/api/secretary/planchas/drafts/{$draft->id}", ['first_name' => 'Ana María', 'last_name' => 'Prueba'])->assertOk();

        // Quien revisa planchas atiende todos los barrios, sin tener uno asignado.
        $this->assertCount(1, $this->actingAs($revisor)->getJson('/api/secretary/planchas/drafts')->assertOk()->json('data.data'));
        $this->actingAs($revisor)->postJson('/api/secretary/planchas/drafts', $this->plancha($eleccionB->id, 'Luis Otro', '5550002'))->assertCreated();
        $this->actingAs($revisor)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $batch])->assertOk();
        $this->assertCount(2, $this->actingAs($revisor)->getJson('/api/secretary/neighborhoods/search?q=Barrio')->assertOk()->json('data'));
    }

    #[Test]
    public function buscar_en_la_bandeja_de_planchas_no_falla(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Buscar');
        $eleccion = $this->makeElection($barrio);
        $user = $this->makeUser(self::CAPTURE, $barrio);
        $this->actingAs($user)->postJson('/api/secretary/planchas/drafts', $this->plancha($eleccion->id))->assertCreated();

        // Antes devolvía un error 500 por una variable que no llegaba a la consulta.
        $this->assertCount(1, $this->actingAs($user)->getJson('/api/secretary/planchas/drafts?search=Ana')->assertOk()->json('data.data'));
        $this->assertCount(1, $this->actingAs($user)->getJson('/api/secretary/planchas/drafts?search=Buscar')->assertOk()->json('data.data'));
        $this->assertCount(0, $this->actingAs($user)->getJson('/api/secretary/planchas/drafts?search=Zzz')->assertOk()->json('data.data'));
    }

    #[Test]
    public function una_eleccion_cerrada_no_recibe_actas(): void
    {
        Storage::fake('local');
        $barrio = $this->makeNeighborhood('Barrio Cerrado');
        $eleccion = $this->makeElection($barrio);
        $mesa = $this->makePollingTable($eleccion);
        $block = Block::create(['name' => 'Directiva', 'code' => 'DIR', 'is_active' => true]);
        $electionBlock = ElectionBlock::create(['election_id' => $eleccion->id, 'block_id' => $block->id, 'is_active' => true]);
        $slate = Slate::create(['election_id' => $eleccion->id, 'code' => 'P1', 'name' => 'Plancha 1', 'is_active' => true]);
        SlateBlock::create(['election_id' => $eleccion->id, 'slate_id' => $slate->id, 'election_block_id' => $electionBlock->id, 'is_active' => true]);

        $jurado = $this->makeUser(['scrutiny_records.create', 'elections.view', 'polling_tables.view'], $barrio);
        $enviar = fn (array $extra = []) => $this->actingAs($jurado)->post('/api/jury/submit', $extra + [
            'document_file' => UploadedFile::fake()->create('acta-'.uniqid().'.jpg', random_int(5, 500), 'image/jpeg'),
            'page_number' => 1,
            'normalized_payload' => json_encode(['block_votes' => [['block_name' => 'Directiva', 'plancha_1' => 10]]]),
        ], ['Accept' => 'application/json']);

        $recordId = $enviar()->assertCreated()->json('data.scrutiny_record_id');
        $this->assertMatchesRegularExpression('/^AUTO-'.$mesa->id.'-\d{14}-[a-z0-9]{4}$/', ScrutinyRecord::find($recordId)->record_number);

        // El administrador cierra la elección del barrio.
        $admin = $this->makeUser(['elections.update', 'scrutiny_records.view', 'scrutiny_records.approve']);
        $this->actingAs($admin)->postJson("/api/admin/neighborhoods/{$barrio->id}/elections/close")->assertOk();

        // Ni corregir el acta pendiente ni cargar otra.
        $enviar(['scrutiny_record_id' => $recordId])->assertUnprocessable()->assertJsonValidationErrors('polling_table_id');
        $enviar(['polling_table_id' => $mesa->id])->assertUnprocessable()->assertJsonValidationErrors('polling_table_id');
        $this->assertSame(1, ScrutinyRecord::count());

        // Revisar lo que ya se había enviado sí se puede.
        $this->actingAs($admin)->postJson("/api/admin/audit-records/{$recordId}/decision", ['decision' => 'approved'])->assertOk();
    }

    #[Test]
    public function un_empate_por_la_ultima_curul_se_avisa(): void
    {
        $allocate = new ReflectionMethod(NeighborhoodDirectoryController::class, 'allocateSeatsByQuota');
        $controller = app(NeighborhoodDirectoryController::class);

        // 3 cargos, A y B con 50 votos: cuociente 33,3 → una curul cada una y la
        // tercera queda entre las dos con el mismo residuo.
        $tie = $allocate->invoke($controller, [['plancha' => 'Plancha 1', 'votos' => 50], ['plancha' => 'Plancha 2', 'votos' => 50]], 3, 0);
        $this->assertSame(['Plancha 1', 'Plancha 2'], $tie['empate_residuo']);

        // Sin empate no hay aviso.
        $clear = $allocate->invoke($controller, [['plancha' => 'Plancha 1', 'votos' => 60], ['plancha' => 'Plancha 2', 'votos' => 40]], 3, 0);
        $this->assertSame([], $clear['empate_residuo']);

        // Mismos votos pero las curules alcanzan parejo: no hay nada en disputa.
        $even = $allocate->invoke($controller, [['plancha' => 'Plancha 1', 'votos' => 50], ['plancha' => 'Plancha 2', 'votos' => 50]], 4, 0);
        $this->assertSame([], $even['empate_residuo']);
    }
}
