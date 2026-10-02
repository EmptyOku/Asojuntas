<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\ElectionBlock;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyReview;
use App\Models\Slate;
use App\Models\SlateBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Aprobar y rechazar actas desde la auditoría: los votos solo cuentan en los
 * resultados cuando el acta está aprobada, nunca cuando está rechazada.
 */
class ActaDecisionTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private function makeActa(): ScrutinyRecord
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Actas'));
        $table = $this->makePollingTable($election);
        $block = Block::create(['name' => 'Directiva', 'code' => 'DIR', 'is_active' => true]);
        $electionBlock = ElectionBlock::create(['election_id' => $election->id, 'block_id' => $block->id, 'is_active' => true]);

        $record = ScrutinyRecord::create([
            'election_id' => $election->id,
            'polling_table_id' => $table->id,
            'record_number' => 'ACTA-1',
            'status' => 'pending_review',
        ]);

        foreach ([1 => 60, 2 => 40] as $number => $votes) {
            $slate = Slate::create(['election_id' => $election->id, 'code' => 'P'.$number, 'name' => 'Plancha '.$number, 'is_active' => true]);
            $slateBlock = SlateBlock::create(['election_id' => $election->id, 'slate_id' => $slate->id, 'election_block_id' => $electionBlock->id, 'is_active' => true]);

            ScrutinyBlockResult::create([
                'scrutiny_record_id' => $record->id,
                'election_id' => $election->id,
                'election_block_id' => $electionBlock->id,
                'slate_block_id' => $slateBlock->id,
                'votes' => $votes,
                'status' => 'pending',
            ]);
        }

        return $record;
    }

    private function countedVotes(ScrutinyRecord $record): int
    {
        // Mismo criterio que usan los resultados del barrio y el cuociente.
        return (int) ScrutinyBlockResult::where('scrutiny_record_id', $record->id)
            ->whereIn('status', ['approved', 'reviewed'])
            ->sum('votes');
    }

    private function payload(int $plancha1, int $plancha2): array
    {
        return ['blocks' => [['name' => 'Directiva', 'votes' => ['plancha_1' => $plancha1, 'plancha_2' => $plancha2, 'blancos' => 0]]]];
    }

    #[Test]
    public function aprobar_guarda_las_cifras_corregidas_y_los_votos_cuentan(): void
    {
        $record = $this->makeActa();
        $auditor = $this->makeUser(['records.review']);
        $this->assertSame(0, $this->countedVotes($record), 'Un acta pendiente no cuenta.');

        $this->actingAs($auditor)->postJson("/api/admin/audit-records/{$record->id}/decision", [
            'decision' => 'approved',
            'changes_payload' => $this->payload(70, 40),
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $record->fresh()->status);
        $this->assertSame(110, $this->countedVotes($record));
        $this->assertSame(0, ScrutinyBlockResult::where('scrutiny_record_id', $record->id)->where('status', '<>', 'approved')->count());
    }

    #[Test]
    public function aprobar_sin_editar_tambien_cuenta_los_votos(): void
    {
        $record = $this->makeActa();

        $this->actingAs($this->makeUser(['records.review']))
            ->postJson("/api/admin/audit-records/{$record->id}/decision", ['decision' => 'approved'])
            ->assertOk();

        $this->assertSame(100, $this->countedVotes($record));
    }

    #[Test]
    public function rechazar_exige_motivo(): void
    {
        $record = $this->makeActa();

        $this->actingAs($this->makeUser(['records.review']))
            ->postJson("/api/admin/audit-records/{$record->id}/decision", ['decision' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comments');

        $this->assertSame('pending_review', $record->fresh()->status);
    }

    #[Test]
    public function rechazar_un_acta_aprobada_saca_sus_votos_de_los_resultados(): void
    {
        $record = $this->makeActa();
        $auditor = $this->makeUser(['records.review']);

        $this->actingAs($auditor)->postJson("/api/admin/audit-records/{$record->id}/decision", [
            'decision' => 'approved',
        ])->assertOk();
        $this->assertSame(100, $this->countedVotes($record));

        // Antes, al rechazar se enviaban las cifras y quedaban como "reviewed": seguían sumando.
        $this->actingAs($auditor)->postJson("/api/admin/audit-records/{$record->id}/decision", [
            'decision' => 'rejected',
            'comments' => 'El acta está ilegible.',
            'changes_payload' => $this->payload(999, 999),
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame('rejected', $record->fresh()->status);
        $this->assertSame(0, $this->countedVotes($record));
        $this->assertSame(100, (int) ScrutinyBlockResult::where('scrutiny_record_id', $record->id)->sum('votes'), 'Rechazar no altera las cifras.');

        $review = ScrutinyReview::where('scrutiny_record_id', $record->id)->latest('id')->first();
        $this->assertSame('rejected', $review->decision);
        $this->assertSame('El acta está ilegible.', $review->comments);

        $stats = $this->actingAs($auditor)->getJson('/api/admin/audit-records?filter=rejected')->assertOk();
        $this->assertSame(1, $stats->json('data.stats.rejected_count'));
        $this->assertSame(0, $stats->json('data.stats.valid_votes_total'));
        $this->assertSame('rejected', $stats->json('data.records.data.0.status_tag.kind'));
    }
}
