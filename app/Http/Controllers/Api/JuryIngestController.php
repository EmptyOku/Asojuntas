<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\PollingTable;
use App\Models\ScrutinyExtraction;
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
            ->with([
                'pollingTable.election.neighborhood.commune',
            ])
            ->where('created_by_user_id', $user->id)
            ->whereIn('status', ['draft', 'pending'])
            ->latest()
            ->first();

        $suggestedPollingTable = $lastOpenRecord?->pollingTable;
        if (! $suggestedPollingTable && $suggestedPollingTableId) {
            $suggestedPollingTable = PollingTable::query()
                ->with(['election.neighborhood.commune'])
                ->find($suggestedPollingTableId);
        }

        $suggestedNeighborhood = $suggestedPollingTable?->election?->neighborhood;

        return response()->json([
            'success' => true,
            'data' => [
                'suggested_scrutiny_record_id' => $lastOpenRecord?->id,
                'suggested_polling_table_id' => $suggestedPollingTable?->id ?? $lastOpenRecord?->polling_table_id ?? $suggestedPollingTableId,
                'suggested_election_id' => $lastOpenRecord?->election_id,
                'suggested_polling_table' => $suggestedPollingTable ? [
                    'id' => $suggestedPollingTable->id,
                    'name' => $suggestedPollingTable->name,
                    'code' => $suggestedPollingTable->code,
                    'location' => $suggestedPollingTable->location,
                    'neighborhood' => $suggestedNeighborhood ? [
                        'id' => $suggestedNeighborhood->id,
                        'name' => $suggestedNeighborhood->name,
                        'address' => $suggestedNeighborhood->address ?? null,
                        'commune' => $suggestedNeighborhood->commune ? [
                            'id' => $suggestedNeighborhood->commune->id,
                            'name' => $suggestedNeighborhood->commune->name,
                        ] : null,
                    ] : null,
                ] : null,
                'storage_disk' => $storageDisk,
            ],
        ]);
    }

    public function status(Request $request, ScrutinyRecord $scrutinyRecord): JsonResponse
    {
        $user = $request->user();
        if (! $user || (int) $scrutinyRecord->created_by_user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar el estado de esta acta.',
            ], 403);
        }

        $files = ScrutinyRecordFile::query()
            ->where('scrutiny_record_id', $scrutinyRecord->id)
            ->orderBy('page_number')
            ->orderBy('id')
            ->get();

        $latestExtractionsByFile = ScrutinyExtraction::query()
            ->where('scrutiny_record_id', $scrutinyRecord->id)
            ->whereNotNull('scrutiny_record_file_id')
            ->latest('id')
            ->get()
            ->unique('scrutiny_record_file_id')
            ->keyBy('scrutiny_record_file_id');

        $processingMeta = [];
        if (is_array($scrutinyRecord->metadata)) {
            $candidateMeta = $scrutinyRecord->metadata['ingest_processing'] ?? [];
            if (is_array($candidateMeta)) {
                $processingMeta = $candidateMeta;
            }
        }

        $pages = [];
        $summary = [
            'total' => $files->count(),
            'queued' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'superseded' => 0,
            'unknown' => 0,
        ];

        foreach ($files as $file) {
            $pageKey = (string) ($file->page_number ?? $file->id);
            $meta = $processingMeta[$pageKey] ?? null;
            $latestExtraction = $latestExtractionsByFile->get($file->id);

            $pageStatus = 'unknown';
            $error = null;
            if (is_array($meta) && ! empty($meta['status'])) {
                $pageStatus = (string) $meta['status'];
                $error = isset($meta['error']) ? (string) $meta['error'] : null;
            } elseif ($latestExtraction) {
                $pageStatus = 'completed';
            } elseif ($scrutinyRecord->status === 'pending') {
                $pageStatus = 'queued';
            }

            if (! array_key_exists($pageStatus, $summary)) {
                $pageStatus = 'unknown';
            }
            $summary[$pageStatus]++;

            $pages[] = [
                'page_number' => (int) ($file->page_number ?? 0),
                'scrutiny_record_file_id' => $file->id,
                'status' => $pageStatus,
                'extraction_id' => $latestExtraction?->id,
                'extraction_status' => $latestExtraction?->status,
                'updated_at' => is_array($meta) ? ($meta['updated_at'] ?? null) : null,
                'error' => $error,
            ];
        }

        $overallStatus = 'pending';
        if ($summary['failed'] > 0) {
            $overallStatus = 'failed';
        } elseif ($summary['total'] > 0 && $summary['completed'] === $summary['total']) {
            $overallStatus = 'completed';
        } elseif ($summary['processing'] > 0) {
            $overallStatus = 'processing';
        } elseif ($summary['queued'] > 0) {
            $overallStatus = 'queued';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'scrutiny_record_id' => $scrutinyRecord->id,
                'record_status' => $scrutinyRecord->status,
                'overall_status' => $overallStatus,
                'summary' => $summary,
                'pages' => $pages,
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
            'normalized_payload' => 'nullable',
        ]);

        $record = $this->resolveScrutinyRecord($request, $validated);
        $storageDisk = $this->storageDisk();

        // En el flujo actual de jurado (cola OCR sin payload manual) solo se admite pagina 1.
        $isDirectQueueFlow = ! array_key_exists('normalized_payload', $validated) || $validated['normalized_payload'] === null;
        if ($isDirectQueueFlow && (int) $validated['page_number'] !== 1) {
            throw ValidationException::withMessages([
                'page_number' => 'Para escrutinio jurado solo se admite una imagen y debe enviarse como pagina 1.',
            ]);
        }

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
        $normalizedPayload = $this->decodeJsonLikePayload($validated['normalized_payload'] ?? null);

        if ($normalizedPayload !== null && ! is_array($normalizedPayload)) {
            throw ValidationException::withMessages([
                'normalized_payload' => 'normalized_payload debe ser un objeto JSON valido.',
            ]);
        }

        $payload = [
            'scrutiny_record_id' => $record->id,
            'scrutiny_record_file_id' => $recordFile->id,
            'source_type' => $validated['source_type'] ?? 'ai',
            'engine_name' => $validated['engine_name'] ?? 'Jury-UI',
            'engine_version' => $validated['engine_version'] ?? 'web',
            'confidence_score' => $validated['confidence_score'] ?? 0.85,
            'status' => $validated['status'] ?? 'pending_review',
            'raw_payload' => $rawPayload,
            'normalized_payload' => is_array($normalizedPayload) ? $normalizedPayload : null,
            'notes' => $validated['notes'] ?? null,
        ];

        if (! is_array($payload['normalized_payload'] ?? null)) {
            throw ValidationException::withMessages([
                'normalized_payload' => 'Se requiere normalized_payload para guardar el escrutinio desde jurado.',
            ]);
        }

        $queueMode = 'direct';
        $this->updateIngestProcessingMetadata($record, $recordFile, 'processing');
        $importer->import($payload, (int) $request->user()->id);
        $this->updateIngestProcessingMetadata($record, $recordFile, 'completed');

        if ($record->status === 'draft') {
            $record->status = 'pending';
            $record->save();
        }

        $serverFilePath = null;
        try {
            $serverFilePath = Storage::disk($storageDisk)->path($recordFile->storage_path);
        } catch (Throwable) {
            // Cloud drivers may not expose a local absolute path.
            $serverFilePath = null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Carga recibida y procesada inmediatamente.',
            'data' => [
                'scrutiny_record_id' => $record->id,
                'scrutiny_record_file_id' => $recordFile->id,
                'storage_path' => $recordFile->storage_path,
                'server_file_path' => $serverFilePath,
                'download_url' => route('api.jury.scrutiny-files.show', $recordFile),
                'queued' => false,
                'queue_mode' => $queueMode,
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
        $documentType = $validated['document_type'] ?? 'escrutinio';

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
            $extractorScript = $documentType === 'plancha'
                ? 'data_extraction/extraer_candidatos.py'
                : 'data_extraction/motor_extraction.py';

            $process = new Process([
                $pythonBinary,
                base_path($extractorScript),
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
                $documentType
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
            $record = ScrutinyRecord::findOrFail((int) $validated['scrutiny_record_id']);

            if ((int) $record->created_by_user_id !== (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'scrutiny_record_id' => 'No puedes usar un acta creada por otro usuario.',
                ]);
            }

            return $record;
        }

        $pollingTableId = ! empty($validated['polling_table_id'])
            ? (int) $validated['polling_table_id']
            : ($this->resolveDefaultPollingTableId($request) ?? 0);

        if ($pollingTableId > 0) {
            $pollingTable = PollingTable::findOrFail($pollingTableId);
            $this->assertPollingTableAccess($request, $pollingTable);

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

    private function assertPollingTableAccess(Request $request, PollingTable $pollingTable): void
    {
        $userNeighborhoodId = (int) ($request->user()?->person?->neighborhood_id ?? 0);
        if ($userNeighborhoodId <= 0) {
            return;
        }

        $tableNeighborhoodId = (int) Election::query()
            ->where('id', $pollingTable->election_id)
            ->value('neighborhood_id');

        if ($tableNeighborhoodId !== $userNeighborhoodId) {
            throw ValidationException::withMessages([
                'polling_table_id' => 'Solo puedes cargar actas en la mesa asignada a tu barrio.',
            ]);
        }
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

    private function updateIngestProcessingMetadata(
        ScrutinyRecord $record,
        ScrutinyRecordFile $recordFile,
        string $status,
        ?string $error = null
    ): void {
        $metadata = is_array($record->metadata) ? $record->metadata : [];
        $processing = is_array($metadata['ingest_processing'] ?? null) ? $metadata['ingest_processing'] : [];
        $pageKey = (string) ($recordFile->page_number ?? $recordFile->id);

        $processing[$pageKey] = [
            'status' => $status,
            'scrutiny_record_file_id' => $recordFile->id,
            'updated_at' => now()->toIso8601String(),
        ];

        if ($error !== null && $error !== '') {
            $processing[$pageKey]['error'] = $error;
        }

        $metadata['ingest_processing'] = $processing;
        $record->metadata = $metadata;
        $record->save();
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
            $planchaBlocks = $normalizedPayload['plancha_blocks'] ?? [];
            if (is_array($planchaBlocks) && ! empty($planchaBlocks)) {
                $bloques = [];
                foreach ($planchaBlocks as $block) {
                    if (! is_array($block)) {
                        continue;
                    }

                    $cargos = [];
                    foreach ((array) ($block['cargos'] ?? []) as $cargo) {
                        if (! is_array($cargo)) {
                            continue;
                        }

                        $cargos[] = [
                            'puesto' => (string) ($cargo['puesto'] ?? 'SIN CARGO'),
                            'nombre' => (string) ($cargo['nombre'] ?? ''),
                            'identificacion' => (string) ($cargo['identificacion'] ?? ''),
                            'celular' => (string) ($cargo['celular'] ?? ''),
                            'correo' => (string) ($cargo['correo'] ?? ''),
                        ];
                    }

                    if (! empty($cargos)) {
                        $bloques[] = [
                            'titulo' => (string) ($block['titulo'] ?? 'Bloque - SIN BLOQUE'),
                            'cargos' => $cargos,
                        ];
                    }
                }

                return [
                    'bloques' => $bloques,
                ];
            }

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

        $baseEnv = array_merge($_SERVER, $_ENV);

        if (PHP_OS_FAMILY === 'Windows') {
            $baseEnv['SystemRoot'] = $baseEnv['SystemRoot'] ?? getenv('SystemRoot') ?: 'C:\\Windows';
            $baseEnv['WINDIR'] = $baseEnv['WINDIR'] ?? getenv('WINDIR') ?: $baseEnv['SystemRoot'];
        }

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
            'PATH' => (string) (getenv('PATH') ?: ''),
            'PYTHONUTF8' => '1',
        ];

        $normalizedBaseEnv = [];
        foreach ($baseEnv as $key => $value) {
            if (! is_string($key) || $key === '' || is_array($value) || is_object($value) || $value === null) {
                continue;
            }

            $normalizedBaseEnv[$key] = (string) $value;
        }

        return array_filter(
            array_merge($normalizedBaseEnv, $env),
            static fn ($value): bool => $value !== ''
        );
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
