<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Slate extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Números de plancha ya ocupados, por elección.
     *
     * Cada elección nace con las planchas P1..P3 vacías, así que "existe la
     * plancha" no significa "está registrada". Un número está ocupado si tiene
     * borradores vigentes (no rechazados) o candidatos oficiales.
     *
     * @param  array<int, int>  $electionIds
     * @return array<int, array<int, int>>  [election_id => [1, 2, ...]]
     */
    public static function occupiedNumbers(array $electionIds, ?string $exceptBatchUuid = null): array
    {
        if ($electionIds === []) {
            return [];
        }

        $fromDrafts = DB::table('candidate_drafts as d')
            ->join('slates as s', 's.id', '=', 'd.slate_id')
            ->whereIn('d.election_id', $electionIds)
            ->whereNull('d.deleted_at')
            ->where('d.review_status', '<>', 'rejected')
            ->when($exceptBatchUuid, fn ($q) => $q->where('d.capture_batch_uuid', '<>', $exceptBatchUuid))
            ->distinct()
            ->get(['d.election_id', 's.code']);

        $fromCandidates = DB::table('candidates as c')
            ->join('slate_blocks as sb', 'sb.id', '=', 'c.slate_block_id')
            ->join('slates as s', 's.id', '=', 'sb.slate_id')
            ->whereIn('c.election_id', $electionIds)
            ->where('c.is_active', true)
            ->distinct()
            ->get(['c.election_id', 's.code']);

        $occupied = [];
        foreach ($fromDrafts->concat($fromCandidates) as $row) {
            if (preg_match('/(\d+)/', (string) $row->code, $match) === 1) {
                $occupied[(int) $row->election_id][(int) $match[1]] = true;
            }
        }

        return array_map(function (array $numbers): array {
            $list = array_keys($numbers);
            sort($list);

            return $list;
        }, $occupied);
    }

    /** Primer número de plancha libre de la elección. */
    public static function nextFreeNumber(int $electionId): int
    {
        $occupied = self::occupiedNumbers([$electionId])[$electionId] ?? [];
        $number = 1;
        while (in_array($number, $occupied, true)) {
            $number++;
        }

        return $number;
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function slateBlocks(): HasMany
    {
        return $this->hasMany(SlateBlock::class);
    }

    public function candidateDrafts(): HasMany
    {
        return $this->hasMany(CandidateDraft::class);
    }
}
