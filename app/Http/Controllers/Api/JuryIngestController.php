<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PollingTable;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyRecordFile;
use App\Services\ScrutinyExtractionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class JuryIngestController extends Controller
{
    public function context(Request $request): JsonResponse
    {
        $user = $request->user();

        $lastRecord = ScrutinyRecord::query()
            ->where('created_by_user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'suggested_scrutiny_record_id' => $lastRecord?->id,
                'suggested_polling_table_id' => $lastRecord?->polling_table_id,
                'suggested_election_id' => $lastRecord?->election_id,
                'storage_disk' => 'local',
            ],
        ]);
    }

    public function submit(Request $request, ScrutinyExtractionImporter $importer): JsonResponse
    {
        $maxFileSizeKb = (int) config('services.extractor.max_upload_kb', 10240);

        $validated = $request->validate([
            'scrutiny_record_id' => 'nullable|exists:scrutiny_records,id',
            'polling_table_id' => 'nullable|exists:polling_tables,id',
            'document_file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:'.$maxFileSizeKb,
            'page_number' => 'required|integer|min:1',
            'is_primary' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1500',
            'source_type' => 'nullable|in:ai,manual,api',
            'engine_name' => 'nullable|string|max:50',
            'engine_version' => 'nullable|string|max:30',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
            'status' => 'nullable|string|max:20',
            'raw_payload' => 'nullable',
            'normalized_payload' => 'required',
        ]);

        $record = $this->resolveScrutinyRecord($request, $validated);

        $file = $request->file('document_file');
        $fileHash = hash_file('sha256', $file->getRealPath());

        $alreadyUploaded = ScrutinyRecordFile::where('hash', $fileHash)->first();
        if ($alreadyUploaded && $alreadyUploaded->scrutiny_record_id !== $record->id) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo ya existe en otra acta del sistema.',
                'data' => [
                    'existing_scrutiny_record_file_id' => $alreadyUploaded->id,
                ],
            ], 409);
        }

        if ($alreadyUploaded && $alreadyUploaded->scrutiny_record_id === $record->id) {
            $recordFile = $alreadyUploaded;
        } else {
            $path = $file->store("actas/{$record->election_id}/mesa-{$record->polling_table_id}/record-{$record->id}", 'local');

            $recordFile = ScrutinyRecordFile::create([
                'scrutiny_record_id' => $record->id,
                'uploaded_by_user_id' => $request->user()->id,
                'file_type' => $file->getClientOriginalExtension(),
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'hash' => $fileHash,
                'page_number' => (int) $validated['page_number'],
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        $rawPayload = $this->decodeJsonLikePayload($validated['raw_payload'] ?? null);
        $normalizedPayload = $this->decodeJsonLikePayload($validated['normalized_payload']);

        if (! is_array($normalizedPayload)) {
            throw ValidationException::withMessages([
                'normalized_payload' => 'normalized_payload debe ser un objeto JSON válido.',
            ]);
        }

        $importResult = $importer->import([
            'scrutiny_record_id' => $record->id,
            'scrutiny_record_file_id' => $recordFile->id,
            'source_type' => $validated['source_type'] ?? 'ai',
            'engine_name' => $validated['engine_name'] ?? 'Jury-UI',
            'engine_version' => $validated['engine_version'] ?? 'web',
            'confidence_score' => $validated['confidence_score'] ?? 0.85,
            'status' => $validated['status'] ?? 'pending_review',
            'raw_payload' => $rawPayload,
            'normalized_payload' => $normalizedPayload,
            'notes' => $validated['notes'] ?? null,
        ], $request->user()->id);

        $extraction = $importResult['extraction'];

        return response()->json([
            'success' => true,
            'message' => 'Carga jurado procesada correctamente.',
            'data' => [
                'scrutiny_record_id' => $record->id,
                'scrutiny_record_file_id' => $recordFile->id,
                'extraction_id' => $extraction->id,
                'storage_path' => $recordFile->storage_path,
                'server_file_path' => Storage::disk('local')->path($recordFile->storage_path),
                'download_url' => route('api.jury.scrutiny-files.show', $recordFile),
                'summary' => $importResult['summary'],
            ],
        ], 201);
    }

    public function previewExtraction(Request $request): JsonResponse
    {
        $maxFileSizeKb = (int) config('services.extractor.max_upload_kb', 10240);

        $validated = $request->validate([
            'document_file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:'.$maxFileSizeKb,
            'document_type' => 'nullable|in:plancha,escrutinio',
            'page_number' => 'nullable|integer|min:1',
        ]);

        $file = $request->file('document_file');
        $tempDir = storage_path('app/private/tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $tmpName = 'preview_'.uniqid('', true).'_'.$file->getClientOriginalName();
        $tmpPath = $file->move($tempDir, $tmpName)->getPathname();
        $pythonBinary = $this->resolvePythonBinary();

        try {
            $process = new Process([
                $pythonBinary,
                base_path('data_extraction/motor_extraction.py'),
                '--image',
                $tmpPath,
                '--dry-run',
            ], base_path(), null, null, 180);

            $process->run();

            if (! $process->isSuccessful()) {
                $errorDetail = trim($process->getErrorOutput() ?: $process->getOutput());

                return response()->json([
                    'success' => false,
                    'message' => $errorDetail !== ''
                        ? 'Fallo el extractor de texto: '.$errorDetail
                        : 'Fallo el extractor de texto.',
                    'error' => $errorDetail,
                    'python_bin' => $pythonBinary,
                ], 422);
            }

            $stdout = trim($process->getOutput());
            $json = json_decode($stdout, true);

            if (! is_array($json)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La salida del extractor no es JSON válido.',
                    'raw' => $stdout,
                ], 422);
            }

            $normalizedPayload = $json['normalized_payload'] ?? [];
            $pageData = $this->mapNormalizedToReviewPage(
                is_array($normalizedPayload) ? $normalizedPayload : [],
                $validated['document_type'] ?? 'escrutinio'
            );

            return response()->json([
                'success' => true,
                'message' => 'Extracción preliminar completada.',
                'data' => [
                    'page_number' => (int) ($validated['page_number'] ?? 1),
                    'normalized_payload' => $normalizedPayload,
                    'review_page_data' => $pageData,
                ],
            ]);
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function showFile(ScrutinyRecordFile $scrutinyRecordFile)
    {
        if (! Storage::disk('local')->exists($scrutinyRecordFile->storage_path)) {
            abort(404, 'El archivo no existe en el servidor.');
        }

        return Storage::disk('local')->download(
            $scrutinyRecordFile->storage_path,
            $scrutinyRecordFile->original_name ?? ('acta_'.$scrutinyRecordFile->id)
        );
    }

    private function resolveScrutinyRecord(Request $request, array $validated): ScrutinyRecord
    {
        if (! empty($validated['scrutiny_record_id'])) {
            return ScrutinyRecord::findOrFail((int) $validated['scrutiny_record_id']);
        }

        if (! empty($validated['polling_table_id'])) {
            $pollingTable = PollingTable::findOrFail((int) $validated['polling_table_id']);

            $existing = ScrutinyRecord::query()
                ->where('polling_table_id', $pollingTable->id)
                ->where('created_by_user_id', $request->user()->id)
                ->whereIn('status', ['draft', 'pending', 'pending_review'])
                ->latest()
                ->first();

            if ($existing) {
                return $existing;
            }

            return ScrutinyRecord::create([
                'election_id' => $pollingTable->election_id,
                'polling_table_id' => $pollingTable->id,
                'created_by_user_id' => $request->user()->id,
                'record_number' => 'AUTO-'.$pollingTable->id.'-'.now()->format('YmdHis'),
                'record_date' => now()->toDateString(),
                'record_time' => now()->format('H:i:s'),
                'source_type' => 'api',
                'status' => 'draft',
                'metadata' => [
                    'origin' => 'jury-ui',
                ],
            ]);
        }

        $lastRecord = ScrutinyRecord::query()
            ->where('created_by_user_id', $request->user()->id)
            ->latest()
            ->first();

        if ($lastRecord) {
            return $lastRecord;
        }

        throw ValidationException::withMessages([
            'polling_table_id' => 'No hay acta previa para este usuario. Debes enviar polling_table_id al menos una vez.',
        ]);
    }

    private function decodeJsonLikePayload(mixed $payload): mixed
    {
        if (is_array($payload) || $payload === null) {
            return $payload;
        }

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $payload;
        }

        return $payload;
    }

    private function mapNormalizedToReviewPage(array $normalizedPayload, string $documentType): array
    {
        if ($documentType === 'plancha') {
            return [
                'bloques' => [],
            ];
        }

        $blockVotes = (array) ($normalizedPayload['block_votes'] ?? []);
        if (! empty($blockVotes)) {
            $bloques = [];

            foreach ($blockVotes as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $blockName = trim((string) ($row['block_name'] ?? 'SIN BLOQUE'));

                $bloques[] = [
                    'titulo' => 'Bloque - '.$blockName,
                    'votos' => [
                        'Votos totales' => max(0, (int) ($row['total_votes'] ?? 0)),
                        'Plancha 1' => max(0, (int) ($row['plancha_1'] ?? 0)),
                        'Plancha 2' => max(0, (int) ($row['plancha_2'] ?? 0)),
                        'Plancha 3' => max(0, (int) ($row['plancha_3'] ?? 0)),
                        'Votos blancos' => max(0, (int) ($row['blancos'] ?? 0)),
                        'Votos nulos' => max(0, (int) ($row['nulos'] ?? 0)),
                        'Votos no marcados' => max(0, (int) ($row['no_marcados'] ?? 0)),
                        'Votos validos' => max(0, (int) ($row['validos'] ?? 0)),
                    ],
                ];
            }

            return [
                'bloques' => $bloques,
            ];
        }

        $grouped = [];
        foreach ((array) ($normalizedPayload['block_results'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $blockName = trim((string) ($row['block_name'] ?? 'SIN BLOQUE'));
            $slateCode = trim((string) ($row['slate_code'] ?? ''));
            $votes = max(0, (int) ($row['votes'] ?? 0));

            if (! isset($grouped[$blockName])) {
                $grouped[$blockName] = [];
            }

            $label = $slateCode !== '' ? 'Plancha '.$slateCode : 'Plancha';
            $grouped[$blockName][$label] = $votes;
        }

        $bloques = [];
        foreach ($grouped as $blockName => $votos) {
            $bloques[] = [
                'titulo' => 'Bloque - '.$blockName,
                'votos' => $votos,
            ];
        }

        return [
            'bloques' => $bloques,
        ];
    }

    private function resolvePythonBinary(): string
    {
        $configured = trim((string) env('EXTRACTOR_PYTHON_BIN', ''));
        if ($configured !== '') {
            return $configured;
        }

        $venvWindows = base_path('.venv/Scripts/python.exe');
        if (is_file($venvWindows)) {
            return $venvWindows;
        }

        $venvPosix = base_path('.venv/bin/python');
        if (is_file($venvPosix)) {
            return $venvPosix;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    }
}
