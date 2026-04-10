<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\PollingTable;
use App\Models\ScrutinyRecord;
use App\Models\ScrutinyRecordFile;
use App\Services\ScrutinyExtractionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class JuryIngestController extends Controller
{
    public function context(Request $request): JsonResponse
    {
        $user = $request->user();
        $storageDisk = $this->storageDisk();
        $suggestedPollingTableId = $this->resolveDefaultPollingTableId($request);

        $lastOpenRecord = ScrutinyRecord::query()
            ->where('created_by_user_id', $user->id)
            ->whereIn('status', ['draft', 'pending'])
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'suggested_scrutiny_record_id' => $lastOpenRecord?->id,
                'suggested_polling_table_id' => $lastOpenRecord?->polling_table_id ?? $suggestedPollingTableId,
                'suggested_election_id' => $lastOpenRecord?->election_id,
                'storage_disk' => $storageDisk,
            ],
        ]);
    }

    public function submit(Request $request, ScrutinyExtractionImporter $importer): JsonResponse
    {
        $this->extendExecutionTimeout();

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
        $storageDisk = $this->storageDisk();

        $file = $request->file('document_file');
        $fileHash = hash_file('sha256', $file->getRealPath());
        $pageNumber = (int) $validated['page_number'];
        $existingPageFile = ScrutinyRecordFile::query()
            ->where('scrutiny_record_id', $record->id)
            ->where('page_number', $pageNumber)
            ->latest('id')
            ->first();

        $alreadyUploaded = ScrutinyRecordFile::where('hash', $fileHash)->first();

        if (
            $alreadyUploaded
            && $alreadyUploaded->scrutiny_record_id === $record->id
            && (int) $alreadyUploaded->page_number === $pageNumber
        ) {
            $recordFile = $alreadyUploaded;
        } elseif (
            $alreadyUploaded
            && $alreadyUploaded->scrutiny_record_id === $record->id
            && (int) $alreadyUploaded->page_number !== $pageNumber
        ) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo ya está asociado a otra página de esta misma acta.',
                'data' => [
                    'existing_scrutiny_record_file_id' => $alreadyUploaded->id,
                    'existing_page_number' => (int) $alreadyUploaded->page_number,
                ],
            ], 409);
        } else {
            $path = $file->store("actas/{$record->election_id}/mesa-{$record->polling_table_id}/record-{$record->id}", $storageDisk);

            if ($existingPageFile) {
                if (Storage::disk($storageDisk)->exists($existingPageFile->storage_path)) {
                    Storage::disk($storageDisk)->delete($existingPageFile->storage_path);
                }

                $existingPageFile->fill([
                    'uploaded_by_user_id' => $request->user()->id,
                    'file_type' => Str::lower((string) $file->getClientOriginalExtension()),
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'hash' => $fileHash,
                    'page_number' => $pageNumber,
                    'is_primary' => (bool) ($validated['is_primary'] ?? false),
                    'notes' => $validated['notes'] ?? null,
                ]);
                $existingPageFile->save();
                $recordFile = $existingPageFile;
            } else {
                $recordFile = ScrutinyRecordFile::create([
                    'scrutiny_record_id' => $record->id,
                    'uploaded_by_user_id' => $request->user()->id,
                    'file_type' => Str::lower((string) $file->getClientOriginalExtension()),
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'hash' => $fileHash,
                    'page_number' => $pageNumber,
                    'is_primary' => (bool) ($validated['is_primary'] ?? false),
                    'notes' => $validated['notes'] ?? null,
                ]);
            }
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

        if (in_array($record->status, ['draft', 'pending'], true)) {
            $record->status = 'pending_review';
            $record->save();
        }

        $extraction = $importResult['extraction'];

        $serverFilePath = null;
        try {
            $serverFilePath = Storage::disk($storageDisk)->path($recordFile->storage_path);
        } catch (Throwable) {
            // Cloud drivers may not expose a local absolute path.
            $serverFilePath = null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Carga jurado procesada correctamente.',
            'data' => [
                'scrutiny_record_id' => $record->id,
                'scrutiny_record_file_id' => $recordFile->id,
                'extraction_id' => $extraction->id,
                'storage_path' => $recordFile->storage_path,
                'server_file_path' => $serverFilePath,
                'download_url' => route('api.jury.scrutiny-files.show', $recordFile),
                'summary' => $importResult['summary'],
            ],
        ], 201);
    }

    public function previewExtraction(Request $request): JsonResponse
    {
        $this->extendExecutionTimeout();

        $maxFileSizeKb = (int) config('services.extractor.max_upload_kb', 10240);

        $validated = $request->validate([
            'document_file' => 'required|file|mimes:jpeg,png,jpg|max:'.$maxFileSizeKb,
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

        try {
            $pythonBinary = $this->resolvePythonBinary();
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error' => $exception->getMessage(),
            ], 422);
        }

        try {
            $process = new Process([
                $pythonBinary,
                base_path('data_extraction/motor_extraction.py'),
                '--image',
                $tmpPath,
                '--dry-run',
            ], base_path(), $this->buildExtractorProcessEnvironment(), null, 180);

            $process->run();

            if (! $process->isSuccessful()) {
                $errorDetail = trim($process->getErrorOutput() ?: $process->getOutput());
                $classified = $this->classifyExtractorError($errorDetail);

                Log::warning('previewExtraction extractor failure', [
                    'status' => $classified['status'],
                    'error_code' => $classified['error_code'],
                    'retriable' => $classified['retriable'],
                    'python_bin' => $pythonBinary,
                    'detail' => $classified['detail'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $classified['message'] !== ''
                        ? 'Fallo el extractor de texto: '.$classified['message']
                        : 'Fallo el extractor de texto.',
                    'error' => $classified['detail'],
                    'error_code' => $classified['error_code'],
                    'retriable' => $classified['retriable'],
                    'python_bin' => $pythonBinary,
                ], $classified['status']);
            }

            $stdout = trim($process->getOutput());
            $json = json_decode($stdout, true);

            if (! is_array($json)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La salida del extractor no es JSON válido.',
                    'raw' => $stdout,
                    'error_code' => 'extractor_invalid_json',
                    'retriable' => false,
                ], 502);
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
        $storageDisk = $this->resolveStorageDisk($scrutinyRecordFile->storage_path);

        if ($storageDisk === null) {
            abort(404, 'El archivo no existe en el servidor.');
        }

        return Storage::disk($storageDisk)->download(
            $scrutinyRecordFile->storage_path,
            $scrutinyRecordFile->original_name ?? ('acta_'.$scrutinyRecordFile->id)
        );
    }

    private function storageDisk(): string
    {
        return (string) config('services.extractor.storage_disk', config('filesystems.default', 'local'));
    }

    private function extendExecutionTimeout(): void
    {
        $seconds = max(30, (int) config('services.extractor.request_timeout_seconds', 180));

        @set_time_limit($seconds);

        try {
            ini_set('max_execution_time', (string) $seconds);
        } catch (Throwable) {
            // Ignore environments where ini_set is restricted.
        }
    }

    private function resolveStorageDisk(string $path): ?string
    {
        $disks = array_values(array_unique([
            $this->storageDisk(),
            'local',
        ]));

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    private function resolveScrutinyRecord(Request $request, array $validated): ScrutinyRecord
    {
        if (! empty($validated['scrutiny_record_id'])) {
            return ScrutinyRecord::findOrFail((int) $validated['scrutiny_record_id']);
        }

        $pollingTableId = ! empty($validated['polling_table_id'])
            ? (int) $validated['polling_table_id']
            : ($this->resolveDefaultPollingTableId($request) ?? 0);

        if ($pollingTableId > 0) {
            $pollingTable = PollingTable::findOrFail($pollingTableId);

            $existing = ScrutinyRecord::query()
                ->where('polling_table_id', $pollingTable->id)
                ->where('created_by_user_id', $request->user()->id)
                ->whereIn('status', ['draft', 'pending'])
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

    private function resolveDefaultPollingTableId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $lastOpenRecord = ScrutinyRecord::query()
            ->where('created_by_user_id', $user->id)
            ->whereIn('status', ['draft', 'pending'])
            ->latest()
            ->first();

        if ($lastOpenRecord?->polling_table_id) {
            return (int) $lastOpenRecord->polling_table_id;
        }

        $neighborhoodId = $user->person?->neighborhood_id;
        if ($neighborhoodId) {
            $activeElection = Election::query()
                ->where('neighborhood_id', $neighborhoodId)
                ->where('is_active', true)
                ->latest('election_date')
                ->first();

            if ($activeElection) {
                $pollingTableId = PollingTable::query()
                    ->where('election_id', $activeElection->id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->value('id');

                if ($pollingTableId) {
                    return (int) $pollingTableId;
                }

                $pollingTableId = PollingTable::query()
                    ->where('election_id', $activeElection->id)
                    ->orderBy('id')
                    ->value('id');

                if ($pollingTableId) {
                    return (int) $pollingTableId;
                }
            }
        }

        $activePollingTables = PollingTable::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id']);

        if ($activePollingTables->count() === 1) {
            return (int) $activePollingTables->first()->id;
        }

        return null;
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
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $venvWindows = base_path('.venv/Scripts/python.exe');
        $venvPosix = base_path('.venv/bin/python');
        $candidates[] = $venvWindows;
        $candidates[] = $venvPosix;
        $candidates[] = 'python';
        $candidates[] = 'python3';

        foreach ($candidates as $candidate) {
            if (! $this->canRunPythonBinary($candidate)) {
                continue;
            }

            return $candidate;
        }

        throw new RuntimeException(
            'No se encontro un ejecutable de Python valido para OCR. '
            .'Crea .venv en la raiz del proyecto o configura EXTRACTOR_PYTHON_BIN con la ruta local de tu equipo.'
        );
    }

    private function canRunPythonBinary(string $binary): bool
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR) && ! is_file($binary)) {
            return false;
        }

        try {
            $process = new Process([$binary, '--version'], base_path(), null, null, 10);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    private function buildExtractorProcessEnvironment(): array
    {
        $resolvedRegion = (string) (env('AWS_REGION')
            ?: env('AWS_DEFAULT_REGION')
            ?: config('services.ses.region', 'us-east-1'));

        $env = [
            'APP_ENV' => (string) config('app.env', 'production'),
            'AWS_ACCESS_KEY_ID' => (string) env('AWS_ACCESS_KEY_ID', ''),
            'AWS_SECRET_ACCESS_KEY' => (string) env('AWS_SECRET_ACCESS_KEY', ''),
            'AWS_SESSION_TOKEN' => (string) env('AWS_SESSION_TOKEN', ''),
            'AWS_REGION' => $resolvedRegion,
            'AWS_DEFAULT_REGION' => $resolvedRegion,
            'BEDROCK_CONNECT_TIMEOUT_SECONDS' => (string) env('BEDROCK_CONNECT_TIMEOUT_SECONDS', ''),
            'BEDROCK_READ_TIMEOUT_SECONDS' => (string) env('BEDROCK_READ_TIMEOUT_SECONDS', ''),
            'BEDROCK_MAX_RETRIES' => (string) env('BEDROCK_MAX_RETRIES', ''),
            'BEDROCK_RETRY_BASE_SECONDS' => (string) env('BEDROCK_RETRY_BASE_SECONDS', ''),
            'HTTPS_PROXY' => (string) env('HTTPS_PROXY', ''),
            'HTTP_PROXY' => (string) env('HTTP_PROXY', ''),
            'NO_PROXY' => (string) env('NO_PROXY', ''),
            'PATH' => (string) env('PATH', (string) getenv('PATH')),
            'PYTHONUTF8' => '1',
        ];

        return array_filter($env, static fn ($value): bool => $value !== '');
    }

    private function classifyExtractorError(string $errorDetail): array
    {
        $detail = trim($errorDetail);
        $message = $detail;

        $decoded = $this->decodeJsonLikePayload($detail);
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['error'] ?? $decoded['message'] ?? $decoded['detail'] ?? $detail));
            $detail = trim((string) ($decoded['detail'] ?? $decoded['error'] ?? $detail));
        }

        if ($message === '') {
            $message = 'Error desconocido del extractor.';
        }

        $normalized = Str::lower($message.' '.$detail);

        if (
            str_contains($normalized, 'no se pudo conectar a aws bedrock')
            || str_contains($normalized, 'could not connect to the endpoint url')
            || str_contains($normalized, 'proxy')
            || str_contains($normalized, 'ssl')
            || str_contains($normalized, 'timed out')
            || str_contains($normalized, 'timeout')
        ) {
            return [
                'status' => 503,
                'error_code' => 'bedrock_connectivity_error',
                'retriable' => true,
                'message' => $message,
                'detail' => $detail,
            ];
        }

        if (
            str_contains($normalized, 'faltan credenciales aws')
            || str_contains($normalized, 'credenciales aws')
            || str_contains($normalized, 'accessdeniedexception')
            || str_contains($normalized, 'unrecognizedclientexception')
        ) {
            return [
                'status' => 503,
                'error_code' => 'bedrock_credentials_error',
                'retriable' => false,
                'message' => $message,
                'detail' => $detail,
            ];
        }

        return [
            'status' => 422,
            'error_code' => 'extractor_process_failed',
            'retriable' => false,
            'message' => $message,
            'detail' => $detail,
        ];
    }
}
