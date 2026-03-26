<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyRecordFile;
use App\Models\ScrutinyReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ScrutinyRecord::query()
            ->with([
                'pollingTable:id,election_id,name,code,location',
                'createdByUser.person:id,first_name,last_name',
                'createdByUser:id,person_id,username',
                'election:id,neighborhood_id,name',
                'election.neighborhood:id,commune_id,name',
                'election.neighborhood.commune:id,name',
                'extractions:id,scrutiny_record_id,status,confidence_score,created_at',
            ])
            ->withSum('blockResults as valid_votes_sum', 'votes');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->whereHas('pollingTable', function ($tableQuery) use ($search): void {
                    $tableQuery->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%")
                        ->orWhere('location', 'ilike', "%{$search}%");
                })->orWhereHas('createdByUser', function ($userQuery) use ($search): void {
                    $userQuery->where('username', 'ilike', "%{$search}%")
                        ->orWhereHas('person', function ($personQuery) use ($search): void {
                            $personQuery->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                })->orWhereHas('election.neighborhood.commune', function ($communeQuery) use ($search): void {
                    $communeQuery->where('name', 'ilike', "%{$search}%");
                });
            });
        }

        $filter = $request->string('filter')->toString();
        if ($filter === 'review') {
            $query->whereIn('status', ['draft', 'pending', 'pending_review']);
        }

        if ($filter === 'processed') {
            $query->whereIn('status', ['reviewed', 'approved', 'consolidated']);
        }

        $records = $query
            ->latest('updated_at')
            ->paginate((int) $request->integer('per_page', 20))
            ->through(function (ScrutinyRecord $record): array {
                $latestExtraction = $record->extractions->sortByDesc('created_at')->first();

                $confidence = $latestExtraction?->confidence_score !== null
                    ? (float) $latestExtraction->confidence_score
                    : null;

                $statusTag = $this->buildStatusTag($record->status, $confidence);
                $userName = $record->createdByUser?->person
                    ? trim(($record->createdByUser->person->first_name ?? '').' '.($record->createdByUser->person->last_name ?? ''))
                    : null;

                return [
                    'id' => $record->id,
                    'polling_table' => [
                        'id' => $record->pollingTable?->id,
                        'name' => $record->pollingTable?->name,
                        'code' => $record->pollingTable?->code,
                        'location' => $record->pollingTable?->location,
                    ],
                    'commune_name' => $record->election?->neighborhood?->commune?->name,
                    'jury_name' => $userName !== null && $userName !== ''
                        ? $userName
                        : ($record->createdByUser?->username ?? 'Sin usuario'),
                    'transmitted_at_human' => $record->updated_at?->diffForHumans(),
                    'valid_votes' => (int) ($record->valid_votes_sum ?? 0),
                    'status' => $record->status,
                    'ai_confidence' => $confidence,
                    'status_tag' => $statusTag,
                ];
            });

        $statsBase = ScrutinyRecord::query();
        $processedCount = (clone $statsBase)->whereIn('status', ['reviewed', 'approved', 'consolidated'])->count();
        $reviewCount = (clone $statsBase)->whereIn('status', ['draft', 'pending', 'pending_review'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'processed_count' => $processedCount,
                    'review_count' => $reviewCount,
                ],
                'records' => $records,
            ],
        ]);
    }

    public function show(ScrutinyRecord $scrutinyRecord): JsonResponse
    {
        $scrutinyRecord->load([
            'pollingTable:id,election_id,name,code,location',
            'createdByUser.person:id,first_name,last_name',
            'createdByUser:id,person_id,username',
            'election:id,neighborhood_id,name',
            'election.neighborhood:id,commune_id,name',
            'election.neighborhood.commune:id,name',
            'files:id,scrutiny_record_id,original_name,mime_type,page_number,storage_path,created_at',
            'extractions:id,scrutiny_record_id,status,confidence_score,created_at,normalized_payload',
            'blockResults:id,scrutiny_record_id,election_block_id,slate_block_id,votes,status',
            'blockResults.electionBlock:id,block_id',
            'blockResults.electionBlock.block:id,name,code',
            'blockResults.slateBlock:id,slate_id',
            'blockResults.slateBlock.slate:id,code,name',
        ]);

        $latestExtraction = $scrutinyRecord->extractions->sortByDesc('created_at')->first();
        $confidence = $latestExtraction?->confidence_score !== null
            ? (float) $latestExtraction->confidence_score
            : null;

        // OCR block votes aggregated from all extraction pages (latest first, first write wins)
        $aggregatedBlockVotesMap = [];
        foreach ($scrutinyRecord->extractions->sortByDesc('created_at')->values() as $extraction) {
            $normalizedPayload = is_array($extraction->normalized_payload) ? $extraction->normalized_payload : [];
            foreach ((array) ($normalizedPayload['block_votes'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rawName = trim((string) ($row['block_name'] ?? ''));
                if ($rawName === '') {
                    continue;
                }

                $normalizedName = $this->normalizeBlockName($rawName);
                if ($normalizedName === '' || isset($aggregatedBlockVotesMap[$normalizedName])) {
                    continue;
                }

                $aggregatedBlockVotesMap[$normalizedName] = [
                    'name' => $rawName,
                    'votes' => [
                        'total_votes' => max(0, (int) ($row['total_votes'] ?? 0)),
                        'plancha_1' => max(0, (int) ($row['plancha_1'] ?? 0)),
                        'plancha_2' => max(0, (int) ($row['plancha_2'] ?? 0)),
                        'plancha_3' => max(0, (int) ($row['plancha_3'] ?? 0)),
                        'blancos' => max(0, (int) ($row['blancos'] ?? 0)),
                        'nulos' => max(0, (int) ($row['nulos'] ?? 0)),
                        'no_marcados' => max(0, (int) ($row['no_marcados'] ?? 0)),
                        'validos' => max(0, (int) ($row['validos'] ?? 0)),
                    ],
                ];
            }
        }

        // Base blocks from persisted plancha results
        $groupedBlocks = [];
        foreach ($scrutinyRecord->blockResults as $result) {
            $blockCode = $result->electionBlock?->block?->code ?? 'UNKNOWN';
            $blockName = $result->electionBlock?->block?->name ?? 'SIN BLOQUE';

            if (! isset($groupedBlocks[$blockCode])) {
                $groupedBlocks[$blockCode] = [
                    'name' => $blockName,
                    'votes' => [
                        'total_votes' => 0,
                        'plancha_1' => 0,
                        'plancha_2' => 0,
                        'plancha_3' => 0,
                        'blancos' => 0,
                        'nulos' => 0,
                        'no_marcados' => 0,
                        'validos' => 0,
                    ],
                ];
            }

            $slateCode = (string) ($result->slateBlock?->slate?->code ?? '');
            $slateIndex = 1;
            if ($slateCode !== '' && preg_match('/(\d+)/', $slateCode, $matches) === 1) {
                $slateIndex = max(1, min(3, (int) $matches[1]));
            }

            $key = 'plancha_'.$slateIndex;
            $groupedBlocks[$blockCode]['votes'][$key] += (int) $result->votes;
        }

        // Add OCR-only blocks (e.g., Conciliacion) not present in election catalog
        foreach ($aggregatedBlockVotesMap as $normalizedName => $ocrBlock) {
            $existsInCatalogBlocks = false;
            foreach ($groupedBlocks as $existing) {
                if ($this->normalizeBlockName((string) ($existing['name'] ?? '')) === $normalizedName) {
                    $existsInCatalogBlocks = true;
                    break;
                }
            }

            if ($existsInCatalogBlocks) {
                continue;
            }

            $groupedBlocks['OCR_'.$normalizedName] = [
                'name' => (string) ($ocrBlock['name'] ?? 'SIN BLOQUE'),
                'votes' => [
                    'total_votes' => (int) ($ocrBlock['votes']['total_votes'] ?? 0),
                    'plancha_1' => (int) ($ocrBlock['votes']['plancha_1'] ?? 0),
                    'plancha_2' => (int) ($ocrBlock['votes']['plancha_2'] ?? 0),
                    'plancha_3' => (int) ($ocrBlock['votes']['plancha_3'] ?? 0),
                    'blancos' => (int) ($ocrBlock['votes']['blancos'] ?? 0),
                    'nulos' => (int) ($ocrBlock['votes']['nulos'] ?? 0),
                    'no_marcados' => (int) ($ocrBlock['votes']['no_marcados'] ?? 0),
                    'validos' => (int) ($ocrBlock['votes']['validos'] ?? 0),
                ],
            ];
        }

        $blocks = array_values(array_map(function (array $block) use ($aggregatedBlockVotesMap): array {
            $normalizedName = $this->normalizeBlockName((string) ($block['name'] ?? ''));
            $extras = $aggregatedBlockVotesMap[$normalizedName]['votes'] ?? null;

            if (is_array($extras)) {
                // Keep persisted plancha totals when available and complete non-persisted columns.
                if ((int) $block['votes']['plancha_1'] === 0) {
                    $block['votes']['plancha_1'] = (int) $extras['plancha_1'];
                }
                if ((int) $block['votes']['plancha_2'] === 0) {
                    $block['votes']['plancha_2'] = (int) $extras['plancha_2'];
                }
                if ((int) $block['votes']['plancha_3'] === 0) {
                    $block['votes']['plancha_3'] = (int) $extras['plancha_3'];
                }

                $block['votes']['blancos'] = (int) $extras['blancos'];
                $block['votes']['nulos'] = (int) $extras['nulos'];
                $block['votes']['no_marcados'] = (int) $extras['no_marcados'];

                $planchasSumWithExtras =
                    (int) $block['votes']['plancha_1'] +
                    (int) $block['votes']['plancha_2'] +
                    (int) $block['votes']['plancha_3'];

                $validosFromExtras = (int) $extras['validos'];
                if ($validosFromExtras <= 0) {
                    $validosFromExtras = $planchasSumWithExtras + (int) $block['votes']['blancos'];
                }

                $totalFromExtras = (int) $extras['total_votes'];
                if ($totalFromExtras <= 0) {
                    $totalFromExtras = $validosFromExtras
                        + (int) $block['votes']['nulos']
                        + (int) $block['votes']['no_marcados'];
                }

                $block['votes']['validos'] = $validosFromExtras;
                $block['votes']['total_votes'] = $totalFromExtras;

                return $block;
            }

            $planchasSum =
                (int) $block['votes']['plancha_1'] +
                (int) $block['votes']['plancha_2'] +
                (int) $block['votes']['plancha_3'];

            $block['votes']['validos'] = $planchasSum + (int) $block['votes']['blancos'];
            $block['votes']['total_votes'] = $block['votes']['validos']
                + (int) $block['votes']['nulos']
                + (int) $block['votes']['no_marcados'];

            return $block;
        }, $groupedBlocks));

        $preferredOrder = [
            'directiva' => 10,
            'delegados asojuntas' => 20,
            'fiscal' => 30,
            'conciliacion' => 40,
        ];

        usort($blocks, function (array $a, array $b) use ($preferredOrder): int {
            $aKey = $this->normalizeBlockName((string) ($a['name'] ?? ''));
            $bKey = $this->normalizeBlockName((string) ($b['name'] ?? ''));

            $aOrder = $preferredOrder[$aKey] ?? 999;
            $bOrder = $preferredOrder[$bKey] ?? 999;

            if ($aOrder !== $bOrder) {
                return $aOrder <=> $bOrder;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $files = $scrutinyRecord->files
            ->sortBy('page_number')
            ->values()
            ->map(function (ScrutinyRecordFile $file): array {
                return [
                    'id' => $file->id,
                    'page_number' => $file->page_number,
                    'name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'url' => route('api.admin.audit-records.files.show', $file),
                ];
            })
            ->all();

        $userName = $scrutinyRecord->createdByUser?->person
            ? trim(($scrutinyRecord->createdByUser->person->first_name ?? '').' '.($scrutinyRecord->createdByUser->person->last_name ?? ''))
            : null;

        $validVotes = array_sum(array_map(function (array $block): int {
            return (int) ($block['votes']['validos'] ?? 0);
        }, $blocks));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $scrutinyRecord->id,
                'record_number' => $scrutinyRecord->record_number,
                'status' => $scrutinyRecord->status,
                'jury_name' => $userName !== null && $userName !== ''
                    ? $userName
                    : ($scrutinyRecord->createdByUser?->username ?? 'Sin usuario'),
                'election_name' => $scrutinyRecord->election?->name,
                'commune_name' => $scrutinyRecord->election?->neighborhood?->commune?->name,
                'polling_table' => [
                    'id' => $scrutinyRecord->pollingTable?->id,
                    'name' => $scrutinyRecord->pollingTable?->name,
                    'code' => $scrutinyRecord->pollingTable?->code,
                    'location' => $scrutinyRecord->pollingTable?->location,
                ],
                'ai_confidence' => $confidence,
                'valid_votes' => $validVotes,
                'files' => $files,
                'blocks' => $blocks,
                'updated_at_human' => $scrutinyRecord->updated_at?->diffForHumans(),
            ],
        ]);
    }

    public function showFile(ScrutinyRecordFile $scrutinyRecordFile): BinaryFileResponse
    {
        if (! Storage::disk('local')->exists($scrutinyRecordFile->storage_path)) {
            abort(404, 'El archivo no existe en el servidor.');
        }

        $path = Storage::disk('local')->path($scrutinyRecordFile->storage_path);

        return response()->file($path, [
            'Content-Type' => $scrutinyRecordFile->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.($scrutinyRecordFile->original_name ?: ('acta_'.$scrutinyRecordFile->id)).'"',
        ]);
    }

    public function decide(Request $request, ScrutinyRecord $scrutinyRecord): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected,reviewed',
            'comments' => 'nullable|string|max:1000',
        ]);

        $decision = (string) $validated['decision'];
        $scrutinyRecord->status = $decision;
        $scrutinyRecord->save();

        ScrutinyReview::create([
            'scrutiny_record_id' => $scrutinyRecord->id,
            'scrutiny_extraction_id' => $scrutinyRecord->extractions()->latest()->value('id'),
            'reviewed_by_user_id' => Auth::id(),
            'decision' => $decision,
            'reviewed_at' => now(),
            'comments' => $validated['comments'] ?? null,
            'changes_payload' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Acta actualizada correctamente.',
            'data' => [
                'id' => $scrutinyRecord->id,
                'status' => $scrutinyRecord->status,
            ],
        ]);
    }

    private function buildStatusTag(string $recordStatus, ?float $confidence): array
    {
        if (in_array($recordStatus, ['reviewed', 'approved', 'consolidated'], true)) {
            $confidenceText = $confidence !== null
                ? sprintf('%d%% confianza', (int) round($confidence * 100))
                : 'Procesada';

            return [
                'kind' => 'ok',
                'text' => $confidenceText,
            ];
        }

        if ($confidence !== null && $confidence >= 0.9) {
            return [
                'kind' => 'ok',
                'text' => sprintf('%d%% confianza', (int) round($confidence * 100)),
            ];
        }

        return [
            'kind' => 'review',
            'text' => 'Requiere revision',
        ];
    }

    private function normalizeBlockName(string $name): string
    {
        $value = trim($name);
        if ($value === '') {
            return '';
        }

        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (! is_string($normalized) || $normalized === '') {
            $normalized = $value;
        }

        $normalized = strtolower($normalized);
        $normalized = preg_replace('/^bloque\s*n\.?\s*[o\xBA]?\s*\d+\s*[-–:]\s*/', '', (string) $normalized);
        $normalized = preg_replace('/^bloque\s*[-–:]\s*/', '', (string) $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim((string) $normalized);
    }
}
