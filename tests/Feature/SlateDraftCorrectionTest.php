<?php

namespace Tests\Feature;

use App\Models\CandidateDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Corregir una plancha ya capturada: el detalle debe cargar el lote completo
 * y "Guardar" debe actualizar los mismos borradores, en la misma elección.
 */
class SlateDraftCorrectionTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private function capture($user, int $electionId, array $cargos): string
    {
        return $this->actingAs($user)->postJson('/api/secretary/planchas/drafts', [
            'election_id' => $electionId,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => $cargos]]],
        ])->assertCreated()->json('data.capture_batch_uuid');
    }

    #[Test]
    public function el_detalle_carga_el_lote_completo_con_total(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Detalle'));
        $secretary = $this->makeUser(['slates.capture', 'slates.review']);

        $cargos = collect(range(1, 3))->flatMap(fn ($n) => [
            ['puesto' => "DELEGADO ASOJUNTAS {$n}", 'nombre' => "Delegado {$n}", 'identificacion' => "10{$n}0"],
            ['puesto' => "SUPLENTE DELEGADO ASOJUNTAS {$n}", 'nombre' => "Suplente {$n}", 'identificacion' => "20{$n}0"],
        ])->all();
        $batch = $this->capture($secretary, $election->id, $cargos);

        // La pantalla de detalle pide per_page=100: antes era un 422 (máximo 20).
        $response = $this->actingAs($secretary)
            ->getJson("/api/secretary/planchas/drafts?capture_batch_uuid={$batch}&per_page=100")
            ->assertOk();

        $this->assertCount(6, $response->json('data.data'));
        $this->assertSame(6, $response->json('data.total'));
    }

    #[Test]
    public function corregir_un_borrador_lo_actualiza_sin_duplicar_ni_cambiar_de_eleccion(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Original'));
        // Otra elección activa más reciente: antes, sin election_id, se guardaba ahí.
        $this->makeElection($this->makeNeighborhood('Barrio Ajeno'));
        $secretary = $this->makeUser(['slates.capture', 'slates.review']);

        $batch = $this->capture($secretary, $election->id, [
            ['puesto' => 'PRESIDENTE', 'nombre' => 'Ana Prueba', 'identificacion' => '5550001'],
            ['puesto' => 'COMISION EMPRESARIAL', 'nombre' => '<Unknown> Sin_apellido', 'identificacion' => ''],
        ]);

        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', [
            'decision' => 'approved', 'capture_batch_uuid' => $batch,
        ])->assertOk();

        // Corrección desde el detalle: mismo lote, sin election_id, nombre y documento nuevos.
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts', [
            'capture_batch_uuid' => $batch,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'PRESIDENTE', 'nombre' => 'Ana Prueba', 'identificacion' => '5550001'],
                ['puesto' => 'COMISION EMPRESARIAL', 'nombre' => 'Carlos Corregido', 'identificacion' => '5550099'],
            ]]]],
        ])->assertCreated();

        $drafts = CandidateDraft::where('capture_batch_uuid', $batch)->get();
        $this->assertCount(2, $drafts, 'No debe crear borradores duplicados.');
        $this->assertSame([$election->id], $drafts->pluck('election_id')->unique()->values()->all());

        $corregido = $drafts->firstWhere('document_number', '5550099');
        $this->assertNotNull($corregido);
        $this->assertSame('CARLOS', $corregido->first_name); // los nombres se guardan en mayúsculas
        $this->assertSame('approved', $corregido->review_status, 'Conserva la aprobación.');

        // Ya con documento, la oficialización del lote no omite a nadie.
        $promotion = $this->actingAs($this->makeUser(['slates.promote', 'slates.review']))
            ->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $batch])
            ->assertOk();
        $this->assertSame(0, $promotion->json('data.skipped'));
        $this->assertSame(2, $promotion->json('data.processed'));
    }
}
