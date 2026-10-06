<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CandidateDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * La bitácora se cuenta en frases que una persona entiende, sin los pasos
 * internos del sistema ni campos técnicos.
 */
class AuditLogHumanTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private function summaries($user, array $params = []): \Illuminate\Support\Collection
    {
        return collect($this->actingAs($user)->getJson('/api/admin/audit-logs?'.http_build_query(['per_page' => 100] + $params))
            ->assertOk()->json('data.records.data'));
    }

    #[Test]
    public function cuenta_la_actividad_en_frases_y_oculta_los_pasos_internos(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Bitácora'));
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'candidate_drafts.promote']);
        $auditor = $this->makeUser(['audit_logs.view']);

        $batch = $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts', [
            'election_id' => $election->id,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'PRESIDENTE', 'nombre' => 'Ana Prueba', 'identificacion' => '5550001'],
            ]]]],
        ])->assertCreated()->json('data.capture_batch_uuid');
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $batch])->assertOk();
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $batch])->assertOk();

        $logs = $this->summaries($auditor);
        $sentences = $logs->pluck('summary');

        $this->assertContains('Registró una plancha en Barrio Bitácora con 1 candidatos', $sentences);
        $this->assertContains('Agregó a Ana Prueba a una plancha en revisión', $sentences);
        $this->assertContains('Aprobó a Ana Prueba en la revisión de planchas', $sentences);
        $this->assertContains('Oficializó a Ana Prueba', $sentences);
        $this->assertContains('Registró a la persona Ana Prueba', $sentences);

        // Los pasos internos (bloques de plancha, candidato oficial…) no aparecen por defecto.
        $this->assertSame([], $logs->pluck('auditable_type')->intersect([
            'App\\Models\\SlateBlock', 'App\\Models\\Slate', 'App\\Models\\Candidate', 'App\\Models\\ElectionBlockPosition',
        ])->values()->all());
        $this->assertGreaterThan($logs->count(), $this->summaries($auditor, ['include_internal' => 1])->count());

        // El detalle usa nombres y valores de persona, sin ids ni llaves internas.
        $approval = $logs->firstWhere('summary', 'Aprobó a Ana Prueba en la revisión de planchas');
        $this->assertSame([['field' => 'review_status', 'label' => 'Revisión', 'from' => 'Pendiente', 'to' => 'Aprobado']], $approval['changes']);
        $created = $logs->firstWhere('summary', 'Agregó a Ana Prueba a una plancha en revisión');
        $this->assertSame([], collect($created['changes'])->pluck('field')->filter(fn ($f) => str_ends_with($f, '_id') || str_contains($f, 'uuid'))->values()->all());

        // Los filtros hablan en lenguaje de usuario.
        $this->assertSame(['Registró una plancha en Barrio Bitácora con 1 candidatos'], $this->summaries($auditor, ['group' => 'planchas'])->pluck('summary')->all());
        $this->assertSame($secretary->username, $this->summaries($auditor, ['user' => $secretary->username])->pluck('user.username')->unique()->sole());
    }

    #[Test]
    public function un_cambio_solo_de_control_interno_no_se_anota(): void
    {
        $user = $this->makeUser();
        $before = AuditLog::count();

        // Hora del último ingreso: no es una acción de nadie.
        $this->actingAs($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->assertSame($before, AuditLog::count());

        // Un cambio real sí queda.
        $draft = CandidateDraft::create(['election_id' => $this->makeElection($this->makeNeighborhood('B'))->id, 'first_name' => 'Ana', 'last_name' => 'Uno']);
        $beforeUpdate = AuditLog::count();
        $draft->update(['last_name' => 'Dos']);
        $this->assertSame($beforeUpdate + 1, AuditLog::count());
    }
}
