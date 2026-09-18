<?php

namespace Tests\Feature;

use App\Models\CandidateDraftFile;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyRecordFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Cubre los dos IDOR de descarga de archivos: un usuario no puede leer el
 * material de escrutinio ni la evidencia de candidatos de otro barrio.
 */
class ScrutinyFileAccessTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeRecordFile(int $userId, int $electionId, int $pollingTableId): ScrutinyRecordFile
    {
        $record = ScrutinyRecord::create([
            'election_id' => $electionId,
            'polling_table_id' => $pollingTableId,
            'created_by_user_id' => $userId,
            'record_number' => 'ACT-'.Str::random(6),
            'status' => 'draft',
        ]);

        Storage::disk('local')->put('actas/acta.jpg', 'contenido-de-prueba');

        return ScrutinyRecordFile::create([
            'scrutiny_record_id' => $record->id,
            'uploaded_by_user_id' => $userId,
            'storage_path' => 'actas/acta.jpg',
            'original_name' => 'acta.jpg',
            'mime_type' => 'image/jpeg',
            'page_number' => 1,
        ]);
    }

    #[Test]
    public function el_autor_del_acta_puede_descargar_su_archivo(): void
    {
        $barrio = $this->makeNeighborhood('Barrio Propio');
        $eleccion = $this->makeElection($barrio);
        $mesa = $this->makePollingTable($eleccion);
        $jurado = $this->makeUser(['records.upload'], $barrio);

        $archivo = $this->makeRecordFile($jurado->id, $eleccion->id, $mesa->id);

        $this->actingAs($jurado)
            ->get("/api/jury/scrutiny-files/{$archivo->id}")
            ->assertOk();
    }

    #[Test]
    public function un_jurado_no_puede_descargar_el_acta_de_otro_usuario(): void
    {
        $barrioA = $this->makeNeighborhood('Barrio A');
        $eleccionA = $this->makeElection($barrioA);
        $mesaA = $this->makePollingTable($eleccionA);
        $autor = $this->makeUser(['records.upload'], $barrioA);

        $barrioB = $this->makeNeighborhood('Barrio B');
        $intruso = $this->makeUser(['records.upload'], $barrioB);

        $archivo = $this->makeRecordFile($autor->id, $eleccionA->id, $mesaA->id);

        $this->actingAs($intruso)
            ->get("/api/jury/scrutiny-files/{$archivo->id}")
            ->assertForbidden();
    }

    #[Test]
    public function un_revisor_si_puede_acceder_a_actas_de_cualquier_barrio(): void
    {
        $barrioA = $this->makeNeighborhood('Barrio A');
        $eleccionA = $this->makeElection($barrioA);
        $mesaA = $this->makePollingTable($eleccionA);
        $autor = $this->makeUser(['records.upload'], $barrioA);

        $barrioB = $this->makeNeighborhood('Barrio B');
        $revisor = $this->makeUser(['records.upload', 'records.review'], $barrioB);

        $archivo = $this->makeRecordFile($autor->id, $eleccionA->id, $mesaA->id);

        $this->actingAs($revisor)
            ->get("/api/jury/scrutiny-files/{$archivo->id}")
            ->assertOk();
    }

    #[Test]
    public function la_evidencia_de_planchas_no_es_visible_desde_otro_barrio(): void
    {
        $barrioA = $this->makeNeighborhood('Barrio A');
        $eleccionA = $this->makeElection($barrioA);
        $secretaria = $this->makeUser(['records.upload'], $barrioA);

        $barrioB = $this->makeNeighborhood('Barrio B');
        $intruso = $this->makeUser(['records.upload'], $barrioB);

        Storage::disk('local')->put('planchas/evidencia.jpg', 'cedula-escaneada');

        $evidencia = CandidateDraftFile::create([
            'capture_batch_uuid' => (string) Str::uuid(),
            'election_id' => $eleccionA->id,
            'uploaded_by_user_id' => $secretaria->id,
            'original_name' => 'evidencia.jpg',
            'storage_path' => 'planchas/evidencia.jpg',
            'mime_type' => 'image/jpeg',
            'hash' => hash('sha256', 'cedula-escaneada'),
            'page_number' => 1,
        ]);

        $this->actingAs($intruso)
            ->get("/api/secretary/planchas/evidence/files/{$evidencia->id}")
            ->assertForbidden();

        $this->actingAs($secretaria)
            ->get("/api/secretary/planchas/evidence/files/{$evidencia->id}")
            ->assertOk();
    }
}
