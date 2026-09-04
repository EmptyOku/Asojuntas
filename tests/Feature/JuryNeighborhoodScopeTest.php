<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Un jurado solo carga actas en la mesa de su barrio, y sin barrio asignado
 * no carga en ninguna: la comprobación debe fallar en cerrado.
 */
class JuryNeighborhoodScopeTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array<string, mixed> */
    private function payload(int $pollingTableId): array
    {
        return [
            'polling_table_id' => $pollingTableId,
            'document_file' => UploadedFile::fake()->image('acta.jpg'),
            'page_number' => 1,
            'normalized_payload' => json_encode(['block_results' => []]),
        ];
    }

    #[Test]
    public function un_jurado_no_puede_cargar_actas_en_la_mesa_de_otro_barrio(): void
    {
        $barrioAjeno = $this->makeNeighborhood('Barrio Ajeno');
        $eleccionAjena = $this->makeElection($barrioAjeno);
        $mesaAjena = $this->makePollingTable($eleccionAjena);

        $barrioPropio = $this->makeNeighborhood('Barrio Propio');
        $jurado = $this->makeUser(['records.upload'], $barrioPropio);

        $this->actingAs($jurado)
            ->postJson('/api/jury/submit', $this->payload($mesaAjena->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('polling_table_id');
    }

    #[Test]
    public function un_jurado_sin_barrio_asignado_no_puede_cargar_actas(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Cualquiera');
        $eleccion = $this->makeElection($barrio);
        $mesa = $this->makePollingTable($eleccion);

        // Sin barrio: antes de la corrección esto pasaba el control y permitía
        // cargar en cualquier mesa del sistema.
        $juradoSinBarrio = $this->makeUser(['records.upload'], null);

        $this->actingAs($juradoSinBarrio)
            ->postJson('/api/jury/submit', $this->payload($mesa->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('polling_table_id');
    }

    #[Test]
    public function un_jurado_si_puede_cargar_en_la_mesa_de_su_barrio(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Propio');
        $eleccion = $this->makeElection($barrio);
        $mesa = $this->makePollingTable($eleccion);
        $jurado = $this->makeUser(['records.upload'], $barrio);

        $this->actingAs($jurado)
            ->postJson('/api/jury/submit', $this->payload($mesa->id))
            ->assertCreated();
    }
}
