<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\ElectionBlock;
use App\Models\Person;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyElectedPerson;
use App\Models\ScrutinyExtraction;
use App\Models\ScrutinyRecord;
use App\Models\SlateBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScrutinyExtractionImporter
{
    public function import(array $data, ?int $createdByUserId = null): array
    {
        return DB::transaction(function () use ($data, $createdByUserId): array {
            $record = ScrutinyRecord::findOrFail((int) $data['scrutiny_record_id']);

            $lastRound = ScrutinyExtraction::where('scrutiny_record_id', $record->id)->max('round_number') ?? 0;

            $extraction = ScrutinyExtraction::create([
                'scrutiny_record_id' => $record->id,
                'scrutiny_record_file_id' => $data['scrutiny_record_file_id'] ?? null,
                'based_on_extraction_id' => $data['based_on_extraction_id'] ?? null,
                'created_by_user_id' => $createdByUserId,
                'source_type' => $data['source_type'] ?? 'ai',
                'engine_name' => $data['engine_name'] ?? 'AWS-Textract',
                'engine_version' => $data['engine_version'] ?? null,
                'confidence_score' => $data['confidence_score'] ?? null,
                'status' => $data['status'] ?? 'pending_review',
                'round_number' => $lastRound + 1,
                'raw_payload' => $data['raw_payload'] ?? null,
                'normalized_payload' => $data['normalized_payload'] ?? [],
                'notes' => $data['notes'] ?? null,
            ]);

            $normalized = (array) ($data['normalized_payload'] ?? []);
            $blockRows = $this->collectBlockResultRowsForRecord($record);

            $blockSummary = $this->syncBlockResults($record, $extraction, $blockRows);
            $peopleSummary = $this->syncElectedPeople($record, $extraction, (array) ($normalized['elected_people'] ?? []));

            return [
                'extraction' => $extraction,
                'summary' => [
                    'block_results' => $blockSummary,
                    'elected_people' => $peopleSummary,
                ],
            ];
        });
    }

    private function collectBlockResultRowsForRecord(ScrutinyRecord $record): array
    {
        $extractions = ScrutinyExtraction::query()
            ->with('scrutinyRecordFile')
            ->where('scrutiny_record_id', $record->id)
            ->whereNotNull('scrutiny_record_file_id')
            ->orderByDesc('id')
            ->get();

        $latestByPage = [];
        foreach ($extractions as $extraction) {
            $pageNumber = (int) ($extraction->scrutinyRecordFile?->page_number ?? 0);
            $pageKey = $pageNumber > 0 ? $pageNumber : $extraction->id;

            if (! isset($latestByPage[$pageKey])) {
                $latestByPage[$pageKey] = $extraction;
            }
        }

        $rows = [];
        foreach ($latestByPage as $pageKey => $extraction) {
            $normalizedPayload = (array) ($extraction->normalized_payload ?? []);
            $pageNumber = (int) ($extraction->scrutinyRecordFile?->page_number ?? 0);

            foreach ($this->resolveBlockResultRows($normalizedPayload, $pageNumber) as $row) {
                $row['source_page_key'] = $pageKey;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function resolveBlockResultRows(array $normalizedPayload, int $pageNumber = 0): array
    {
        $blockResults = array_values(array_filter(
            (array) ($normalizedPayload['block_results'] ?? []),
            static fn ($row): bool => is_array($row)
        ));

        if (! empty($blockResults)) {
            return array_map(
                static function (array $row) use ($pageNumber): array {
                    $row['page_number'] = max(0, (int) ($row['page_number'] ?? $pageNumber));
                    return $row;
                },
                $blockResults
            );
        }

        $derivedRows = [];

        foreach ((array) ($normalizedPayload['block_votes'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $blockName = trim((string) ($row['block_name'] ?? ''));
            if ($blockName === '') {
                continue;
            }

            foreach (['1', '2', '3'] as $slateNumber) {
                $derivedRows[] = [
                    'votes' => max(0, (int) ($row['plancha_'.$slateNumber] ?? 0)),
                    'status' => 'pending',
                    'notes' => 'Derivado de normalized_payload.block_votes',
                    'block_name' => $blockName,
                    'page_number' => $pageNumber,
                    'slate_code' => 'P'.$slateNumber,
                    'slate_name' => 'PLANCHA '.$slateNumber,
                ];
            }
        }

        return $derivedRows;
    }

    private function syncBlockResults(ScrutinyRecord $record, ScrutinyExtraction $extraction, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $aggregatedRows = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $votes = filter_var($row['votes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($votes === false) {
                throw ValidationException::withMessages([
                    "normalized_payload.block_results.$index.votes" => 'El valor de votos debe ser un entero mayor o igual a 0.',
                ]);
            }

            $electionBlockId = $this->resolveElectionBlockId($record, $row);
            if ($electionBlockId === null) {
                $skipped++;
                continue;
            }

            $slateBlockId = $this->resolveSlateBlockId($record, $electionBlockId, $row);
            $bucketKey = $record->id.'|'.$electionBlockId.'|'.($slateBlockId ?? 'null');

            if (! isset($aggregatedRows[$bucketKey])) {
                $aggregatedRows[$bucketKey] = [
                    'scrutiny_record_id' => $record->id,
                    'election_block_id' => $electionBlockId,
                    'slate_block_id' => $slateBlockId,
                    'votes' => 0,
                    'source_type' => $extraction->source_type,
                    'status' => $row['status'] ?? 'pending',
                    'confidence_score' => $row['confidence_score'] ?? $extraction->confidence_score,
                    'notes' => [],
                ];
            }

            $aggregatedRows[$bucketKey]['votes'] += (int) $votes;

            $note = trim((string) ($row['notes'] ?? ''));
            if ($note !== '') {
                $aggregatedRows[$bucketKey]['notes'][] = $note;
            }

            $pageNumber = max(0, (int) ($row['page_number'] ?? 0));
            if ($pageNumber > 0) {
                $aggregatedRows[$bucketKey]['notes'][] = 'Pagina '.$pageNumber;
            }

            if (! empty($row['status'])) {
                $aggregatedRows[$bucketKey]['status'] = (string) $row['status'];
            }

            if (isset($row['confidence_score'])) {
                $aggregatedRows[$bucketKey]['confidence_score'] = $row['confidence_score'];
            }
        }

        foreach ($aggregatedRows as $row) {
            $result = ScrutinyBlockResult::firstOrNew([
                'scrutiny_record_id' => $row['scrutiny_record_id'],
                'election_block_id' => $row['election_block_id'],
                'slate_block_id' => $row['slate_block_id'],
            ]);

            $alreadyExists = $result->exists;

            $result->fill([
                'election_id' => $record->election_id,
                'scrutiny_extraction_id' => $extraction->id,
                'votes' => (int) $row['votes'],
                'source_type' => $row['source_type'],
                'status' => $row['status'],
                'confidence_score' => $row['confidence_score'],
                'notes' => ! empty($row['notes']) ? implode(' | ', array_values(array_unique($row['notes']))) : null,
            ]);

            $result->save();

            if ($alreadyExists) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function syncElectedPeople(ScrutinyRecord $record, ScrutinyExtraction $extraction, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));

            if ($firstName === '' || $lastName === '') {
                $skipped++;
                continue;
            }

            $documentTypeId = $this->resolveDocumentTypeId($row);
            $personId = $this->resolvePersonId($row, $documentTypeId);

            $person = ScrutinyElectedPerson::firstOrNew([
                'scrutiny_record_id' => $record->id,
                'document_type_id' => $documentTypeId,
                'document_number' => $row['document_number'] ?? null,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            $alreadyExists = $person->exists;

            $person->fill([
                'election_id' => $record->election_id,
                'election_block_id' => $row['election_block_id'] ?? null,
                'election_block_position_id' => $row['election_block_position_id'] ?? null,
                'person_id' => $personId,
                'middle_name' => $row['middle_name'] ?? null,
                'second_last_name' => $row['second_last_name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'signature_path' => $row['signature_path'] ?? null,
                'source_type' => $extraction->source_type,
                'confidence_score' => $row['confidence_score'] ?? $extraction->confidence_score,
                'review_status' => $row['review_status'] ?? 'pending',
                'notes' => $row['notes'] ?? null,
            ]);

            $person->save();

            if ($alreadyExists) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function resolveElectionBlockId(ScrutinyRecord $record, array $row): ?int
    {
        if (! empty($row['election_block_id'])) {
            return ElectionBlock::where('id', (int) $row['election_block_id'])
                ->where('election_id', $record->election_id)
                ->value('id');
        }

        if (! empty($row['block_id'])) {
            return ElectionBlock::where('election_id', $record->election_id)
                ->where('block_id', (int) $row['block_id'])
                ->value('id');
        }

        if (! empty($row['block_code'])) {
            return ElectionBlock::query()
                ->join('blocks', 'blocks.id', '=', 'election_blocks.block_id')
                ->where('election_blocks.election_id', $record->election_id)
                ->whereRaw('LOWER(blocks.code) = ?', [strtolower((string) $row['block_code'])])
                ->value('election_blocks.id');
        }

        if (! empty($row['block_name'])) {
            return ElectionBlock::query()
                ->join('blocks', 'blocks.id', '=', 'election_blocks.block_id')
                ->where('election_blocks.election_id', $record->election_id)
                ->whereRaw('LOWER(blocks.name) = ?', [strtolower((string) $row['block_name'])])
                ->value('election_blocks.id');
        }

        return null;
    }

    private function resolveSlateBlockId(ScrutinyRecord $record, int $electionBlockId, array $row): ?int
    {
        if (! empty($row['slate_block_id'])) {
            return SlateBlock::where('id', (int) $row['slate_block_id'])
                ->where('election_id', $record->election_id)
                ->where('election_block_id', $electionBlockId)
                ->value('id');
        }

        if (! empty($row['slate_id'])) {
            return SlateBlock::where('election_id', $record->election_id)
                ->where('election_block_id', $electionBlockId)
                ->where('slate_id', (int) $row['slate_id'])
                ->value('id');
        }

        if (! empty($row['slate_code'])) {
            $rawCode = strtoupper(trim((string) $row['slate_code']));
            $candidateCodes = [$rawCode];

            // OCR suele devolver 1/2/3; en catalogo demo las planchas usan P1/P2.
            if (preg_match('/^\d+$/', $rawCode) === 1) {
                $candidateCodes[] = 'P'.$rawCode;
            }

            return SlateBlock::query()
                ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
                ->where('slate_blocks.election_id', $record->election_id)
                ->where('slate_blocks.election_block_id', $electionBlockId)
                ->whereIn(DB::raw('UPPER(slates.code)'), array_values(array_unique($candidateCodes)))
                ->value('slate_blocks.id');
        }

        if (! empty($row['slate_name'])) {
            return SlateBlock::query()
                ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
                ->where('slate_blocks.election_id', $record->election_id)
                ->where('slate_blocks.election_block_id', $electionBlockId)
                ->whereRaw('LOWER(slates.name) = ?', [strtolower((string) $row['slate_name'])])
                ->value('slate_blocks.id');
        }

        return null;
    }

    private function resolveDocumentTypeId(array $row): ?int
    {
        if (! empty($row['document_type_id'])) {
            return DocumentType::where('id', (int) $row['document_type_id'])->value('id');
        }

        if (! empty($row['document_type_code'])) {
            return DocumentType::whereRaw('LOWER(code) = ?', [strtolower((string) $row['document_type_code'])])->value('id');
        }

        if (! empty($row['document_type_name'])) {
            return DocumentType::whereRaw('LOWER(name) = ?', [strtolower((string) $row['document_type_name'])])->value('id');
        }

        return null;
    }

    private function resolvePersonId(array $row, ?int $documentTypeId): ?int
    {
        if (! empty($row['person_id'])) {
            return Person::where('id', (int) $row['person_id'])->value('id');
        }

        if (empty($row['document_number'])) {
            return null;
        }

        $query = Person::query()->where('document_number', (string) $row['document_number']);

        if ($documentTypeId !== null) {
            $query->where('document_type_id', $documentTypeId);
        }

        return $query->value('id');
    }
}
