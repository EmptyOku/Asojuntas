<?php

namespace App\Http\Controllers\Api\Secretary;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDraft;
use App\Models\CandidateDraftFile;
use App\Models\DocumentType;
use App\Models\ElectionBlock;
use App\Models\ElectionBlockPosition;
use App\Models\Election;
use App\Models\Neighborhood;
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
                $documentTypeId = $validated['document_type_id'] ?? $this->resolveDefaultDocumentTypeId();

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
            'per_page' => 'nullable|integer|min:1|max:20',
        ]);

        $isDetailedRequest = $request->filled('draft_id') || $request->filled('capture_batch_uuid');

        $query = CandidateDraft::query();

        if ($isDetailedRequest) {
            $query->with(['election.neighborhood', 'person', 'documentType', 'slate', 'position']);
        } else {
            $query
                ->leftJoin('elections', 'candidate_drafts.election_id', '=', 'elections.id')
                ->leftJoin('neighborhoods', 'elections.neighborhood_id', '=', 'neighborhoods.id')
                ->select([
                    'candidate_drafts.id',
                    'candidate_drafts.capture_batch_uuid',
                    'candidate_drafts.election_id',
                    'candidate_drafts.document_number',
                    'candidate_drafts.first_name',
                    'candidate_drafts.middle_name',
                    'candidate_drafts.last_name',
                    'candidate_drafts.second_last_name',
                    'candidate_drafts.review_status',
                    'candidate_drafts.is_processed',
                    'candidate_drafts.notes',
                    'candidate_drafts.created_at',
                    'neighborhoods.name as neighborhood_name',
                ]);
        }

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
            $query->where('candidate_drafts.is_processed', filter_var($request->input('is_processed'), FILTER_VALIDATE_BOOLEAN));
        } elseif (! $isDetailedRequest) {
            // Para bandeja principal, por defecto solo pendientes de proceso para no cargar histórico masivo.
            $query->where('candidate_drafts.is_processed', false);
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term): void {
                $q->where('candidate_drafts.first_name', 'ilike', "%{$term}%")
                    ->orWhere('candidate_drafts.last_name', 'ilike', "%{$term}%")
                    ->orWhere('candidate_drafts.document_number', 'ilike', "%{$term}%");

                if (! $isDetailedRequest) {
                    $q->orWhere('neighborhoods.name', 'ilike', "%{$term}%");
                }
            });
        }

        $perPage = max(1, min(20, (int) $request->integer('per_page', 15)));
        $drafts = $query->orderByDesc('candidate_drafts.id')->simplePaginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $drafts,
        ]);
    }

    public function neighborhoodsWithSlates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:20',
        ]);

        $perPage = max(1, min(20, (int) ($validated['per_page'] ?? 12)));

        $neighborhoodQuery = Neighborhood::query()
            ->select(['id', 'name', 'code', 'commune_id'])
            ->with(['commune:id,name'])
            ->whereHas('elections', function ($electionQuery): void {
                $electionQuery->active()->whereHas('candidates', function ($candidateQuery): void {
                    $candidateQuery->where('is_active', true)
                        ->whereNotNull('slate_block_id')
                        ->whereNotNull('election_block_position_id');
                });
            });

        if (! empty($validated['search'])) {
            $term = trim((string) $validated['search']);
            $neighborhoodQuery->where(function ($query) use ($term): void {
                $query->where('name', 'ilike', "%{$term}%")
                    ->orWhere('code', 'ilike', "%{$term}%");
            });
        }

        $neighborhoods = $neighborhoodQuery->orderBy('name')->paginate($perPage);
        $neighborhoodIds = collect($neighborhoods->items())->pluck('id')->all();

        $candidates = collect();
        if (! empty($neighborhoodIds)) {
            $candidates = Candidate::query()
                ->select([
                    'id',
                    'election_id',
                    'person_id',
                    'slate_block_id',
                    'election_block_position_id',
                    'ballot_number',
                    'is_active',
                ])
                ->with([
                    'election:id,neighborhood_id,is_active',
                    'person:id,first_name,middle_name,last_name,second_last_name,document_number',
                    'slateBlock:id,election_id,slate_id,election_block_id',
                    'slateBlock.slate:id,name,code',
                    'electionBlockPosition:id,position_id',
                    'electionBlockPosition.position:id,name,code',
                ])
                ->where('is_active', true)
                ->whereNotNull('slate_block_id')
                ->whereNotNull('election_block_position_id')
                ->whereHas('election', function ($electionQuery) use ($neighborhoodIds): void {
                    $electionQuery->active()->whereIn('neighborhood_id', $neighborhoodIds);
                })
                ->orderBy('id')
                ->get();
        }

        $candidatesByNeighborhood = $candidates->groupBy(function (Candidate $candidate): string {
            return (string) ($candidate->election?->neighborhood_id ?? '0');
        });

        $items = collect($neighborhoods->items())->map(function (Neighborhood $neighborhood) use ($candidatesByNeighborhood): array {
            $neighborhoodCandidates = $candidatesByNeighborhood->get((string) $neighborhood->id, collect());

            $slates = $neighborhoodCandidates
                ->groupBy(function (Candidate $candidate): string {
                    return (string) ($candidate->slateBlock?->slate_id ?? 0);
                })
                ->map(function ($slateCandidates): array {
                    $firstCandidate = $slateCandidates->first();
                    $slate = $firstCandidate?->slateBlock?->slate;

                    $representatives = $slateCandidates->map(function (Candidate $candidate): array {
                        $person = $candidate->person;
                        $fullName = trim(implode(' ', array_filter([
                            $person?->first_name,
                            $person?->middle_name,
                            $person?->last_name,
                            $person?->second_last_name,
                        ])));

                        return [
                            'id' => $candidate->id,
                            'name' => $fullName !== '' ? $fullName : 'Sin nombre',
                            'position' => $candidate->electionBlockPosition?->position?->name ?? 'Sin cargo',
                            'ballot_number' => $candidate->ballot_number,
                        ];
                    })->values();

                    return [
                        'id' => $slate?->id,
                        'code' => $slate?->code,
                        'name' => $slate?->name,
                        'label' => $slate?->code ? 'Plancha '.$slate->code : 'Plancha sin código',
                        'representatives' => $representatives,
                    ];
                })
                ->values();

            return [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'code' => $neighborhood->code,
                'commune' => $neighborhood->commune ? [
                    'id' => $neighborhood->commune->id,
                    'name' => $neighborhood->commune->name,
                ] : null,
                'slates' => $slates,
            ];
        })->filter(fn (array $neighborhood) => $neighborhood['slates']->isNotEmpty())->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $neighborhoods->currentPage(),
                    'last_page' => $neighborhoods->lastPage(),
                    'per_page' => $neighborhoods->perPage(),
                    'total' => $neighborhoods->total(),
                    'from' => $neighborhoods->firstItem() ?? 0,
                    'to' => $neighborhoods->lastItem() ?? 0,
                ],
            ],
        ]);
    }

    public function update(Request $request, CandidateDraft $candidateDraft): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'nullable|exists:document_types,id',
            'document_number' => 'nullable|string|max:30',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $documentNumber = $this->normalizeDocumentNumber((string) ($validated['document_number'] ?? ''));
        $documentTypeId = $validated['document_type_id'] ?? $candidateDraft->document_type_id ?? $this->resolveDefaultDocumentTypeId();
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
                if (! $draft->document_number) {
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => 'Sin documento para deduplicar persona.',
                    ];
                    continue;
                }

                $documentTypeId = $draft->document_type_id ?? $this->resolveDefaultDocumentTypeId();

                $positionId = $draft->position_id;
                if (! $positionId) {
                    $cargoLabel = $this->extractCargoLabelFromNotes((string) ($draft->notes ?? ''));
                    if ($cargoLabel !== null) {
                        $positionContext = $this->resolvePositionContextForCargo($draft->election_id, $cargoLabel, null);
                        $positionId = $positionContext['position_id'];
                    }
                }

                $positionBlockId = null;
                $electionBlockId = null;
                if ($positionId) {
                    $mapping = $this->ensureElectionStructureForPosition($draft->election_id, (int) $positionId);
                    $positionBlockId = $mapping['block_id'];
                    $electionBlockId = $mapping['election_block_id'];
                }

                $slateBlockId = $draft->slate_block_id;
                if (! $slateBlockId && $draft->slate_id) {
                    $slateBlockId = SlateBlock::query()
                        ->where('election_id', $draft->election_id)
                        ->where('slate_id', $draft->slate_id)
                        ->value('id');
                }

                if (! $slateBlockId && $draft->capture_batch_uuid) {
                    $batchSlateBlockIds = CandidateDraft::query()
                        ->where('capture_batch_uuid', $draft->capture_batch_uuid)
                        ->where('election_id', $draft->election_id)
                        ->whereNotNull('slate_block_id')
                        ->distinct()
                        ->pluck('slate_block_id');

                    if ($batchSlateBlockIds->count() === 1) {
                        $slateBlockId = (int) $batchSlateBlockIds->first();
                    }
                }

                if (! $slateBlockId && $positionId && $electionBlockId) {
                    $candidateSlateBlocks = SlateBlock::query()
                        ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
                        ->where('slate_blocks.election_id', $draft->election_id)
                        ->where('slate_blocks.election_block_id', $electionBlockId)
                        ->where('slates.is_active', true)
                        ->pluck('slate_blocks.id');

                    if ($candidateSlateBlocks->count() === 1) {
                        $slateBlockId = (int) $candidateSlateBlocks->first();
                    } elseif ($candidateSlateBlocks->count() > 1) {
                        $slateBlockId = $this->resolvePreferredSlateBlockId($draft->election_id, (int) $electionBlockId);
                    }
                }

                if (! $positionId) {
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => 'Sin mapeo de cargo OCR hacia una posicion oficial.',
                    ];
                    continue;
                }

                if (! $slateBlockId) {
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => 'Sin mapeo de plancha para el cargo. Verifica codigo de plancha o define una plancha activa unica.',
                    ];
                    continue;
                }

                $electionBlockPositionId = ElectionBlockPosition::query()
                    ->where('election_block_id', $electionBlockId)
                    ->where('position_id', $positionId)
                    ->value('id');

                if (! $electionBlockPositionId) {
                    $positionBlockId = $positionBlockId ?: Position::query()->where('id', $positionId)->value('block_id');

                    if ($electionBlockId && $positionBlockId) {
                        $ebp = ElectionBlockPosition::query()->updateOrCreate(
                            [
                                'election_block_id' => $electionBlockId,
                                'position_id' => $positionId,
                            ],
                            [
                                'block_id' => $positionBlockId,
                                'vacancies' => 1,
                                'is_active' => true,
                            ]
                        );

                        $electionBlockPositionId = $ebp->id;
                    }
                }

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
                        'document_type_id' => $documentTypeId,
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

                $candidate = Candidate::query()->updateOrCreate(
                    [
                        'election_id' => $draft->election_id,
                        'person_id' => $person->id,
                    ],
                    [
                        'slate_block_id' => $slateBlockId,
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
                    'document_type_id' => $documentTypeId,
                    'position_id' => $positionId,
                    'slate_block_id' => $slateBlockId,
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
            $query->where('document_type_id', $documentTypeId ?? $this->resolveDefaultDocumentTypeId());
        }

        $found = $query->value('id');

        return $found ? (int) $found : null;
    }

    private function resolveDefaultDocumentTypeId(): ?int
    {
        return DocumentType::query()
            ->where('code', 'CC')
            ->value('id');
    }

    private function resolvePositionContextForCargo(int $electionId, string $cargoLabel, ?string $slateCode): array
    {
        $normalizedCargo = (string) Str::of($cargoLabel)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9 ]+/', ' ')
            ->squish();
        $positionCode = null;
        $blockCode = null;

        $map = [
            'PRESIDENTE' => ['DIR_PRES', 'DIR'],
            'SUPLENTE DE PRESIDENTE' => ['DIR_PRES', 'DIR'],
            'VICEPRESIDENTE' => ['DIR_VICE', 'DIR'],
            'SUPLENTE DE VICEPRESIDENTE' => ['DIR_VICE', 'DIR'],
            'TESORERO' => ['DIR_TESO', 'DIR'],
            'SUPLENTE DE TESORERO' => ['DIR_TESO', 'DIR'],
            'SECRETARIO' => ['DIR_SECR', 'DIR'],
            'SUPLENTE DE SECRETARIO' => ['DIR_SECR', 'DIR'],
            'DELEGADO ASOJUNTAS 1' => ['DEL_AJ_1', 'DEL'],
            'SUPLENTE DELEGADO ASOJUNTAS 1' => ['DEL_AJ_1', 'DEL'],
            'DELEGADO ASOJUNTAS 2' => ['DEL_AJ_2', 'DEL'],
            'SUPLENTE DELEGADO ASOJUNTAS 2' => ['DEL_AJ_2', 'DEL'],
            'DELEGADO ASOJUNTAS 3' => ['DEL_AJ_3', 'DEL'],
            'SUPLENTE DELEGADO ASOJUNTAS 3' => ['DEL_AJ_3', 'DEL'],
            'FISCAL' => ['FIS_PRIN', 'FIS'],
            'SUPLENTE FISCAL' => ['FIS_PRIN', 'FIS'],
            'CONCILIADOR 1' => ['CYC_CONC_1', 'CYC'],
            'CONCILIADOR 2' => ['CYC_CONC_2', 'CYC'],
            'CONCILIADOR 3' => ['CYC_CONC_3', 'CYC'],
            'COMISION EMPRESARIAL' => ['CYC_EMP_COORD', 'CYC'],
            'COORDINADOR COMISION EMPRESARIAL' => ['CYC_EMP_COORD', 'CYC'],
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
            $normalizedSlateCode = Str::upper(trim($slateCode));

            $slate = Slate::query()
                ->where('election_id', $electionId)
                ->where(function ($query) use ($normalizedSlateCode): void {
                    $query->whereRaw('UPPER(code) = ?', [$normalizedSlateCode])
                        ->orWhereRaw('UPPER(name) = ?', [$normalizedSlateCode]);

                    if (preg_match('/^P(\d+)$/', $normalizedSlateCode, $matches) === 1) {
                        $number = (int) $matches[1];
                        $query->orWhereRaw('UPPER(code) LIKE ?', ["%PL{$number}%"])
                            ->orWhereRaw('UPPER(name) LIKE ?', ["%{$number}%"]);
                    }
                })
                ->orderBy('id')
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

    private function extractCargoLabelFromNotes(string $notes): ?string
    {
        if ($notes === '') {
            return null;
        }

        if (preg_match('/Cargo:\s*(.+)$/i', $notes, $matches) !== 1) {
            return null;
        }

        $label = trim((string) ($matches[1] ?? ''));

        return $label !== '' ? $label : null;
    }

    private function resolvePreferredSlateBlockId(int $electionId, int $electionBlockId): ?int
    {
        $slateBlocks = SlateBlock::query()
            ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
            ->where('slate_blocks.election_id', $electionId)
            ->where('slate_blocks.election_block_id', $electionBlockId)
            ->where('slates.is_active', true)
            ->orderBy('slate_blocks.id')
            ->get([
                'slate_blocks.id as slate_block_id',
                'slates.code as slate_code',
                'slates.name as slate_name',
            ]);

        if ($slateBlocks->isEmpty()) {
            return null;
        }

        $preferred = $slateBlocks->first(function ($row): bool {
            $code = Str::upper((string) ($row->slate_code ?? ''));
            $name = Str::upper((string) ($row->slate_name ?? ''));

            return str_contains($code, 'PL1') || str_contains($name, 'PLANCHA 1');
        });

        if ($preferred) {
            return (int) $preferred->slate_block_id;
        }

        return (int) $slateBlocks->first()->slate_block_id;
    }

    private function ensureElectionStructureForPosition(int $electionId, int $positionId): array
    {
        $position = Position::query()->find($positionId);
        $blockId = $position?->block_id;

        if (! $blockId) {
            return [
                'block_id' => null,
                'election_block_id' => null,
            ];
        }

        $electionBlock = ElectionBlock::query()->updateOrCreate(
            [
                'election_id' => $electionId,
                'block_id' => $blockId,
            ],
            [
                'is_active' => true,
            ]
        );

        $activeSlateIds = Slate::query()
            ->where('election_id', $electionId)
            ->where('is_active', true)
            ->pluck('id');

        if ($activeSlateIds->isEmpty()) {
            $activeSlateIds = Slate::query()
                ->where('election_id', $electionId)
                ->pluck('id');
        }

        foreach ($activeSlateIds as $slateId) {
            SlateBlock::query()->updateOrCreate(
                [
                    'election_id' => $electionId,
                    'slate_id' => $slateId,
                    'election_block_id' => $electionBlock->id,
                ],
                [
                    'is_active' => true,
                ]
            );
        }

        ElectionBlockPosition::query()->updateOrCreate(
            [
                'election_block_id' => $electionBlock->id,
                'position_id' => $positionId,
            ],
            [
                'block_id' => $blockId,
                'vacancies' => 1,
                'is_active' => true,
            ]
        );

        return [
            'block_id' => $blockId,
            'election_block_id' => $electionBlock->id,
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
