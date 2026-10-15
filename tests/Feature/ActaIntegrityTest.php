<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Election;
use App\Models\ElectionBlock;
use App\Models\Neighborhood;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyRecord;
use App\Models\Slate;
use App\Models\SlateBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Los votos que cuentan no se pueden alterar por fuera de la revisión: un acta
 * aprobada no se reescribe, una mesa solo tiene un acta que cuenta, no se
 * aprueba un acta sin votos y todo cambio de cifras queda en la bitácora.
 */
class ActaIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private Neighborhood $neighborhood;

    private Election $election;

    private User $jury;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->neighborhood = $this->makeNeighborhood('Barrio Íntegro');
        $this->election = $this->makeElection($this->neighborhood);
        $this->makePollingTable($this->election);

        $block = Block::create(['name' => 'Directiva', 'code' => 'DIR', 'is_active' => true]);
        $electionBlock = ElectionBlock::create(['election_id' => $this->election->id, 'block_id' => $block->id, 'is_active' => true]);
        foreach ([1, 2] as $number) {
            $slate = Slate::create(['election_id' => $this->election->id, 'code' => 'P'.$number, 'name' => 'Plancha '.$number, 'is_active' => true]);
            SlateBlock::create(['election_id' => $this->election->id, 'slate_id' => $slate->id, 'election_block_id' => $electionBlock->id, 'is_active' => true]);
        }

        $this->jury = $this->makeUser(['scrutiny_records.create', 'elections.view', 'polling_tables.view'], $this->neighborhood);
        $this->auditor = $this->makeUser(['scrutiny_records.view', 'scrutiny_records.approve']);
    }

    private function submit(int $plancha1, int $plancha2, array $extra = []): TestResponse
    {
        // Entre un envío y otro pasa al menos un segundo (el número del acta lleva la hora).
        $this->travel(2)->seconds();

        return $this->actingAs($this->jury)->post('/api/jury/submit', $extra + [
            'document_file' => UploadedFile::fake()->create('acta-'.uniqid().'.jpg', random_int(5, 500), 'image/jpeg'),
            'page_number' => 1,
            'normalized_payload' => json_encode(['block_votes' => [['block_name' => 'Directiva', 'plancha_1' => $plancha1, 'plancha_2' => $plancha2]]]),
        ], ['Accept' => 'application/json']);
    }

    private function decide(int $recordId, string $decision, array $extra = []): TestResponse
    {
        return $this->actingAs($this->auditor)->postJson("/api/admin/audit-records/{$recordId}/decision", ['decision' => $decision] + $extra);
    }

    /** Votos que cuentan en los resultados del barrio, por plancha. */
    private function counted(): array
    {
        return ScrutinyBlockResult::query()
            ->join('slate_blocks', 'slate_blocks.id', '=', 'scrutiny_block_results.slate_block_id')
            ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
            ->where('scrutiny_block_results.election_id', $this->election->id)
            ->whereIn('scrutiny_block_results.status', ['approved', 'reviewed'])
            ->whereIn('slates.code', ['P1', 'P2'])
            ->groupBy('slates.code')
            ->orderBy('slates.code')
            ->selectRaw('slates.code as code, SUM(scrutiny_block_results.votes) as votes')
            ->pluck('votes', 'code')
            ->map(fn ($votes) => (int) $votes)
            ->all();
    }

    #[Test]
    public function el_jurado_no_puede_cambiar_un_acta_ya_aprobada(): void
    {
        $recordId = $this->submit(60, 40)->assertCreated()->json('data.scrutiny_record_id');

        // Mientras está pendiente sí puede corregirla.
        $this->submit(61, 39, ['scrutiny_record_id' => $recordId])->assertCreated();

        $this->decide($recordId, 'approved')->assertOk();
        $this->assertSame(['P1' => 61, 'P2' => 39], $this->counted());

        $this->submit(5, 95, ['scrutiny_record_id' => $recordId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scrutiny_record_id');

        $this->assertSame(['P1' => 61, 'P2' => 39], $this->counted());
        $this->assertSame('approved', ScrutinyRecord::find($recordId)->status);
    }

    #[Test]
    public function una_mesa_solo_tiene_un_acta_que_cuenta(): void
    {
        $firstId = $this->submit(60, 40)->assertCreated()->json('data.scrutiny_record_id');
        $this->decide($firstId, 'approved')->assertOk();

        // Con un acta aprobada, el jurado no puede cargar otra.
        $this->submit(30, 30)->assertUnprocessable()->assertJsonValidationErrors('polling_table_id');
        $this->assertSame(1, ScrutinyRecord::count());

        // Si existiera una segunda acta (datos anteriores a esta regla), no se puede aprobar.
        $second = ScrutinyRecord::create([
            'election_id' => $this->election->id,
            'polling_table_id' => ScrutinyRecord::find($firstId)->polling_table_id,
            'record_number' => 'SEGUNDA',
            'status' => 'pending',
        ]);
        ScrutinyBlockResult::where('scrutiny_record_id', $firstId)->get()->each(
            fn (ScrutinyBlockResult $result) => $result->replicate()->fill(['scrutiny_record_id' => $second->id, 'status' => 'pending'])->save()
        );

        $this->decide($second->id, 'approved')->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame(['P1' => 60, 'P2' => 40], $this->counted());

        // Rechazada la primera, la segunda sí puede aprobarse: sigue contando una sola.
        $this->decide($firstId, 'rejected', ['comments' => 'La correcta es la otra.'])->assertOk();
        $this->decide($second->id, 'approved')->assertOk();
        $this->assertSame(['P1' => 60, 'P2' => 40], $this->counted());
    }

    #[Test]
    public function tras_un_rechazo_el_jurado_carga_un_acta_nueva(): void
    {
        $firstId = $this->submit(60, 40)->assertCreated()->json('data.scrutiny_record_id');
        $this->decide($firstId, 'rejected', ['comments' => 'La foto está borrosa.'])->assertOk();

        // La pantalla aún recuerda el acta rechazada: el envío crea una nueva, no reabre la anterior.
        $secondId = $this->submit(55, 45, ['scrutiny_record_id' => $firstId])->assertCreated()->json('data.scrutiny_record_id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame('rejected', ScrutinyRecord::find($firstId)->status);
        $this->assertSame([], $this->counted());

        $this->decide($secondId, 'approved')->assertOk();
        $this->assertSame(['P1' => 55, 'P2' => 45], $this->counted());
    }

    #[Test]
    public function no_se_decide_un_borrador_ni_se_aprueba_un_acta_sin_votos(): void
    {
        $tableId = $this->election->pollingTables()->value('id');

        $draft = ScrutinyRecord::create(['election_id' => $this->election->id, 'polling_table_id' => $tableId, 'record_number' => 'BORRADOR', 'status' => 'draft']);
        $this->decide($draft->id, 'approved')->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->decide($draft->id, 'rejected', ['comments' => 'No aplica.'])->assertUnprocessable();
        $this->assertSame('draft', $draft->fresh()->status);

        $empty = ScrutinyRecord::create(['election_id' => $this->election->id, 'polling_table_id' => $tableId, 'record_number' => 'VACIA', 'status' => 'pending']);
        $this->decide($empty->id, 'approved')->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame('pending', $empty->fresh()->status);
        $this->assertFalse(ScrutinyRecord::electionHasApprovedActa($this->election->id));

        // Rechazar un acta enviada sin votos sí se puede.
        $this->decide($empty->id, 'rejected', ['comments' => 'Llegó sin cifras.'])->assertOk();
    }

    #[Test]
    public function cambiar_una_cifra_ya_cargada_queda_en_la_bitacora(): void
    {
        $recordId = $this->submit(60, 40)->assertCreated()->json('data.scrutiny_record_id');
        $this->decide($recordId, 'approved')->assertOk();
        $this->assertSame(0, AuditLog::where('auditable_type', ScrutinyBlockResult::class)->count(), 'Cargar los votos no deja una fila por cifra.');

        // Edición directa desde el Panel.
        $admin = $this->makeUser(['scrutiny_block_results.view', 'scrutiny_block_results.update', 'audit_logs.view']);
        $result = ScrutinyBlockResult::where('scrutiny_record_id', $recordId)->where('votes', 60)->firstOrFail();
        $this->actingAs($admin)->putJson("/api/admin/panel/scrutiny_block_results/{$result->id}", ['votes' => 75])->assertOk();

        $log = AuditLog::where('auditable_type', ScrutinyBlockResult::class)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(60, (int) $log->old_values['votes']);
        $this->assertSame(75, (int) $log->new_values['votes']);

        $summaries = collect($this->actingAs($admin)->getJson('/api/admin/audit-logs?detail=important&per_page=100')->assertOk()->json('data.records.data'))->pluck('summary');
        $this->assertContains("Cambió votos del acta n.º {$recordId}: de 60 a 75", $summaries);
    }

    #[Test]
    public function una_cuenta_desactivada_pierde_la_sesion_abierta(): void
    {
        $this->actingAs($this->jury)->getJson('/api/jury/context')->assertOk();

        $this->jury->forceFill(['is_active' => false])->save();

        $this->getJson('/api/jury/context')->assertUnauthorized()->assertJsonPath('message', 'Tu cuenta fue desactivada. Comunícate con un administrador.');
        $this->assertGuest();

        // Al reactivarla no recupera la sesión anterior: tiene que volver a entrar.
        $this->jury->forceFill(['is_active' => true])->save();
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
