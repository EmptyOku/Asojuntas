<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Completa la fase posterior a la captura con datos de demostracion.
 *
 * Parte de los votos ya sembrados en scrutiny_block_results y deriva,
 * de forma coherente, las tablas que quedaban vacias:
 * revisiones, consolidacion, asignacion de curules, electos,
 * archivos de soporte y bitacora de auditoria.
 *
 * Es idempotente: si ya existen filas propias las respeta.
 */
class DemoConsolidationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $reviewerId = DB::table('users')->where('username', 'electoraladmin')->value('id')
            ?? DB::table('users')->value('id');
        $adminId = DB::table('users')->where('username', 'superadmin')->value('id') ?? $reviewerId;
        $juradoId = DB::table('users')->where('username', 'jurado')->value('id') ?? $reviewerId;

        if (! $reviewerId) {
            $this->command?->warn('No hay usuarios; ejecuta UserSeeder primero.');

            return;
        }

        $this->seedRecordFiles($juradoId, $now);
        $this->seedCandidateDraftFiles($juradoId, $now);
        $this->seedReviews($reviewerId, $now);

        $elections = DB::table('scrutiny_block_results')
            ->distinct()
            ->orderBy('election_id')
            ->pluck('election_id');

        $this->command?->info("Consolidando {$elections->count()} elecciones con votos.");

        foreach ($elections as $electionId) {
            $this->consolidateElection((int) $electionId, $adminId, $now);
        }

        $this->seedAuditLogs($adminId, $reviewerId, $juradoId, $now);

        $this->command?->info('Consolidacion de demostracion finalizada.');
    }

    /**
     * Un archivo de acta escaneada por cada registro de escrutinio.
     */
    private function seedRecordFiles(int $uploaderId, $now): void
    {
        $existing = DB::table('scrutiny_record_files')->pluck('scrutiny_record_id')->flip();

        $rows = [];
        foreach (DB::table('scrutiny_records')->select('id', 'record_number')->cursor() as $record) {
            if ($existing->has($record->id)) {
                continue;
            }

            $name = 'acta-'.($record->record_number ?: $record->id).'.jpg';
            $rows[] = [
                'scrutiny_record_id' => $record->id,
                'uploaded_by_user_id' => $uploaderId,
                'file_type' => 'image',
                'original_name' => $name,
                'storage_path' => 'demo/actas/'.$record->id.'/'.$name,
                'mime_type' => 'image/jpeg',
                'file_size' => random_int(180_000, 950_000),
                'hash' => hash('sha256', 'acta-'.$record->id),
                'page_number' => 1,
                'is_primary' => true,
                'notes' => 'Archivo de demostracion (no existe en disco).',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('scrutiny_record_files', $rows);
    }

    /**
     * Soporte digitalizado de cada lote de captura de candidatos.
     */
    private function seedCandidateDraftFiles(int $uploaderId, $now): void
    {
        if (DB::table('candidate_draft_files')->exists()) {
            return;
        }

        $batches = DB::table('candidate_drafts')
            ->select('capture_batch_uuid', 'election_id')
            ->whereNotNull('capture_batch_uuid')
            ->distinct()
            ->get();

        if ($batches->isEmpty()) {
            $election = DB::table('elections')->value('id');
            if (! $election) {
                return;
            }
            $batches = collect([(object) [
                'capture_batch_uuid' => (string) Str::uuid(),
                'election_id' => $election,
            ]]);
        }

        $rows = [];
        foreach ($batches as $i => $batch) {
            $name = 'inscripcion-lote-'.($i + 1).'.pdf';
            $rows[] = [
                'capture_batch_uuid' => $batch->capture_batch_uuid,
                'election_id' => $batch->election_id,
                'uploaded_by_user_id' => $uploaderId,
                'original_name' => $name,
                'storage_path' => 'demo/inscripciones/'.$batch->capture_batch_uuid.'/'.$name,
                'mime_type' => 'application/pdf',
                'file_size' => random_int(120_000, 640_000),
                'hash' => hash('sha256', 'draft-'.$batch->capture_batch_uuid),
                'page_number' => 1,
                'notes' => 'Archivo de demostracion (no existe en disco).',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('candidate_draft_files', $rows);
    }

    /**
     * Revision humana de cada acta. La mayoria aprobada, algunas
     * observadas para poder probar los filtros de la bandeja.
     */
    private function seedReviews(int $reviewerId, $now): void
    {
        $existing = DB::table('scrutiny_reviews')->pluck('scrutiny_record_id')->flip();

        $extractionByRecord = DB::table('scrutiny_extractions')
            ->select('id', 'scrutiny_record_id')
            ->get()
            ->keyBy('scrutiny_record_id');

        $rows = [];
        $i = 0;
        foreach (DB::table('scrutiny_records')->select('id')->cursor() as $record) {
            if ($existing->has($record->id)) {
                continue;
            }

            $i++;
            $decision = match (true) {
                $i % 17 === 0 => 'rejected',
                $i % 7 === 0 => 'observed',
                default => 'approved',
            };

            $rows[] = [
                'scrutiny_record_id' => $record->id,
                'scrutiny_extraction_id' => $extractionByRecord[$record->id]->id ?? null,
                'reviewed_by_user_id' => $reviewerId,
                'decision' => $decision,
                'reviewed_at' => $now,
                'comments' => match ($decision) {
                    'rejected' => 'Inconsistencia entre el total de votos y el numero de asistentes.',
                    'observed' => 'Firma del jurado ilegible; se solicita nueva captura.',
                    default => 'Acta verificada contra la imagen escaneada.',
                },
                'changes_payload' => json_encode(['revisado_por_seeder' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('scrutiny_reviews', $rows);
    }

    /**
     * Suma los votos por bloque/plancha, calcula porcentajes,
     * asigna curules y registra a los electos.
     */
    private function consolidateElection(int $electionId, int $adminId, $now): void
    {
        if (DB::table('consolidation_runs')->where('election_id', $electionId)->exists()) {
            return;
        }

        $runId = DB::table('consolidation_runs')->insertGetId([
            'election_id' => $electionId,
            'created_by_user_id' => $adminId,
            'run_type' => 'general',
            'status' => 'completed',
            'started_at' => $now,
            'finished_at' => $now,
            'notes' => 'Consolidacion de demostracion generada por DemoConsolidationSeeder.',
            'metadata' => json_encode(['origen' => 'seeder', 'demo' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Votos agregados por bloque y plancha.
        $totals = DB::table('scrutiny_block_results')
            ->select('election_block_id', 'slate_block_id', DB::raw('SUM(votes) as total_votes'))
            ->where('election_id', $electionId)
            ->groupBy('election_block_id', 'slate_block_id')
            ->get();

        if ($totals->isEmpty()) {
            return;
        }

        $votesByBlock = $totals->groupBy('election_block_id')
            ->map(fn ($rows) => $rows->sum('total_votes'));

        $consolidated = [];
        foreach ($totals as $row) {
            $blockTotal = (int) ($votesByBlock[$row->election_block_id] ?? 0);

            $consolidated[] = [
                'consolidation_run_id' => $runId,
                'election_id' => $electionId,
                'election_block_id' => $row->election_block_id,
                'slate_block_id' => $row->slate_block_id,
                'total_votes' => (int) $row->total_votes,
                'vote_percentage' => $blockTotal > 0
                    ? round($row->total_votes * 100 / $blockTotal, 4)
                    : null,
                'status' => 'final',
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('consolidated_block_results', $consolidated);

        $this->allocateSeats($runId, $electionId, $totals, $now);
    }

    /**
     * Reparte las vacantes de cada posicion entre las planchas mas
     * votadas del bloque y deja registrados a los electos.
     *
     * Metodo: mayoria simple ordenada por votacion. Es un dato de
     * demostracion, no la formula legal de cifra repartidora.
     */
    private function allocateSeats(int $runId, int $electionId, $totals, $now): void
    {
        $documentTypeId = DB::table('document_types')->where('code', 'CC')->value('id')
            ?? DB::table('document_types')->value('id');

        $allocations = [];
        $elected = [];

        foreach ($totals->groupBy('election_block_id') as $electionBlockId => $blockRows) {
            // Planchas del bloque ordenadas de mayor a menor votacion.
            $ranked = $blockRows->sortByDesc('total_votes')->values();

            $positions = DB::table('election_block_positions')
                ->where('election_block_id', $electionBlockId)
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'vacancies']);

            $order = 0;

            foreach ($positions as $position) {
                $vacancies = max(1, (int) $position->vacancies);

                for ($seat = 0; $seat < $vacancies; $seat++) {
                    $winner = $ranked[$seat % max(1, $ranked->count())] ?? null;
                    if (! $winner || ! $winner->slate_block_id) {
                        continue;
                    }

                    // Candidato de esa plancha para esa posicion.
                    $candidate = DB::table('candidates')
                        ->where('slate_block_id', $winner->slate_block_id)
                        ->where('election_block_position_id', $position->id)
                        ->where('is_active', true)
                        ->first(['id', 'person_id']);

                    $order++;

                    $allocations[] = [
                        'consolidation_run_id' => $runId,
                        'election_id' => $electionId,
                        'election_block_id' => $electionBlockId,
                        'election_block_position_id' => $position->id,
                        'slate_block_id' => $winner->slate_block_id,
                        'candidate_id' => $candidate->id ?? null,
                        'allocated_seats' => 1,
                        'allocation_order' => $order,
                        'allocation_method' => 'mayoria_simple_demo',
                        'notes' => 'Asignacion de demostracion segun votacion consolidada.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (! $candidate || ! $candidate->person_id) {
                        continue;
                    }

                    $person = DB::table('persons')->where('id', $candidate->person_id)->first();
                    if (! $person) {
                        continue;
                    }

                    $elected[] = [
                        'scrutiny_record_id' => $this->recordIdForElection($electionId),
                        'election_id' => $electionId,
                        'election_block_id' => $electionBlockId,
                        'election_block_position_id' => $position->id,
                        'document_type_id' => $person->document_type_id ?? $documentTypeId,
                        'person_id' => $person->id,
                        'document_number' => $person->document_number,
                        'first_name' => $person->first_name,
                        'middle_name' => $person->middle_name,
                        'last_name' => $person->last_name,
                        'second_last_name' => $person->second_last_name,
                        'phone' => $person->phone,
                        'email' => $person->email,
                        'signature_path' => null,
                        'source_type' => 'consolidation',
                        'confidence_score' => 100.00,
                        'review_status' => 'approved',
                        'notes' => 'Electo segun consolidacion de demostracion.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertChunked('seat_allocations', $allocations);
        $this->insertChunked('scrutiny_elected_people', array_filter(
            $elected,
            fn ($row) => $row['scrutiny_record_id'] !== null
        ));
    }

    /** @var array<int,int|null> */
    private array $recordCache = [];

    private function recordIdForElection(int $electionId): ?int
    {
        return $this->recordCache[$electionId] ??= DB::table('scrutiny_records')
            ->where('election_id', $electionId)
            ->value('id');
    }

    /**
     * Bitacora con las acciones tipicas del flujo electoral.
     */
    private function seedAuditLogs(int $adminId, int $reviewerId, int $juradoId, $now): void
    {
        if (DB::table('audit_logs')->exists()) {
            return;
        }

        $rows = [];

        $rows[] = [
            'user_id' => $adminId,
            'action' => 'election.opened',
            'auditable_type' => 'App\\Models\\Election',
            'auditable_id' => DB::table('elections')->value('id'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder/DemoConsolidationSeeder',
            'old_values' => null,
            'new_values' => json_encode(['status' => 'active'], JSON_UNESCAPED_UNICODE),
            'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach (DB::table('scrutiny_records')->select('id')->limit(60)->get() as $record) {
            $rows[] = [
                'user_id' => $juradoId,
                'action' => 'scrutiny_record.captured',
                'auditable_type' => 'App\\Models\\ScrutinyRecord',
                'auditable_id' => $record->id,
                'ip_address' => '192.168.1.'.random_int(2, 254),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) DemoSeeder',
                'old_values' => null,
                'new_values' => json_encode(['status' => 'captured'], JSON_UNESCAPED_UNICODE),
                'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'user_id' => $reviewerId,
                'action' => 'scrutiny_record.reviewed',
                'auditable_type' => 'App\\Models\\ScrutinyRecord',
                'auditable_id' => $record->id,
                'ip_address' => '192.168.1.'.random_int(2, 254),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) DemoSeeder',
                'old_values' => json_encode(['status' => 'captured'], JSON_UNESCAPED_UNICODE),
                'new_values' => json_encode(['status' => 'reviewed'], JSON_UNESCAPED_UNICODE),
                'metadata' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (DB::table('consolidation_runs')->select('id', 'election_id')->limit(40)->get() as $run) {
            $rows[] = [
                'user_id' => $adminId,
                'action' => 'consolidation.completed',
                'auditable_type' => 'App\\Models\\ConsolidationRun',
                'auditable_id' => $run->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder/DemoConsolidationSeeder',
                'old_values' => json_encode(['status' => 'pending'], JSON_UNESCAPED_UNICODE),
                'new_values' => json_encode(['status' => 'completed'], JSON_UNESCAPED_UNICODE),
                'metadata' => json_encode(['election_id' => $run->election_id], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunked('audit_logs', $rows);
    }

    private function insertChunked(string $table, array $rows, int $size = 500): void
    {
        foreach (array_chunk($rows, $size) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
