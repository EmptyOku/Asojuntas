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
        $this->assertSame([
            'Oficializó la plancha 1 de Barrio Bitácora (1 candidato)',
            'Aprobó la plancha 1 de Barrio Bitácora (1 candidato)',
            'Registró una plancha en Barrio Bitácora con 1 candidatos',
        ], $this->summaries($auditor, ['group' => 'planchas'])->pluck('summary')->all());
        $this->assertSame($secretary->username, $this->summaries($auditor, ['user' => $secretary->username])->pluck('user.username')->unique()->sole());
    }

    /** Una plancha registrada, aprobada y oficializada, y un ingreso al sistema. */
    private function registerApprovedPlancha(): array
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Bitácora'));
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'candidate_drafts.promote']);

        $batch = $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts', [
            'election_id' => $election->id,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'PRESIDENTE', 'nombre' => 'Ana Prueba', 'identificacion' => '5550001'],
                ['puesto' => 'TESORERO', 'nombre' => 'Luis Segundo', 'identificacion' => '5550002'],
            ]]]],
        ])->assertCreated()->json('data.capture_batch_uuid');
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $batch])->assertOk();
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $batch])->assertOk();

        AuditLog::create(['user_id' => $secretary->id, 'action' => 'login']);

        return [$secretary, $batch];
    }

    #[Test]
    public function solo_lo_importante_resume_cada_plancha_en_un_renglon(): void
    {
        [$secretary] = $this->registerApprovedPlancha();
        $auditor = $this->makeUser(['audit_logs.view']);

        $important = $this->summaries($auditor, ['detail' => 'important'])->pluck('summary');
        $all = $this->summaries($auditor, ['detail' => 'all'])->pluck('summary');

        // Un renglón por plancha, no uno por candidato; sin ingresos ni registros repetidos.
        $this->assertContains('Aprobó la plancha 1 de Barrio Bitácora (2 candidatos)', $important);
        $this->assertContains('Oficializó la plancha 1 de Barrio Bitácora (2 candidatos)', $important);
        $this->assertContains('Registró una plancha en Barrio Bitácora con 2 candidatos', $important);
        foreach (['Aprobó a Ana Prueba en la revisión de planchas', 'Oficializó a Ana Prueba', 'Agregó a Ana Prueba a una plancha en revisión', 'Registró a la persona Ana Prueba', 'Creó el cargo Presidente', 'Inició sesión'] as $noise) {
            $this->assertNotContains($noise, $important);
            $this->assertContains($noise, $all);
        }

        // Una corrección hecha a mano sí es importante.
        $draft = CandidateDraft::where('first_name', 'Ana')->firstOrFail();
        $this->actingAs($secretary);
        $draft->update(['phone' => '3001112233']);
        $this->assertContains('Corrigió los datos de Ana Prueba en una plancha: Celular', $this->summaries($auditor, ['detail' => 'important'])->pluck('summary'));
    }

    #[Test]
    public function la_bitacora_se_exporta_a_csv_con_los_mismos_filtros(): void
    {
        $this->registerApprovedPlancha();

        $this->actingAs($this->makeUser())->get('/api/admin/audit-logs/export')->assertForbidden();

        $auditor = $this->makeUser(['audit_logs.view']);
        $response = $this->actingAs($auditor)->get('/api/admin/audit-logs/export?detail=important')->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('/bitacora-asojuntas-\d{8}-\d{6}\.csv/', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = array_map(fn (string $line) => str_getcsv($line, ';', '"', ''), array_filter(explode("\n", substr($csv, 3))));

        $this->assertSame(['Fecha y hora', 'Usuario', 'Nombre', 'Tipo', 'Qué pasó', 'Sobre qué', 'Detalle', 'Dirección IP'], $rows[0]);
        $sentences = array_column(array_slice($rows, 1), 4);
        $this->assertContains('Aprobó la plancha 1 de Barrio Bitácora (2 candidatos)', $sentences);
        $this->assertNotContains('Inició sesión', $sentences);
        // Mismas filas que la pantalla con ese filtro.
        $this->assertSame($this->summaries($auditor, ['detail' => 'important'])->pluck('summary')->reject(fn ($s) => str_starts_with($s, 'Exportó'))->values()->all(), $sentences);

        // Con "toda la actividad" salen también los ingresos y cada candidato, con su detalle.
        $all = $this->actingAs($auditor)->get('/api/admin/audit-logs/export?detail=all')->streamedContent();
        $this->assertStringContainsString('Inició sesión', $all);
        $this->assertStringContainsString('Revisión: Pendiente → Aprobado', $all);

        // La exportación queda anotada.
        $this->assertContains('Exportó la bitácora a un archivo ('.count($sentences).' registros)', $this->summaries($auditor, ['group' => 'security'])->pluck('summary'));
    }

    #[Test]
    public function las_planchas_ya_aprobadas_reciben_su_renglon_resumen(): void
    {
        $this->registerApprovedPlancha();
        AuditLog::whereIn('action', ['plancha_decision', 'plancha_officialized'])->delete();

        $migration = require database_path('migrations/2026_10_08_120000_backfill_plancha_audit_events.php');
        $migration->up();

        $summaries = $this->summaries($this->makeUser(['audit_logs.view']), ['detail' => 'important'])->pluck('summary');
        $this->assertContains('Aprobó la plancha 1 de Barrio Bitácora (2 candidatos)', $summaries);
        $this->assertContains('Oficializó la plancha 1 de Barrio Bitácora (2 candidatos)', $summaries);

        $migration->down();
        $this->assertSame(0, AuditLog::whereIn('action', ['plancha_decision', 'plancha_officialized'])->count());
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
