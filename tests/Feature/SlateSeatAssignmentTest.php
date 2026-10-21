<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Admin\NeighborhoodDirectoryController;
use App\Models\Block;
use App\Models\Candidate;
use App\Models\CandidateDraft;
use App\Models\DocumentType;
use App\Models\Election;
use App\Models\ElectionBlock;
use App\Models\ElectionBlockPosition;
use App\Models\Person;
use App\Models\Position;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyRecord;
use App\Models\Slate;
use App\Models\SlateBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Asignación de dignatarios por cuociente electoral y registro de planchas.
 *
 * Fija los errores que hacían que no se mostraran bien presidente y
 * vicepresidente: cargos repetidos entre planchas, suplentes contados como
 * principales, planchas fusionadas en "Plancha 1" y curules para planchas
 * sin votos.
 */
class SlateSeatAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private int $personSequence = 0;

    /** @return array{0: ElectionBlock, 1: array<string, ElectionBlockPosition>} */
    private function makeDirectiva(Election $election): array
    {
        $block = Block::create(['name' => 'Directiva', 'code' => 'DIR', 'is_active' => true]);
        $electionBlock = ElectionBlock::create(['election_id' => $election->id, 'block_id' => $block->id, 'is_active' => true]);

        $positions = [];
        foreach ([['DIR_PRES', 'Presidente', 1], ['DIR_VICE', 'Vicepresidente', 2], ['DIR_TESO', 'Tesorero', 3], ['DIR_SECR', 'Secretario', 4]] as [$code, $name, $order]) {
            $position = Position::create(['block_id' => $block->id, 'code' => $code, 'name' => $name, 'order_number' => $order, 'is_active' => true]);
            $positions[$code] = ElectionBlockPosition::create([
                'election_block_id' => $electionBlock->id,
                'block_id' => $block->id,
                'position_id' => $position->id,
                'vacancies' => 1,
                'is_active' => true,
            ]);
        }

        return [$electionBlock, $positions];
    }

    private function makeSlateBlock(Election $election, ElectionBlock $electionBlock, int $number): SlateBlock
    {
        $slate = Slate::create(['election_id' => $election->id, 'code' => 'P'.$number, 'name' => 'Plancha '.$number, 'is_active' => true]);

        return SlateBlock::create(['election_id' => $election->id, 'slate_id' => $slate->id, 'election_block_id' => $electionBlock->id, 'is_active' => true]);
    }

    private function makeCandidate(Election $election, SlateBlock $slateBlock, ElectionBlockPosition $ebp, string $name, bool $substitute = false): void
    {
        $documentType = DocumentType::firstOrCreate(['code' => 'CC'], ['name' => 'Cédula de ciudadanía']);
        $person = Person::create([
            'document_type_id' => $documentType->id,
            'document_number' => '900'.(++$this->personSequence),
            'first_name' => $name,
            'last_name' => 'Prueba',
        ]);

        Candidate::create([
            'election_id' => $election->id,
            'person_id' => $person->id,
            'slate_block_id' => $slateBlock->id,
            'election_block_position_id' => $ebp->id,
            'is_substitute' => $substitute,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function cada_cargo_se_provee_una_sola_vez_y_la_plancha_mayoritaria_obtiene_la_presidencia(): void
    {
        $neighborhood = $this->makeNeighborhood('Barrio Cuociente');
        $election = $this->makeElection($neighborhood);
        $table = $this->makePollingTable($election);
        [$directiva, $cargos] = $this->makeDirectiva($election);

        $plancha1 = $this->makeSlateBlock($election, $directiva, 1);
        $plancha2 = $this->makeSlateBlock($election, $directiva, 2);

        foreach (['DIR_PRES' => 'Ana', 'DIR_VICE' => 'Beto', 'DIR_TESO' => 'Carla', 'DIR_SECR' => 'Dario'] as $code => $name) {
            $this->makeCandidate($election, $plancha1, $cargos[$code], $name);
        }
        // Suplente de presidente inscrito ANTES que otros: no debe tomar la presidencia.
        $this->makeCandidate($election, $plancha1, $cargos['DIR_PRES'], 'Suplenteana', substitute: true);

        foreach (['DIR_PRES' => 'Elena', 'DIR_VICE' => 'Fabio', 'DIR_TESO' => 'Gina', 'DIR_SECR' => 'Hugo'] as $code => $name) {
            $this->makeCandidate($election, $plancha2, $cargos[$code], $name);
        }

        $record = ScrutinyRecord::create([
            'election_id' => $election->id,
            'polling_table_id' => $table->id,
            'record_number' => 'ACTA-1',
            'status' => 'approved',
        ]);

        // 100 votos, 4 cargos → cuociente 25. P1: 60 (2 enteros + residuo 10),
        // P2: 40 (1 entero + residuo 15) → la curul sobrante va a P2: 2 y 2.
        foreach ([[$plancha1, 60], [$plancha2, 40]] as [$slateBlock, $votes]) {
            ScrutinyBlockResult::create([
                'scrutiny_record_id' => $record->id,
                'election_id' => $election->id,
                'election_block_id' => $directiva->id,
                'slate_block_id' => $slateBlock->id,
                'votes' => $votes,
                'status' => 'approved',
            ]);
        }

        $admin = $this->makeUser(['candidates.view']);
        $response = $this->actingAs($admin)
            ->getJson("/api/admin/neighborhoods/{$neighborhood->id}")
            ->assertOk();

        $bloque = collect($response->json('data.resultados'))->firstWhere('codigo_bloque', 'DIR');
        $asignados = collect($bloque['cargos'])->mapWithKeys(fn ($c) => [$c['cargo'] => $c]);

        // Los cuatro cargos, sin repetir ninguno.
        $this->assertSame(['Presidente', 'Vicepresidente', 'Tesorero', 'Secretario'], collect($bloque['cargos'])->pluck('cargo')->all());

        // La plancha con más votos provee presidente y vicepresidente con sus principales.
        $this->assertSame('Ana Prueba', $asignados['Presidente']['persona']['nombre']);
        $this->assertSame('Plancha 1', $asignados['Presidente']['plancha']);
        $this->assertSame('Suplenteana Prueba', $asignados['Presidente']['suplente']);
        $this->assertSame('Beto Prueba', $asignados['Vicepresidente']['persona']['nombre']);

        // La segunda plancha continúa con los cargos siguientes, no repite presidente.
        $this->assertSame('Gina Prueba', $asignados['Tesorero']['persona']['nombre']);
        $this->assertSame('Plancha 2', $asignados['Tesorero']['plancha']);
        $this->assertSame('Hugo Prueba', $asignados['Secretario']['persona']['nombre']);
    }

    #[Test]
    public function antes_del_acta_se_ven_los_candidatos_y_despues_el_presidente_electo(): void
    {
        $neighborhood = $this->makeNeighborhood('Barrio Directorio');
        $election = $this->makeElection($neighborhood);
        [$directiva, $cargos] = $this->makeDirectiva($election);
        $plancha1 = $this->makeSlateBlock($election, $directiva, 1);
        $plancha2 = $this->makeSlateBlock($election, $directiva, 2);

        // El suplente no es el candidato a presidente.
        $this->makeCandidate($election, $plancha1, $cargos['DIR_PRES'], 'Suplente', substitute: true);
        $this->makeCandidate($election, $plancha1, $cargos['DIR_PRES'], 'Principal');
        $this->makeCandidate($election, $plancha1, $cargos['DIR_VICE'], 'Vice');
        $this->makeCandidate($election, $plancha2, $cargos['DIR_PRES'], 'Rival');

        $admin = $this->makeUser(['candidates.view']);
        $row = fn () => collect($this->actingAs($admin)->getJson('/api/admin/neighborhoods')->assertOk()->json('data.neighborhoods'))
            ->firstWhere('id', $neighborhood->id);

        // Sin acta aprobada nadie es "presidente": se muestran los candidatos de cada plancha.
        $before = $row();
        $this->assertSame('en_curso', $before['election_status']);
        $this->assertNull($before['president_name']);
        $this->assertFalse($before['president_elected']);
        $this->assertSame([
            ['numero' => 1, 'plancha' => 'Plancha 1', 'presidente' => 'Principal Prueba', 'vicepresidente' => 'Vice Prueba'],
            ['numero' => 2, 'plancha' => 'Plancha 2', 'presidente' => 'Rival Prueba', 'vicepresidente' => null],
        ], $before['slate_candidates']);

        // Un acta pendiente tampoco elige a nadie.
        $record = ScrutinyRecord::create(['election_id' => $election->id, 'record_number' => 'ACTA-D', 'status' => 'pending']);
        $results = [];
        foreach ([[$plancha1, 30], [$plancha2, 70]] as [$slateBlock, $votes]) {
            $results[] = ScrutinyBlockResult::create([
                'scrutiny_record_id' => $record->id, 'election_id' => $election->id,
                'election_block_id' => $directiva->id, 'slate_block_id' => $slateBlock->id,
                'votes' => $votes, 'status' => 'pending',
            ]);
        }
        $this->assertFalse($row()['president_elected']);
        $this->assertSame('en_curso', $row()['election_status']);

        // Aprobada: el presidente de la plancha ganadora queda como electo.
        $record->update(['status' => 'approved']);
        foreach ($results as $result) {
            $result->update(['status' => 'approved']);
        }
        $after = $row();
        $this->assertTrue($after['president_elected']);
        $this->assertSame('con_resultados', $after['election_status']);
        $this->assertSame('Rival Prueba', $after['president_name']);
        $this->assertSame('Plancha 2', $after['winning_slate']);

        // Si gana una plancha que no inscribió presidente, se sabe cuál ganó.
        Candidate::where('election_id', $election->id)->where('slate_block_id', $plancha2->id)->delete();
        $empty = $row();
        $this->assertTrue($empty['president_elected']);
        $this->assertNull($empty['president_name']);
        $this->assertSame('Plancha 2', $empty['winning_slate']);

        // Los resultados se quedan aunque la elección se cierre.
        $election->update(['is_active' => false]);
        $this->assertSame('con_resultados', $row()['election_status']);

        // Una elección nueva deja la junta en blanco hasta que termine su proceso.
        $this->makeElection($neighborhood);
        $fresh = $row();
        $this->assertSame('en_curso', $fresh['election_status']);
        $this->assertFalse($fresh['president_elected']);
        $this->assertSame([], $fresh['slate_candidates']);

        // Un barrio cuya elección se cerró sin acta aprobada, y uno sin elección.
        $closed = $this->makeElection($otro = $this->makeNeighborhood('Barrio Cerrado'));
        $closed->update(['is_active' => false]);
        $none = $this->makeNeighborhood('Barrio Sin Eleccion');
        $rows = collect($this->actingAs($admin)->getJson('/api/admin/neighborhoods?per_page=50')->assertOk()->json('data.neighborhoods'))->keyBy('id');
        $this->assertSame('cerrada_sin_resultados', $rows[$otro->id]['election_status']);
        $this->assertSame('sin_eleccion', $rows[$none->id]['election_status']);
    }

    #[Test]
    public function una_plancha_sin_votos_nunca_recibe_curul(): void
    {
        $allocate = new ReflectionMethod(NeighborhoodDirectoryController::class, 'allocateSeatsByQuota');

        // 30 votos válidos (10 de A + 20 en blanco), 3 cargos → cuociente 10:
        // A gana 1 curul entera y quedan 2 por residuo. Antes rotaban hacia B y C.
        $result = $allocate->invoke(app(NeighborhoodDirectoryController::class), [
            ['plancha' => 'A', 'votos' => 10],
            ['plancha' => 'B', 'votos' => 0],
            ['plancha' => 'C', 'votos' => 0],
        ], 3, 20);

        $curules = collect($result['planchas'])->pluck('curules', 'plancha');
        $this->assertSame(3, $curules['A']);
        $this->assertSame(0, $curules['B']);
        $this->assertSame(0, $curules['C']);
    }

    #[Test]
    public function registrar_dos_planchas_crea_dos_planchas_y_marca_los_suplentes(): void
    {
        $neighborhood = $this->makeNeighborhood('Barrio Planchas');
        $election = $this->makeElection($neighborhood);
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve']);

        $lotes = [];
        foreach (['1', '2'] as $n) {
            $response = $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts', [
                'election_id' => $election->id,
                'review_page_data' => ['bloques' => [[
                    'titulo' => 'Directiva',
                    'cargos' => [
                        ['puesto' => 'PRESIDENTE', 'nombre' => "Presidente Plancha{$n}", 'identificacion' => "11{$n}0001"],
                        ['puesto' => 'SUPLENTE DE PRESIDENTE', 'nombre' => "Suplente Plancha{$n}", 'identificacion' => "11{$n}0002"],
                    ],
                ]]],
            ])->assertCreated();

            $lotes[] = $response->json('data.capture_batch_uuid');
        }

        $this->assertSame(2, CandidateDraft::where('election_id', $election->id)->where('is_substitute', true)->count());
        $this->assertSame(2, CandidateDraft::where('election_id', $election->id)->where('is_substitute', false)->count());

        foreach ($lotes as $lote) {
            $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', [
                'decision' => 'approved',
                'capture_batch_uuid' => $lote,
            ])->assertOk();
        }

        // Cada lote aprobado queda en su propia plancha (antes, los dos en "Plancha 1").
        $slatesPorLote = collect($lotes)->map(
            fn ($lote) => CandidateDraft::where('capture_batch_uuid', $lote)->distinct()->pluck('slate_id')->all()
        );

        $this->assertCount(1, $slatesPorLote[0]);
        $this->assertCount(1, $slatesPorLote[1]);
        $this->assertNotEquals($slatesPorLote[0], $slatesPorLote[1]);
        $this->assertSame(2, Slate::where('election_id', $election->id)->count());
    }
}
