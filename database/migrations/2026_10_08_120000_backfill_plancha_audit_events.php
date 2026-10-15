<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La bitácora guardaba la aprobación y la oficialización de una plancha como
 * una fila por candidato. Ahora cada una deja además un solo evento
 * ("Aprobó la plancha 2 de …"), que es lo que se ve en "solo lo importante".
 *
 * Esta migración crea ese evento para las planchas que ya se habían aprobado u
 * oficializado, a partir de las filas por candidato. No borra ni cambia nada.
 */
return new class extends Migration
{
    private const DRAFT = 'App\\Models\\CandidateDraft';

    public function up(): void
    {
        $groups = [];

        DB::table('audit_logs')
            ->where('action', 'updated')
            ->where('auditable_type', self::DRAFT)
            ->orderBy('id')
            ->select(['id', 'user_id', 'auditable_id', 'ip_address', 'old_values', 'new_values', 'metadata', 'created_at'])
            ->chunkById(500, function ($rows) use (&$groups): void {
                foreach ($rows as $row) {
                    $route = (string) (json_decode((string) $row->metadata, true)['route'] ?? '');
                    $old = json_decode((string) $row->old_values, true) ?: [];
                    $new = json_decode((string) $row->new_values, true) ?: [];

                    if (str_ends_with($route, 'planchas.drafts.decision.batch')) {
                        if (($old['review_status'] ?? null) === ($new['review_status'] ?? null)) {
                            continue;
                        }
                        $action = 'plancha_decision';
                        $decision = (string) $new['review_status'];
                    } elseif (str_ends_with($route, 'planchas.drafts.promote')) {
                        if (! ($new['is_processed'] ?? false) || ($old['is_processed'] ?? false)) {
                            continue;
                        }
                        $action = 'plancha_officialized';
                        $decision = null;
                    } else {
                        continue;
                    }

                    $batch = (string) ($new['capture_batch_uuid'] ?? '');
                    $key = implode('|', [$row->user_id, $action, $decision, $batch ?: 'e'.($new['election_id'] ?? 0)]);

                    $groups[$key] ??= [
                        'user_id' => $row->user_id, 'action' => $action, 'decision' => $decision, 'batch' => $batch ?: null,
                        'election_id' => $new['election_id'] ?? null, 'slate_id' => null, 'first_draft' => $row->auditable_id,
                        'drafts' => [], 'ip_address' => $row->ip_address, 'created_at' => $row->created_at,
                    ];
                    $groups[$key]['drafts'][$row->auditable_id] = true;
                    $groups[$key]['slate_id'] ??= $new['slate_id'] ?? null;
                    $groups[$key]['created_at'] = $row->created_at;
                }
            });

        if ($groups === []) {
            return;
        }

        $neighborhoods = DB::table('elections as e')
            ->join('neighborhoods as n', 'n.id', '=', 'e.neighborhood_id')
            ->whereIn('e.id', array_filter(array_column($groups, 'election_id')))
            ->get(['e.id', 'n.id as neighborhood_id', 'n.name'])
            ->keyBy('id');
        $slateCodes = DB::table('slates')->whereIn('id', array_filter(array_column($groups, 'slate_id')))->pluck('code', 'id');

        $rows = [];
        foreach ($groups as $group) {
            $neighborhood = $neighborhoods[$group['election_id']] ?? null;
            $slate = preg_match('/(\d+)/', (string) ($slateCodes[$group['slate_id']] ?? ''), $match) === 1 ? (int) $match[1] : null;

            $rows[] = [
                'user_id' => $group['user_id'],
                'action' => $group['action'],
                'auditable_type' => self::DRAFT,
                'auditable_id' => $group['first_draft'],
                'ip_address' => $group['ip_address'],
                'user_agent' => null,
                'old_values' => null,
                'new_values' => null,
                'metadata' => json_encode(array_filter([
                    'decision' => $group['decision'],
                    'neighborhood_id' => $neighborhood?->neighborhood_id,
                    'neighborhood' => $neighborhood?->name,
                    'election_id' => $group['election_id'],
                    'slate' => $slate,
                    'batch' => $group['batch'],
                    'candidates' => count($group['drafts']),
                    'backfilled' => true,
                ], fn ($value) => $value !== null), JSON_UNESCAPED_UNICODE),
                'created_at' => $group['created_at'],
                'updated_at' => $group['created_at'],
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('audit_logs')->insert($chunk);
        }
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->whereIn('action', ['plancha_decision', 'plancha_officialized'])
            ->whereRaw("COALESCE(CAST(metadata AS TEXT), '') LIKE ?", ['%"backfilled":true%'])
            ->delete();
    }
};
