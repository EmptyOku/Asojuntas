<?php

namespace App\Http\Controllers\Api\Secretary;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDraft;
use App\Models\CandidateDraftFile;
use App\Models\ElectionBlock;
use App\Models\ElectionBlockPosition;
use App\Models\Election;
use App\Models\Person;
use App\Models\Position;
use App\Models\Slate;
use App\Models\SlateBlock;
use App\Support\CandidateDraftWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PlanchaDraftController extends Controller
{
    public function previewExtraction(Request $request): JsonResponse
    {
        $this->extendExecutionTimeLimit();

        $maxFileSizeKb = (int) config('services.extractor.max_upload_kb', 10240);

        $validated = $request->validate([
            'document_file' => 'required|file|mimes:jpeg,png,jpg,webp|max:'.$maxFileSizeKb,
            'page_number' => 'nullable|integer|min:1',
        ]);

        $file = $request->file('document_file');
        $tempDir = storage_path('app/private/tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $tmpName = 'secretary_preview_'.uniqid('', true).'_'.$file->getClientOriginalName();
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
                base_path('data_extraction/extraer_candidatos.py'),
                '--image',
                $tmpPath,
                '--dry-run',
            ], base_path(), $this->buildExtractorProcessEnvironment(), null, 180);

            $process->run();

            if (! $process->isSuccessful()) {
                $errorDetail = trim($process->getErrorOutput() ?: $process->getOutput());
                $classified = $this->classifyExtractorError($errorDetail);

                Log::warning('secretary preview extractor failure', [
                    'status' => $classified['status'],
                    'error_code' => $classified['error_code'],
                    'retriable' => $classified['retriable'],
                    'python_bin' => $pythonBinary,
                    'detail' => $classified['detail'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $classified['message'] !== ''
                        ? 'Fallo el extractor de candidatos: '.$classified['message']
                        : 'Fallo el extractor de candidatos.',
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
                    'message' => 'La salida del extractor no es JSON valido.',
                    'raw' => $stdout,
                    'error_code' => 'extractor_invalid_json',
                    'retriable' => false,
                ], 502);
            }

            $normalizedPayload = $json['normalized_payload'] ?? [];
            $pageData = $this->mapNormalizedToReviewPage(is_array($normalizedPayload) ? $normalizedPayload : []);

            return response()->json([
                'success' => true,
                'message' => 'Extraccion preliminar de plancha completada.',
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

    public function storeDrafts(Request $request): JsonResponse
    {
        $this->extendExecutionTimeLimit();

        $validated = $request->validate([
            'election_id' => 'nullable|exists:elections,id',
            'document_type_id' => 'nullable|exists:document_types,id',
            'slate_code' => 'nullable|string|max:20',
            'capture_batch_uuid' => 'nullable|uuid',
            'source_type' => 'nullable|string|in:ocr,manual,api',
            'confidence_score' => 'nullable|numeric|min:0|max:100',
            'review_page_data' => 'required|array',
            'review_page_data.bloques' => 'required|array|min:1',
            'replace_pending' => 'sometimes|boolean',
        ]);

        $electionId = $this->resolveElectionId($validated['election_id'] ?? null);
        if (! $electionId) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo resolver election_id. Envia election_id o crea una eleccion activa.',
            ], 422);
        }

        $replacePending = (bool) ($validated['replace_pending'] ?? false);
        $captureBatchUuid = (string) ($validated['capture_batch_uuid'] ?? Str::uuid());
        $slateCode = $this->normalizeSlateCode((string) ($validated['slate_code'] ?? ''));

        if ($replacePending) {
            CandidateDraft::query()
                ->where('election_id', $electionId)
                ->where('review_status', CandidateDraftWorkflow::STATUS_PENDING)
                ->where('is_processed', false)
                ->where('source_type', $validated['source_type'] ?? 'ocr')
            ->where('capture_batch_uuid', $captureBatchUuid)
                ->delete();
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rows = [];

        foreach ((array) ($validated['review_page_data']['bloques'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockTitle = trim((string) ($block['titulo'] ?? ''));

            foreach ((array) ($block['cargos'] ?? []) as $cargo) {
                if (! is_array($cargo)) {
                    continue;
                }

                $fullName = trim((string) ($cargo['nombre'] ?? ''));
                if ($fullName === '') {
                    $skipped++;
                    continue;
                }

                $nameParts = $this->splitPersonName($fullName);
                $documentNumber = $this->normalizeDocumentNumber((string) ($cargo['identificacion'] ?? ''));
                $documentTypeId = $validated['document_type_id'] ?? null;

                $personId = null;
                if ($documentNumber !== null) {
                    $personId = $this->resolvePersonId($documentNumber, $documentTypeId);
                }

                $positionContext = $this->resolvePositionContextForCargo(
                    $electionId,
                    (string) ($cargo['puesto'] ?? ''),
                    $slateCode
                );

                $baseData = [
                    'election_id' => $electionId,
                    'block_id' => $positionContext['block_id'],
                    'position_id' => $positionContext['position_id'],
                    'slate_id' => $positionContext['slate_id'],
                    'slate_block_id' => $positionContext['slate_block_id'],
                    'capture_batch_uuid' => $captureBatchUuid,
                    'document_type_id' => $documentTypeId,
                    'person_id' => $personId,
                    'document_number' => $documentNumber,
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'second_last_name' => $nameParts['second_last_name'],
                    'phone' => $this->normalizeNullable((string) ($cargo['celular'] ?? '')),
                    'email' => $this->normalizeNullable((string) ($cargo['correo'] ?? '')),
                    'source_type' => $validated['source_type'] ?? 'ocr',
                    'confidence_score' => $validated['confidence_score'] ?? null,
                    'review_status' => CandidateDraftWorkflow::STATUS_PENDING,
                    'is_processed' => false,
                    'processed_at' => null,
                    'notes' => $this->buildDraftNote($blockTitle, (string) ($cargo['puesto'] ?? '')),
                ];

                $existing = CandidateDraft::query()
                    ->where('election_id', $electionId)
                    ->where('review_status', CandidateDraftWorkflow::STATUS_PENDING)
                    ->where('is_processed', false)
                    ->where('source_type', $baseData['source_type'])
                    ->where('first_name', $baseData['first_name'])
                    ->where('last_name', $baseData['last_name'])
                    ->where('capture_batch_uuid', $captureBatchUuid)
                    ->when($documentNumber !== null, fn ($q) => $q->where('document_number', $documentNumber))
                    ->first();

                if ($existing) {
                    $existing->update($baseData);
                    $updated++;
                    $rows[] = $existing;
                    continue;
                }

                $draft = CandidateDraft::create($baseData);
                $created++;
                $rows[] = $draft;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Borradores de plancha guardados correctamente.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'capture_batch_uuid' => $captureBatchUuid,
                'drafts' => $rows,
            ],
        ], 201);
    }

    public function uploadDraftFiles(Request $request): JsonResponse
    {
        $this->extendExecutionTimeLimit();

        $maxFileSizeKb = (int) config('services.extractor.max_upload_kb', 10240);

        $validated = $request->validate([
            'capture_batch_uuid' => 'required|uuid',
            'election_id' => 'nullable|exists:elections,id',
            'document_files' => 'required|array|min:1',
            'document_files.*' => 'required|file|mimes:jpeg,png,jpg,webp|max:'.$maxFileSizeKb,
            'page_numbers' => 'nullable|array',
            'page_numbers.*' => 'nullable|integer|min:1',
        ]);

        $captureBatchUuid = (string) $validated['capture_batch_uuid'];
        $electionId = $validated['election_id'] ?? null;
        $storageDisk = $this->storageDisk();

        $created = 0;
        $updated = 0;
        $files = [];

        foreach ($request->file('document_files') as $index => $file) {
            $hash = hash_file('sha256', $file->getRealPath());
            $pageNumber = (int) (($validated['page_numbers'][$index] ?? ($index + 1)) ?: ($index + 1));

            $path = $file->store(
                'planchas/'.($electionId ?? 'sin-eleccion').'/batch-'.$captureBatchUuid,
                $storageDisk
            );

            $existing = CandidateDraftFile::query()
                ->where('capture_batch_uuid', $captureBatchUuid)
                ->where('hash', $hash)
                ->first();

            if ($existing) {
                $existing->update([
                    'election_id' => $electionId,
                    'uploaded_by_user_id' => $request->user()?->id,
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'page_number' => $pageNumber,
                ]);
                $updated++;
                $files[] = $existing->fresh();
                continue;
            }

            $record = CandidateDraftFile::create([
                'capture_batch_uuid' => $captureBatchUuid,
                'election_id' => $electionId,
                'uploaded_by_user_id' => $request->user()?->id,
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'hash' => $hash,
                'page_number' => $pageNumber,
            ]);

            $created++;
            $files[] = $record;
        }

        return response()->json([
            'success' => true,
            'message' => 'Evidencias de plancha cargadas correctamente.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'capture_batch_uuid' => $captureBatchUuid,
                'files' => $files,
            ],
        ], 201);
    }

    public function listEvidenceByBatch(string $captureBatchUuid): JsonResponse
    {
        $files = CandidateDraftFile::query()
            ->where('capture_batch_uuid', $captureBatchUuid)
            ->orderBy('page_number')
            ->orderBy('id')
            ->get()
            ->map(function (CandidateDraftFile $file): array {
                return [
                    'id' => $file->id,
                    'page_number' => $file->page_number,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'download_url' => route('api.secretary.planchas.evidence.show', $file),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'capture_batch_uuid' => $captureBatchUuid,
                'files' => $files,
            ],
        ]);
    }

    public function showEvidenceFile(CandidateDraftFile $candidateDraftFile)
    {
        $storageDisk = $this->resolveStorageDisk($candidateDraftFile->storage_path);

        if ($storageDisk === null) {
            abort(404, 'El archivo de evidencia no existe en el servidor.');
        }

        return Storage::disk($storageDisk)->response(
            $candidateDraftFile->storage_path,
            $candidateDraftFile->original_name ?: ('evidencia_'.$candidateDraftFile->id)
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->extendExecutionTimeLimit();

        $request->validate([
            'draft_id' => 'nullable|integer|exists:candidate_drafts,id',
            'election_id' => 'nullable|integer|exists:elections,id',
            'capture_batch_uuid' => 'nullable|uuid',
            'review_status' => 'nullable|string|in:pending,approved,rejected',
            'is_processed' => 'nullable|boolean',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CandidateDraft::query()->with(['election', 'person', 'documentType', 'slate', 'position']);

        if ($request->filled('draft_id')) {
            $query->where('id', (int) $request->input('draft_id'));
        }

        if ($request->filled('election_id')) {
            $query->where('election_id', (int) $request->input('election_id'));
        }

        if ($request->filled('capture_batch_uuid')) {
            $query->where('capture_batch_uuid', (string) $request->input('capture_batch_uuid'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', (string) $request->input('review_status'));
        }

        if ($request->has('is_processed')) {
            $query->where('is_processed', filter_var($request->input('is_processed'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term): void {
                $q->where('first_name', 'ilike', "%{$term}%")
                    ->orWhere('last_name', 'ilike', "%{$term}%")
                    ->orWhere('document_number', 'ilike', "%{$term}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $drafts = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $drafts,
        ]);
    }

    public function update(Request $request, CandidateDraft $candidateDraft): JsonResponse
    {
        if ($candidateDraft->is_processed) {
            return response()->json([
                'success' => false,
                'message' => 'El borrador ya esta procesado y no se puede editar.',
            ], 409);
        }

        $validated = $request->validate([
            'document_type_id' => 'nullable|exists:document_types,id',
            'document_number' => 'nullable|string|max:30',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'notes' => 'nullable|string|max:2000',
        ]);

        $documentNumber = $this->normalizeDocumentNumber((string) ($validated['document_number'] ?? ''));
        $documentTypeId = $validated['document_type_id'] ?? $candidateDraft->document_type_id;
        $personId = $documentNumber ? $this->resolvePersonId($documentNumber, $documentTypeId) : null;

        $candidateDraft->update([
            'document_type_id' => $documentTypeId,
            'document_number' => $documentNumber,
            'first_name' => trim((string) $validated['first_name']),
            'middle_name' => $this->normalizeNullable((string) ($validated['middle_name'] ?? '')),
            'last_name' => trim((string) $validated['last_name']),
            'second_last_name' => $this->normalizeNullable((string) ($validated['second_last_name'] ?? '')),
            'phone' => $this->normalizeNullable((string) ($validated['phone'] ?? '')),
            'email' => $this->normalizeNullable((string) ($validated['email'] ?? '')),
            'notes' => $validated['notes'] ?? $candidateDraft->notes,
            'person_id' => $personId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Borrador actualizado.',
            'data' => $candidateDraft->fresh(),
        ]);
    }

    public function decide(Request $request, CandidateDraft $candidateDraft): JsonResponse
    {
        $this->extendExecutionTimeLimit();

        $validated = $request->validate([
            'decision' => 'required|string|in:approved,rejected',
            'notes' => 'nullable|string|max:2000',
        ]);

        $target = (string) $validated['decision'];
        if (! CandidateDraftWorkflow::canApplyDecision((string) $candidateDraft->review_status, $target, (bool) $candidateDraft->is_processed)) {
            return response()->json([
                'success' => false,
                'message' => 'Transicion invalida para el estado actual del borrador.',
            ], 422);
        }

        $updates = [
            'review_status' => $target,
            'notes' => $validated['notes'] ?? $candidateDraft->notes,
        ];

        if ($target === CandidateDraftWorkflow::STATUS_REJECTED) {
            $updates['is_processed'] = true;
            $updates['processed_at'] = Carbon::now();
        }

        $candidateDraft->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Decision aplicada correctamente.',
            'data' => $candidateDraft->fresh(),
        ]);
    }

    public function decideBatch(Request $request): JsonResponse
    {
        $this->extendExecutionTimeLimit();

        $validated = $request->validate([
            'decision' => 'required|string|in:approved,rejected',
            'capture_batch_uuid' => 'required|uuid',
            'notes' => 'nullable|string|max:2000',
        ]);

        $target = (string) $validated['decision'];
        $batchUuid = (string) $validated['capture_batch_uuid'];

        $drafts = CandidateDraft::query()
            ->where('capture_batch_uuid', $batchUuid)
            ->where('review_status', CandidateDraftWorkflow::STATUS_PENDING)
            ->where('is_processed', false)
            ->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No hay borradores pendientes para decidir en este lote.',
                'data' => [
                    'updated' => 0,
                    'batch_uuid' => $batchUuid,
                ],
            ]);
        }

        $updated = 0;

        DB::transaction(function () use ($drafts, $target, $validated, &$updated): void {
            foreach ($drafts as $draft) {
                if (! CandidateDraftWorkflow::canApplyDecision((string) $draft->review_status, $target, (bool) $draft->is_processed)) {
                    continue;
                }

                $payload = [
                    'review_status' => $target,
                    'notes' => $validated['notes'] ?? $draft->notes,
                ];

                if ($target === CandidateDraftWorkflow::STATUS_REJECTED) {
                    $payload['is_processed'] = true;
                    $payload['processed_at'] = Carbon::now();
                }

                $draft->update($payload);
                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Decision por lote aplicada correctamente.',
            'data' => [
                'updated' => $updated,
                'batch_uuid' => $batchUuid,
                'decision' => $target,
            ],
        ]);
    }

    public function promoteApproved(Request $request): JsonResponse
    {
        $this->extendExecutionTimeLimit();

        $validated = $request->validate([
            'election_id' => 'nullable|exists:elections,id',
            'capture_batch_uuid' => 'nullable|uuid',
            'draft_ids' => 'nullable|array',
            'draft_ids.*' => 'integer|exists:candidate_drafts,id',
        ]);

        $query = CandidateDraft::query()
            ->where('review_status', CandidateDraftWorkflow::STATUS_APPROVED)
            ->where('is_processed', false);

        if (! empty($validated['election_id'])) {
            $query->where('election_id', (int) $validated['election_id']);
        }

        if (! empty($validated['capture_batch_uuid'])) {
            $query->where('capture_batch_uuid', (string) $validated['capture_batch_uuid']);
        }

        if (! empty($validated['draft_ids'])) {
            $query->whereIn('id', $validated['draft_ids']);
        }

        $drafts = $query->orderBy('id')->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No hay borradores aprobados pendientes por promover.',
                'data' => [
                    'processed' => 0,
                    'persons_created' => 0,
                    'candidates_created' => 0,
                    'candidates_existing' => 0,
                    'skipped' => 0,
                    'issues' => [],
                ],
            ]);
        }

        $personsCreated = 0;
        $candidatesCreated = 0;
        $candidatesExisting = 0;
        $processed = 0;
        $skipped = 0;
        $issues = [];

        DB::transaction(function () use (
            $drafts,
            &$personsCreated,
            &$candidatesCreated,
            &$candidatesExisting,
            &$processed,
            &$skipped,
            &$issues
        ): void {
            foreach ($drafts as $draft) {
                if (! $draft->document_number || ! $draft->document_type_id) {
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => 'Sin documento o tipo de documento para deduplicar persona.',
                    ];
                    continue;
                }

                if (! $draft->slate_block_id || ! $draft->position_id) {
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => 'Sin mapeo de plancha/cargo. Corrige el borrador antes de promover.',
                    ];
                    continue;
                }

                $electionBlockPositionId = ElectionBlockPosition::query()
                    ->join('slate_blocks', 'slate_blocks.election_block_id', '=', 'election_block_positions.election_block_id')
                    ->where('slate_blocks.id', $draft->slate_block_id)
                    ->where('election_block_positions.position_id', $draft->position_id)
                    ->value('election_block_positions.id');

                if (! $electionBlockPositionId) {
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => 'No existe el cargo en el bloque electoral de la plancha.',
                    ];
                    continue;
                }

                $person = Person::query()->firstOrCreate(
                    [
                        'document_type_id' => $draft->document_type_id,
                        'document_number' => (string) $draft->document_number,
                    ],
                    [
                        'first_name' => $draft->first_name,
                        'middle_name' => $draft->middle_name,
                        'last_name' => $draft->last_name,
                        'second_last_name' => $draft->second_last_name,
                        'phone' => $draft->phone,
                        'email' => $draft->email,
                        'is_active' => true,
                    ]
                );

                if ($person->wasRecentlyCreated) {
                    $personsCreated++;
                }

                $candidate = Candidate::query()->firstOrCreate(
                    [
                        'election_id' => $draft->election_id,
                        'person_id' => $person->id,
                    ],
                    [
                        'slate_block_id' => $draft->slate_block_id,
                        'election_block_position_id' => $electionBlockPositionId,
                        'ballot_number' => null,
                        'is_active' => true,
                    ]
                );

                if ($candidate->wasRecentlyCreated) {
                    $candidatesCreated++;
                } else {
                    $candidatesExisting++;
                }

                $draft->update([
                    'person_id' => $person->id,
                    'is_processed' => true,
                    'processed_at' => Carbon::now(),
                ]);

                $processed++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Promocion oficial completada.',
            'data' => [
                'processed' => $processed,
                'persons_created' => $personsCreated,
                'candidates_created' => $candidatesCreated,
                'candidates_existing' => $candidatesExisting,
                'skipped' => $skipped,
                'issues' => $issues,
            ],
        ]);
    }

    private function mapNormalizedToReviewPage(array $normalizedPayload): array
    {
        $planchaBlocks = $normalizedPayload['plancha_blocks'] ?? [];
        if (! is_array($planchaBlocks)) {
            return ['bloques' => []];
        }

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

        return ['bloques' => $bloques];
    }

    private function resolveElectionId(?int $requestedElectionId): ?int
    {
        if ($requestedElectionId) {
            return Election::query()->where('id', $requestedElectionId)->value('id');
        }

        $active = Election::query()
            ->where('is_active', true)
            ->latest('election_date')
            ->value('id');

        if ($active) {
            return (int) $active;
        }

        $latest = Election::query()->latest('id')->value('id');

        return $latest ? (int) $latest : null;
    }

    private function splitPersonName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        $firstName = $parts[0] ?? 'SIN_NOMBRE';
        $middleName = null;
        $lastName = 'SIN_APELLIDO';
        $secondLastName = null;

        if (count($parts) >= 2) {
            $lastName = $parts[count($parts) - 1];
            if (count($parts) === 3) {
                $middleName = $parts[1];
            }
            if (count($parts) >= 4) {
                $middleName = implode(' ', array_slice($parts, 1, count($parts) - 3));
                $secondLastName = $parts[count($parts) - 2];
            }
        }

        return [
            'first_name' => Str::upper(trim((string) $firstName)),
            'middle_name' => $this->normalizeNullable((string) $middleName),
            'last_name' => Str::upper(trim((string) $lastName)),
            'second_last_name' => $this->normalizeNullable((string) $secondLastName),
        ];
    }

    private function normalizeSlateCode(string $slateCode): ?string
    {
        $value = strtoupper(trim($slateCode));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return 'P'.$value;
        }

        if (preg_match('/^P\d+$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    private function normalizeDocumentNumber(string $value): ?string
    {
        $digits = preg_replace('/[^\d]/', '', $value) ?? '';
        return $digits !== '' ? $digits : null;
    }

    private function normalizeNullable(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function resolvePersonId(string $documentNumber, ?int $documentTypeId): ?int
    {
        $query = Person::query()->where('document_number', $documentNumber);

        if ($documentTypeId) {
            $query->where('document_type_id', $documentTypeId);
        }

        $found = $query->value('id');

        return $found ? (int) $found : null;
    }

    private function resolvePositionContextForCargo(int $electionId, string $cargoLabel, ?string $slateCode): array
    {
        $normalizedCargo = Str::upper(trim($cargoLabel));
        $positionCode = null;
        $blockCode = null;

        $map = [
            'PRESIDENTE' => ['DIR_PRES', 'DIR'],
            'VICEPRESIDENTE' => ['DIR_VICE', 'DIR'],
            'TESORERO' => ['DIR_TESO', 'DIR'],
            'DELEGADO ASOJUNTAS 1' => ['DEL_1', 'DEL'],
            'DELEGADO ASOJUNTAS 2' => ['DEL_2', 'DEL'],
            'FISCAL' => ['FIS_PRIN', 'FIS'],
        ];

        if (isset($map[$normalizedCargo])) {
            [$positionCode, $blockCode] = $map[$normalizedCargo];
        }

        $positionId = null;
        $blockId = null;
        $slateId = null;
        $slateBlockId = null;

        if ($positionCode !== null) {
            $position = Position::query()->where('code', $positionCode)->first();
            $positionId = $position?->id;
            $blockId = $position?->block_id;
        }

        if ($slateCode !== null) {
            $slate = Slate::query()
                ->where('election_id', $electionId)
                ->whereRaw('UPPER(code) = ?', [$slateCode])
                ->first();

            $slateId = $slate?->id;

            if ($slateId && $blockCode) {
                $electionBlockId = ElectionBlock::query()
                    ->join('blocks', 'blocks.id', '=', 'election_blocks.block_id')
                    ->where('election_blocks.election_id', $electionId)
                    ->whereRaw('UPPER(blocks.code) = ?', [$blockCode])
                    ->value('election_blocks.id');

                if ($electionBlockId) {
                    $slateBlockId = SlateBlock::query()
                        ->where('election_id', $electionId)
                        ->where('slate_id', $slateId)
                        ->where('election_block_id', $electionBlockId)
                        ->value('id');
                }
            }
        }

        return [
            'position_id' => $positionId,
            'block_id' => $blockId,
            'slate_id' => $slateId,
            'slate_block_id' => $slateBlockId,
        ];
    }

    private function buildDraftNote(string $blockTitle, string $cargoLabel): string
    {
        $cleanBlock = trim($blockTitle) !== '' ? trim($blockTitle) : 'SIN BLOQUE';
        $cleanCargo = trim($cargoLabel) !== '' ? trim($cargoLabel) : 'SIN CARGO';

        return "OCR Secretaria | {$cleanBlock} | Cargo: {$cleanCargo}";
    }

    private function extendExecutionTimeLimit(): void
    {
        $seconds = max(60, (int) config('services.extractor.request_timeout_seconds', 180));

        @ini_set('max_execution_time', (string) $seconds);
        @set_time_limit($seconds);
    }

    private function storageDisk(): string
    {
        return (string) config('services.extractor.storage_disk', config('filesystems.default', 'local'));
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

        $decoded = json_decode($detail, true);
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
