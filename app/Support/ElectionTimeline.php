<?php

namespace App\Support;

use App\Models\ScrutinyRecord;
use Illuminate\Support\Facades\DB;

/**
 * Las horas clave de una elección, para mostrarlas junto a sus resultados:
 *  - cuándo se oficializó cada plancha,
 *  - cuándo y quién subió el acta (y cuándo se decidió),
 *  - cuándo se cerró la elección.
 *
 * Todo se calcula por lotes de elecciones (unas pocas consultas en total).
 */
final class ElectionTimeline
{
    /**
     * @param  array<int, int>  $electionIds
     * @return array<int, array{abierta: bool, cierre: ?array, acta: ?array, planchas: array<int, array>}>
     */
    public static function forElections(array $electionIds): array
    {
        $electionIds = array_values(array_unique(array_filter(array_map('intval', $electionIds))));
        if ($electionIds === []) {
            return [];
        }

        $elections = DB::table('elections')->whereIn('id', $electionIds)->get(['id', 'is_active', 'closed_at'])->keyBy('id');
        $actas = self::actas($electionIds);
        $planchas = self::planchas($electionIds);

        $timeline = [];
        foreach ($electionIds as $id) {
            $election = $elections[$id] ?? null;
            $timeline[$id] = [
                'abierta' => (bool) ($election->is_active ?? false),
                'cierre' => $election && ! $election->is_active ? LocalTime::present($election->closed_at) : null,
                'acta' => $actas[$id] ?? null,
                'planchas' => $planchas[$id] ?? [],
            ];
        }

        return $timeline;
    }

    /**
     * El acta que representa a cada elección: la que cuenta (aprobada) o, si
     * no hay, la más reciente que no fue rechazada; si todas lo fueron, la última.
     *
     * @param  array<int, int>  $electionIds
     */
    private static function actas(array $electionIds): array
    {
        $records = DB::table('scrutiny_records as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by_user_id')
            ->leftJoin('persons as p', 'p.id', '=', 'u.person_id')
            ->whereIn('r.election_id', $electionIds)
            ->whereNull('r.deleted_at')
            ->whereNotIn('r.status', ['draft'])
            ->orderByDesc('r.id')
            ->get([
                'r.id', 'r.election_id', 'r.record_number', 'r.status', 'r.created_at',
                'u.username', 'p.first_name', 'p.last_name',
            ]);

        if ($records->isEmpty()) {
            return [];
        }

        $recordIds = $records->pluck('id')->all();
        $uploadedAt = self::uploadedAt($recordIds);
        $decidedAt = DB::table('scrutiny_reviews')
            ->whereIn('scrutiny_record_id', $recordIds)
            ->whereIn('decision', ['approved', 'reviewed', 'rejected'])
            ->groupBy('scrutiny_record_id')
            ->selectRaw('scrutiny_record_id, MAX(COALESCE(reviewed_at, created_at)) as decided_at')
            ->pluck('decided_at', 'scrutiny_record_id');

        $rank = fn ($record): int => match (true) {
            in_array($record->status, ScrutinyRecord::APPROVED_STATUSES, true) => 0,
            $record->status !== 'rejected' => 1,
            default => 2,
        };

        $result = [];
        foreach ($records->groupBy('election_id') as $electionId => $electionRecords) {
            // Ya vienen del más nuevo al más viejo: el orden estable deja el más reciente de cada grupo.
            $record = $electionRecords->sortBy($rank)->first();
            $name = trim(($record->first_name ?? '').' '.($record->last_name ?? ''));

            $result[(int) $electionId] = [
                'id' => (int) $record->id,
                'numero' => $record->record_number,
                'estado' => $record->status,
                'cuenta' => $rank($record) === 0,
                'subida' => LocalTime::present($uploadedAt[$record->id] ?? $record->created_at),
                'subida_por' => $name !== '' ? $name : ($record->username ?? null),
                'decidida' => in_array($record->status, [...ScrutinyRecord::APPROVED_STATUSES, 'rejected'], true)
                    ? LocalTime::present($decidedAt[$record->id] ?? null)
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Cuándo se subió cada acta: la primera foto que llegó (el acta se crea en
     * ese mismo momento, así que su fecha sirve si no hubiera fotos).
     *
     * @param  array<int, int>  $recordIds
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function uploadedAt(array $recordIds)
    {
        return DB::table('scrutiny_record_files')
            ->whereIn('scrutiny_record_id', $recordIds)
            ->groupBy('scrutiny_record_id')
            ->selectRaw('scrutiny_record_id, MIN(created_at) as uploaded_at')
            ->pluck('uploaded_at', 'scrutiny_record_id');
    }

    /**
     * Planchas oficializadas de cada elección, con su hora: la de la última
     * oficialización de sus candidatos.
     *
     * @param  array<int, int>  $electionIds
     */
    private static function planchas(array $electionIds): array
    {
        $slates = DB::table('slates')
            ->whereIn('election_id', $electionIds)
            ->whereNull('deleted_at')
            ->get(['id', 'election_id', 'code', 'name']);

        $officialized = self::officializedBySlate($slates->pluck('id')->all());

        $result = [];
        foreach ($slates as $slate) {
            if (! isset($officialized[$slate->id])) {
                continue;
            }

            $number = preg_match('/(\d+)/', (string) $slate->code, $match) === 1 ? (int) $match[1] : null;
            $result[(int) $slate->election_id][] = [
                'numero' => $number,
                'nombre' => $number ? 'Plancha '.$number : ((string) $slate->name ?: 'Plancha'),
                'oficializada' => LocalTime::present($officialized[$slate->id]),
            ];
        }

        foreach ($result as &$list) {
            usort($list, fn (array $a, array $b) => ($a['numero'] ?? 99) <=> ($b['numero'] ?? 99));
        }

        return $result;
    }

    /**
     * Hora de oficialización por plancha. Se toma de los borradores
     * oficializados; si una plancha no tiene (datos cargados de otra forma),
     * de cuándo se crearon sus candidatos oficiales.
     *
     * @param  array<int, int>  $slateIds
     * @return array<int, string>
     */
    public static function officializedBySlate(array $slateIds): array
    {
        if ($slateIds === []) {
            return [];
        }

        $fromDrafts = DB::table('candidate_drafts')
            ->whereIn('slate_id', $slateIds)
            ->whereNull('deleted_at')
            ->where('is_processed', true)
            ->where('review_status', 'approved')
            ->whereNotNull('processed_at')
            ->groupBy('slate_id')
            ->selectRaw('slate_id, MAX(processed_at) as officialized_at')
            ->pluck('officialized_at', 'slate_id')
            ->all();

        $missing = array_diff($slateIds, array_keys($fromDrafts));
        $fromCandidates = $missing === [] ? [] : DB::table('candidates as c')
            ->join('slate_blocks as sb', 'sb.id', '=', 'c.slate_block_id')
            ->whereIn('sb.slate_id', $missing)
            ->whereNull('c.deleted_at')
            ->where('c.is_active', true)
            ->groupBy('sb.slate_id')
            ->selectRaw('sb.slate_id as slate_id, MAX(c.created_at) as officialized_at')
            ->pluck('officialized_at', 'slate_id')
            ->all();

        return $fromDrafts + $fromCandidates;
    }
}
