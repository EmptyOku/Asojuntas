<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Block;
use App\Models\CandidateDraft;
use App\Models\Election;
use App\Models\ElectionBlock;
use App\Models\Slate;
use App\Models\SlateBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Donde se ven los resultados se ven también las horas clave: cuándo se
 * oficializó cada plancha, cuándo subió el jurado el acta y cuándo se cerró
 * la elección, en hora de Colombia.
 */
class ElectionTimelineTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    #[Test]
    public function los_resultados_muestran_las_horas_de_plancha_acta_y_cierre(): void
    {
        Storage::fake('local');
        $neighborhood = $this->makeNeighborhood('Barrio Horas');
        $election = $this->makeElection($neighborhood);
        $this->makePollingTable($election);
        $block = Block::create(['name' => 'Directiva', 'code' => 'DIR', 'is_active' => true]);
        $electionBlock = ElectionBlock::create(['election_id' => $election->id, 'block_id' => $block->id, 'is_active' => true]);
        $slate = Slate::create(['election_id' => $election->id, 'code' => 'P1', 'name' => 'Plancha 1', 'is_active' => true]);
        SlateBlock::create(['election_id' => $election->id, 'slate_id' => $slate->id, 'election_block_id' => $electionBlock->id, 'is_active' => true]);

        // 15 de octubre, 8:00 p. m. UTC = 3:00 p. m. en Colombia: se oficializa la plancha.
        $this->travelTo(Carbon::parse('2026-10-15 20:00:00', 'UTC'));
        CandidateDraft::create([
            'election_id' => $election->id, 'slate_id' => $slate->id, 'first_name' => 'Ana', 'last_name' => 'Prueba',
            'review_status' => 'approved', 'is_processed' => true, 'processed_at' => now(),
        ]);

        // 3:30 p. m.: el jurado sube el acta.
        $this->travelTo(Carbon::parse('2026-10-15 20:30:00', 'UTC'));
        $jury = $this->makeUser(['scrutiny_records.create', 'elections.view', 'polling_tables.view'], $neighborhood);
        $recordId = $this->actingAs($jury)->post('/api/jury/submit', [
            'document_file' => UploadedFile::fake()->create('acta.jpg', 40, 'image/jpeg'),
            'page_number' => 1,
            'normalized_payload' => json_encode(['block_votes' => [['block_name' => 'Directiva', 'plancha_1' => 10]]]),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.scrutiny_record_id');

        // 4:00 p. m.: se aprueba; 4:15 p. m.: se cierra la elección.
        $admin = $this->makeUser(['scrutiny_records.view', 'scrutiny_records.approve', 'elections.update', 'candidates.view', 'neighborhoods.view']);
        $this->travelTo(Carbon::parse('2026-10-15 21:00:00', 'UTC'));
        $this->actingAs($admin)->postJson("/api/admin/audit-records/{$recordId}/decision", ['decision' => 'approved'])->assertOk();
        $this->travelTo(Carbon::parse('2026-10-15 21:15:00', 'UTC'));
        $this->actingAs($admin)->postJson("/api/admin/neighborhoods/{$neighborhood->id}/elections/close")->assertOk();
        $this->assertSame('2026-10-15 21:15:00', $election->fresh()->closed_at->format('Y-m-d H:i:s'));

        // Los resultados siguen visibles con la elección cerrada, con sus horas.
        $data = $this->actingAs($admin)->getJson("/api/admin/neighborhoods/{$neighborhood->id}")->assertOk()->json('data');
        $this->assertFalse($data['eleccion_abierta']);
        $timeline = $data['linea_de_tiempo'];
        $this->assertSame('Plancha 1', $timeline['planchas'][0]['nombre']);
        $this->assertSame('15 de octubre de 2026, 3:00 p. m.', $timeline['planchas'][0]['oficializada']['texto']);
        $this->assertSame('15 de octubre de 2026, 3:30 p. m.', $timeline['acta']['subida']['texto']);
        $this->assertSame('15 de octubre de 2026, 4:00 p. m.', $timeline['acta']['decidida']['texto']);
        $this->assertSame('15 de octubre de 2026, 4:15 p. m.', $timeline['cierre']['texto']);
        $this->assertSame(trim($jury->person->first_name.' '.$jury->person->last_name), $timeline['acta']['subida_por']);

        // La bandeja de actas y el detalle dicen cuándo se subió y cuándo se decidió.
        $row = $this->actingAs($admin)->getJson('/api/admin/audit-records')->assertOk()->json('data.records.data.0');
        $this->assertSame('15 de octubre de 2026, 3:30 p. m.', $row['uploaded_at']['texto']);
        $this->assertSame('15 de octubre de 2026, 4:00 p. m.', $row['decided_at']['texto']);
        $detail = $this->actingAs($admin)->getJson("/api/admin/audit-records/{$recordId}")->assertOk()->json('data');
        $this->assertSame('15 de octubre de 2026, 3:30 p. m.', $detail['uploaded_at']['texto']);
        $this->assertSame('15 de octubre de 2026, 4:15 p. m.', $detail['timeline']['cierre']['texto']);

        // Geografía y el directorio saben cuándo se cerró la última elección del barrio.
        $row = collect($this->actingAs($admin)->getJson('/api/admin/neighborhoods')->assertOk()->json('data.neighborhoods'))->firstWhere('id', $neighborhood->id);
        $this->assertFalse($row['has_active_election']);
        $this->assertSame('15 de octubre de 2026, 4:15 p. m.', $row['last_closed_at']['texto']);
    }

    #[Test]
    public function reabrir_una_eleccion_borra_su_hora_de_cierre(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Reabre'));

        $election->update(['is_active' => false]);
        $this->assertNotNull($election->fresh()->closed_at);

        $election->update(['is_active' => true]);
        $this->assertNull($election->fresh()->closed_at);

        // El cierre masivo también anota la hora.
        $admin = $this->makeUser(['elections.update']);
        $this->actingAs($admin)->postJson('/api/admin/neighborhoods/elections/close-all')->assertOk();
        $this->assertNotNull($election->fresh()->closed_at);
        $this->assertSame(0, Election::whereNull('closed_at')->where('is_active', false)->count());
    }

    #[Test]
    public function la_bitacora_muestra_la_hora_de_colombia(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 20:00:00', 'UTC'));
        $auditor = $this->makeUser(['audit_logs.view']);
        AuditLog::create(['user_id' => $auditor->id, 'action' => 'login']);

        $log = collect($this->actingAs($auditor)->getJson('/api/admin/audit-logs?detail=all')->assertOk()->json('data.records.data'))
            ->firstWhere('action', 'login');

        // Antes salía "8:00 p. m.", la hora UTC del servidor.
        $this->assertSame('15 de octubre de 2026, 3:00 p. m.', $log['created_at_exact']);
        $this->assertSame('2026-10-15 15:00:00', $log['created_at']);
    }
}
