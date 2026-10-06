<?php

namespace Tests\Feature;

use App\Models\CandidateDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Candidatos ilegibles del OCR: "<Unknown>" se guarda como "<DESCONOCIDO>" y
 * un documento vacío recibe un número provisional único para poder oficializar.
 */
class UnknownCandidateTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    #[Test]
    public function traduce_unknown_y_asigna_documentos_provisionales_unicos(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Ilegible'));
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'candidate_drafts.promote']);

        $batch = $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts', [
            'election_id' => $election->id,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'PRESIDENTE', 'nombre' => 'Ana Prueba', 'identificacion' => '5550001'],
                ['puesto' => 'TESORERO', 'nombre' => '<Unknown>', 'identificacion' => ''],
                ['puesto' => 'COMISION EMPRESARIAL', 'nombre' => '<Unknown> Sin_apellido', 'identificacion' => 'N/A'],
            ]]]],
        ])->assertCreated()->json('data.capture_batch_uuid');

        $drafts = CandidateDraft::where('capture_batch_uuid', $batch)->orderBy('id')->get();
        $unknown = $drafts->where('first_name', '<DESCONOCIDO>')->values();

        $this->assertCount(2, $unknown);
        $this->assertSame(['00000000001', '00000000002'], $unknown->pluck('document_number')->all());
        $this->assertSame('5550001', $drafts->first()->document_number, 'Un documento real no se toca.');

        // Corregir el lote conserva el mismo documento provisional (no genera otro).
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts', [
            'capture_batch_uuid' => $batch,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'TESORERO', 'nombre' => '<Unknown>', 'identificacion' => ''],
            ]]]],
        ])->assertCreated();
        $this->assertSame('00000000001', CandidateDraft::find($unknown[0]->id)->document_number);

        // Y ya se puede oficializar sin omitir a nadie.
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', [
            'decision' => 'approved', 'capture_batch_uuid' => $batch,
        ])->assertOk();
        $promotion = $this->actingAs($secretary)
            ->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $batch])
            ->assertOk();
        $this->assertSame(0, $promotion->json('data.skipped'));
        $this->assertSame(3, $promotion->json('data.processed'));

        // Planchas oficiales: "Plancha 1" (no "Plancha P1") y el documento provisional marcado.
        $official = $this->actingAs($this->makeUser(['slates.view', 'candidates.view']))
            ->getJson('/api/admin/planchas/by-neighborhood')->assertOk()->json('data.items.0.slates.0');
        $this->assertSame('Plancha 1', $official['label']);
        $reps = collect($official['representatives']);
        $this->assertCount(3, $reps);
        $this->assertSame(2, $reps->where('document_is_placeholder', true)->count());
        $this->assertTrue($reps->every(fn ($rep) => array_key_exists('is_substitute', $rep) && array_key_exists('block', $rep)));
    }
}
