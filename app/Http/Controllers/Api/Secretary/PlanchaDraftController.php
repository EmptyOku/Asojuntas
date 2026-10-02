<?php

namespace App\Http\Controllers\Api\Secretary;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateDraft;
use App\Models\CandidateDraftFile;
use App\Models\Block;
use App\Models\DocumentType;
use App\Models\ElectionBlock;
use App\Models\ElectionBlockPosition;
use App\Models\Election;
use App\Models\Neighborhood;
use App\Models\Person;
use App\Models\Position;
use App\Models\ScrutinyRecord;
use App\Models\Slate;
use App\Models\SlateBlock;
use App\Support\CandidateDraftWorkflow;
use App\Support\UnknownCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use App\Services\AdminNotifications;
use App\Services\ElectoralAccessGuard;
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
            'election_id' => 'nullable|integer',
        ]);

        // No se gasta OCR en una plancha que no se va a poder registrar.
        if (! empty($validated['election_id']) && ScrutinyRecord::electionHasApprovedActa((int) $validated['election_id'])) {
            return $this->approvedActaLockResponse();
        }

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

        // Si el lote ya existe, su elección manda: al corregir desde el detalle
        // la pantalla puede no enviar election_id y, sin esto, se caía a "la
        // elección activa más reciente" (la de OTRO barrio) y se guardaba ahí.
        $batchElectionId = ! empty($validated['capture_batch_uuid'])
            ? CandidateDraft::query()->where('capture_batch_uuid', $validated['capture_batch_uuid'])->value('election_id')
            : null;

        $electionId = $batchElectionId ?: $this->resolveElectionId($validated['election_id'] ?? null);
        if (! $electionId) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo resolver election_id. Envia election_id o crea una eleccion activa.',
            ], 422);
        }

        // Con un acta aprobada ya no entran planchas nuevas. Corregir un lote
        // que ya existía sí se permite (p. ej. arreglar un nombre mal leído).
        if (! $batchElectionId && ScrutinyRecord::electionHasApprovedActa((int) $electionId)) {
            return $this->approvedActaLockResponse();
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
        $lockedOfficial = [];
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

                // "<Unknown>" del OCR se guarda como "<DESCONOCIDO>".
                $fullName = UnknownCandidate::normalizeName((string) ($cargo['nombre'] ?? ''));
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
                    'is_substitute' => $this->isSubstituteCargo((string) ($cargo['puesto'] ?? '')),
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

                // Cada fila del lote es un cargo de la plancha: se identifica por el
                // cargo (y si es suplente), no por el nombre. Así, corregir en el
                // detalle un nombre o un documento mal leído por el OCR actualiza
                // ese mismo borrador en vez de crear uno duplicado. Antes solo se
                // reconocían borradores pendientes con el MISMO nombre y documento.
                $slotDrafts = CandidateDraft::query()
                    ->where('election_id', $electionId)
                    ->where('capture_batch_uuid', $captureBatchUuid)
                    ->where('is_substitute', $baseData['is_substitute'])
                    ->when(
                        $baseData['position_id'] !== null,
                        fn ($q) => $q->where('position_id', $baseData['position_id']),
                        fn ($q) => $q->whereNull('position_id')->where('notes', $baseData['notes'])
                    )
                    ->orderBy('id')
                    ->get();

                // Ya oficializado como candidato: no se toca ni se duplica. Se informa
                // a la pantalla para que no parezca que el cambio se guardó.
                if ($slotDrafts->contains(fn ($d) => $d->is_processed && $d->review_status !== CandidateDraftWorkflow::STATUS_REJECTED)) {
                    $skipped++;
                    $lockedOfficial[] = trim((string) ($cargo['puesto'] ?? ''));
                    continue;
                }

                $existing = $slotDrafts->first(fn ($d) => ! $d->is_processed);

                // Sin documento: número provisional único (el que ya tenía el
                // borrador si se está corrigiendo), para poder guardarlo y oficializarlo.
                if ($baseData['document_number'] === null) {
                    $baseData['document_number'] = $existing?->document_number ?: UnknownCandidate::nextPlaceholderDocument();
                }

                if ($existing) {
                    // Se corrigen los datos pero se conserva la decisión de revisión
                    // (si ya estaba aprobado, sigue aprobado y listo para oficializar).
                    // La plancha ya asignada tampoco cambia: el número que envía la
                    // pantalla sale de la posición de la pestaña, no de la plancha real.
                    $keep = ['review_status', 'is_processed', 'processed_at'];
                    if ($existing->slate_id) {
                        array_push($keep, 'slate_id', 'slate_block_id');
                    }
                    $existing->update(collect($baseData)->except($keep)->all());
                    $updated++;
                    $rows[] = $existing;
                    continue;
                }

                $draft = CandidateDraft::create($baseData);
                $created++;
                $rows[] = $draft;
            }
        }

        // Aviso para el administrador solo con una plancha nueva (no al corregir un lote existente).
        if (! $batchElectionId && $created > 0) {
            $neighborhood = Election::with('neighborhood:id,name')->find($electionId)?->neighborhood;
            app(AdminNotifications::class)->record(AdminNotifications::PLANCHA_CAPTURED, [
                'neighborhood_id' => $neighborhood?->id,
                'neighborhood' => $neighborhood?->name,
                'election_id' => $electionId,
                'batch' => $captureBatchUuid,
                'candidates' => $created,
            ], CandidateDraft::class, $rows[0]->id ?? null);
        }

        return response()->json([
            'success' => true,
            'message' => 'Borradores de plancha guardados correctamente.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                // Cargos ya oficiales: no se modifican desde la plancha.
                'locked_official' => $lockedOfficial,
                'capture_batch_uuid' => $captureBatchUuid,
                'drafts' => $rows,
            ],
        ], 201);
    }

    private function approvedActaLockResponse(): JsonResponse
    {
        $message = 'Este barrio ya tiene un acta de escrutinio aprobada: no se pueden registrar planchas nuevas.';

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => ['election_id' => [$message]],
        ], 422);
    }

    /**
     * Avisos de candidatos repetidos, por id de borrador.
     *
     * Una persona (mismo documento) solo puede ocupar un cargo en la elección.
     * Se avisa si ya es candidata oficial, si está en otra plancha en revisión
     * o si aparece dos veces en la misma plancha. Son avisos: no bloquean, el
     * revisor decide. Los documentos provisionales no cuentan.
     *
     * @param  \Illuminate\Support\Collection<int, Election>  $elections  con candidateDrafts cargados
     * @return array<int, array<int, string>>
     */
    private function duplicateWarnings($elections): array
    {
        $warnings = [];
        $hasRealDocument = fn (CandidateDraft $d) => $d->document_number && ! UnknownCandidate::isPlaceholderDocument($d->document_number);

        $official = Candidate::query()
            ->whereIn('election_id', $elections->pluck('id'))
            ->where('is_active', true)
            ->with([
                'person:id,document_number',
                'slateBlock:id,slate_id',
                'slateBlock.slate:id,code',
                'electionBlockPosition:id,position_id',
                'electionBlockPosition.position:id,name',
            ])
            ->get(['id', 'election_id', 'person_id', 'slate_block_id', 'election_block_position_id', 'is_substitute'])
            ->groupBy(fn (Candidate $c) => $c->election_id.'|'.$c->person?->document_number);

        foreach ($elections as $election) {
            $batchNumber = $election->candidateDrafts->sortBy('id')->pluck('capture_batch_uuid')->unique()->values()->flip();

            // Borradores vigentes (ni rechazados) con documento real, por documento.
            $byDocument = $election->candidateDrafts
                ->filter(fn (CandidateDraft $d) => $hasRealDocument($d) && $d->review_status !== CandidateDraftWorkflow::STATUS_REJECTED)
                ->groupBy('document_number');

            foreach ($election->candidateDrafts as $draft) {
                if (! $hasRealDocument($draft) || $draft->review_status === CandidateDraftWorkflow::STATUS_REJECTED) {
                    continue;
                }

                // Ya es candidato oficial (y este borrador no es el que lo oficializó).
                if (! $draft->is_processed) {
                    foreach ($official->get($election->id.'|'.$draft->document_number, []) as $candidate) {
                        $slate = preg_replace('/^P(?=\d)/i', '', (string) $candidate->slateBlock?->slate?->code);
                        $position = $candidate->electionBlockPosition?->position?->name ?? 'un cargo';
                        $warnings[$draft->id][] = 'Ya es candidato oficial en la Plancha '.$slate.' ('.($candidate->is_substitute ? 'suplente de ' : '').$position.').';
                    }
                }

                foreach ($byDocument->get($draft->document_number, []) as $other) {
                    if ($other->id === $draft->id) {
                        continue;
                    }

                    if ($other->capture_batch_uuid === $draft->capture_batch_uuid) {
                        $warnings[$draft->id][] = 'El mismo documento aparece dos veces en esta plancha.';
                    } elseif (! $draft->is_processed && ! $other->is_processed) {
                        $warnings[$draft->id][] = 'También está en la Plancha '.(($batchNumber[$other->capture_batch_uuid] ?? 0) + 1).' en revisión.';
                    }
                }

                if (isset($warnings[$draft->id])) {
                    $warnings[$draft->id] = array_values(array_unique($warnings[$draft->id]));
                }
            }
        }

        return $warnings;
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

    public function showEvidenceFile(Request $request, CandidateDraftFile $candidateDraftFile)
    {
        // La evidencia contiene documentos de identidad: solo el barrio dueño o un revisor.
        app(ElectoralAccessGuard::class)->assertCanReachElection(
            $request->user(),
            $candidateDraftFile->election_id,
            'No tienes acceso a esta evidencia.'
        );

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
            'draft_id' => 'nullable|integer|active_exists:candidate_drafts,id',
            'election_id' => 'nullable|integer|exists:elections,id',
            'capture_batch_uuid' => 'nullable|uuid',
            'review_status' => 'nullable|string|in:pending,approved,rejected',
            'is_processed' => 'nullable|boolean',
            'search' => 'nullable|string|max:100',
            // Hasta 100: el detalle de un lote pide la plancha completa de una vez
            // (con suplentes son ~22 cargos). Antes el máximo era 20 y la pantalla
            // de detalle recibía un 422 que confundía con "OCR aún procesando".
            'per_page' => 'nullable|integer|min:1|max:100',
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
                $q->whereLike('candidate_drafts.first_name', "%{$term}%")
                    ->orWhereLike('candidate_drafts.last_name', "%{$term}%")
                    ->orWhereLike('candidate_drafts.document_number', "%{$term}%");

                if (! $isDetailedRequest) {
                    $q->orWhereLike('neighborhoods.name', "%{$term}%");
                }
            });
        }

        // Un lote se pide completo y con total (el contador de "promovibles" lo
        // usa); la bandeja general sigue paginando de a 20 sin contar.
        if ($isDetailedRequest) {
            $perPage = max(1, min(100, (int) $request->integer('per_page', 100)));
            $drafts = $query->orderByDesc('candidate_drafts.id')->paginate($perPage);
        } else {
            $perPage = max(1, min(20, (int) $request->integer('per_page', 15)));
            $drafts = $query->orderByDesc('candidate_drafts.id')->simplePaginate($perPage);
        }

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
                $query->whereLike('name', "%{$term}%")
                    ->orWhereLike('code', "%{$term}%");
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
                    'is_substitute',
                ])
                ->with([
                    'election:id,neighborhood_id,is_active',
                    'person:id,first_name,middle_name,last_name,second_last_name,document_number',
                    'slateBlock:id,election_id,slate_id,election_block_id',
                    'slateBlock.slate:id,name,code',
                    'electionBlockPosition:id,position_id',
                    'electionBlockPosition.position:id,block_id,name,code,order_number',
                    'electionBlockPosition.position.block:id,name,code',
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

                        $position = $candidate->electionBlockPosition?->position;

                        return [
                            'id' => $candidate->id,
                            'name' => $fullName !== '' ? $fullName : 'Sin nombre',
                            'position' => $position?->name ?? 'Sin cargo',
                            'ballot_number' => $candidate->ballot_number,
                            // Para ordenar y agrupar en pantalla: bloque, orden del cargo y suplente.
                            'block' => $position?->block?->name,
                            'block_code' => $position?->block?->code,
                            'order' => (int) ($position?->order_number ?? 99),
                            'is_substitute' => (bool) $candidate->is_substitute,
                            'document_number' => $person?->document_number,
                            'document_is_placeholder' => UnknownCandidate::isPlaceholderDocument($person?->document_number),
                        ];
                    })
                        // Cargo por cargo: el principal y enseguida su suplente.
                        ->sortBy([['order', 'asc'], ['is_substitute', 'asc'], ['id', 'asc']])
                        ->values();

                    return [
                        'id' => $slate?->id,
                        'code' => $slate?->code,
                        'name' => $slate?->name,
                        // "P1" -> "Plancha 1" (antes se mostraba "Plancha P1").
                        'label' => $slate?->code ? 'Plancha '.preg_replace('/^P(?=\d)/i', '', $slate->code) : 'Plancha sin código',
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

    /**
     * Bandeja agrupada por Barrio/Eleccion -> Lotes -> Bloques.
     * Endpoint consumido por la vista de acordeon de Secretaria.
     */
    public function groupedInbox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
        ]);

        // La bandeja es una lista de trabajo: una plancha sale de aquí cuando ya
        // no le queda nada por hacer (todo oficializado). Un borrador "abierto" es
        // uno sin oficializar; los rechazados solo cuentan si la plancha aún no
        // tiene nada oficial (una plancha rechazada entera sigue visible).
        $query = Election::query()
            ->where('is_active', true)
            ->whereHas('candidateDrafts', function ($draftQuery): void {
                $draftQuery->where('is_processed', false)
                    ->where(function ($open): void {
                        $open->where('review_status', '<>', CandidateDraftWorkflow::STATUS_REJECTED)
                            ->orWhereNotExists(function ($processed): void {
                                $processed->selectRaw('1')
                                    ->from('candidate_drafts as done')
                                    ->whereColumn('done.capture_batch_uuid', 'candidate_drafts.capture_batch_uuid')
                                    ->where('done.is_processed', true)
                                    ->whereNull('done.deleted_at');
                            });
                    });
            })
            ->with([
                'neighborhood.commune',
                'candidateDrafts' => function ($q): void {
                    $q->orderBy('capture_batch_uuid')->orderBy('id');
                },
            ]);

        if (! empty($validated['q'])) {
            $term = trim((string) $validated['q']);
            $query->where(function ($q) use ($term): void {
                $q->whereHas('neighborhood', function ($sub) use ($term): void {
                    $sub->whereLike('name', "%{$term}%");
                })->orWhereHas('candidateDrafts', function ($sub) use ($term): void {
                    $sub->whereLike('first_name', "%{$term}%")
                        ->orWhereLike('last_name', "%{$term}%")
                        ->orWhereLike('document_number', "%{$term}%");
                });
            });
        }

        $elections = $query->paginate(10);
        $warnings = $this->duplicateWarnings($elections->getCollection());
        $closedElections = ScrutinyRecord::query()
            ->whereIn('election_id', $elections->getCollection()->pluck('id'))
            ->whereIn('status', ScrutinyRecord::APPROVED_STATUSES)
            ->distinct()
            ->pluck('election_id')
            ->flip();

        $data = $elections->through(function (Election $election) use ($warnings, $closedElections): array {
            $drafts = $election->candidateDrafts;

            // Número estable de cada plancha dentro del barrio: no cambia cuando
            // otra plancha sale de la bandeja al oficializarse.
            // Va por orden de captura: la primera plancha registrada es la Plancha 1.
            $batchNumbers = $drafts->sortBy('id')->pluck('capture_batch_uuid')->unique()->values()->flip();

            $batches = $drafts->groupBy('capture_batch_uuid')->filter(function ($batchDrafts): bool {
                $hasOfficial = $batchDrafts->contains(fn (CandidateDraft $d) => (bool) $d->is_processed);

                return $batchDrafts->contains(fn (CandidateDraft $d) => ! $d->is_processed
                    && ($d->review_status !== CandidateDraftWorkflow::STATUS_REJECTED || ! $hasOfficial));
            })->map(function ($batchDrafts, $uuid) use ($warnings, $batchNumbers): array {
                $blocks = $batchDrafts->groupBy(function (CandidateDraft $draft): string {
                    if (preg_match('/Bloque\s*-\s*([^|]+)/i', (string) $draft->notes, $matches) === 1) {
                        return trim((string) ($matches[1] ?? 'Otros Cargos'));
                    }

                    return 'Otros Cargos';
                })->map(function ($blockDrafts, $blockName) use ($warnings): array {
                    return [
                        'block_name' => (string) $blockName,
                        'candidates' => collect($blockDrafts)->map(function (CandidateDraft $c) use ($warnings): array {
                            $fullName = trim(implode(' ', array_filter([
                                $c->first_name,
                                $c->middle_name,
                                $c->last_name,
                                $c->second_last_name,
                            ])));

                            $cargo = 'Sin Cargo';
                            if (preg_match('/Cargo:\s*(.+)$/i', (string) $c->notes, $m) === 1) {
                                $cargo = trim((string) ($m[1] ?? 'Sin Cargo'));
                            }

                            return [
                                'id' => $c->id,
                                'full_name' => $fullName,
                                'document_number' => $c->document_number,
                                'review_status' => $c->review_status,
                                'is_processed' => $c->is_processed,
                                'is_substitute' => (bool) $c->is_substitute,
                                'cargo' => $cargo,
                                // Candidato ya registrado en otro lado (ver duplicateWarnings()).
                                'warnings' => $warnings[$c->id] ?? [],
                            ];
                        })->values(),
                    ];
                })->values();

                return [
                    'capture_batch_uuid' => $uuid,
                    'number' => ((int) ($batchNumbers[$uuid] ?? 0)) + 1,
                    'total' => $batchDrafts->count(),
                    'pending' => $batchDrafts->where('review_status', 'pending')->where('is_processed', false)->count(),
                    'approved' => $batchDrafts->where('review_status', 'approved')->count(),
                    'rejected' => $batchDrafts->where('review_status', 'rejected')->count(),
                    'promotable' => $batchDrafts->where('review_status', 'approved')->where('is_processed', false)->count(),
                    'official' => $batchDrafts->where('is_processed', true)->where('review_status', '<>', 'rejected')->count(),
                    'blocks' => $blocks,
                ];
            })->sortBy('number')->values();

            return [
                'election_id' => $election->id,
                'has_approved_acta' => $closedElections->has($election->id),
                'neighborhood_name' => $election->neighborhood->name ?? 'Desconocido',
                'commune_name' => $election->neighborhood?->commune?->name ?? 'Sin Comuna',
                // Totales solo de las planchas que siguen en la bandeja.
                'total_drafts' => $batches->sum('total'),
                'total_pending' => $batches->sum('pending'),
                'total_approved' => $batches->sum('approved'),
                'batches' => $batches,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $elections->currentPage(),
                'last_page' => $elections->lastPage(),
                'total_neighborhoods' => $elections->total(),
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

        if ($target === CandidateDraftWorkflow::STATUS_APPROVED) {
            $this->ensureSlateContextForApprovedDraft($candidateDraft);
        }

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

                if ($target === CandidateDraftWorkflow::STATUS_APPROVED) {
                    $this->ensureSlateContextForApprovedDraft($draft);
                }

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
            'draft_ids.*' => 'integer|active_exists:candidate_drafts,id',
        ]);

        // Sin ningún filtro la consulta abarcaba TODOS los borradores aprobados
        // de todos los barrios: se exige indicar plancha, elección o borradores.
        if (empty($validated['election_id']) && empty($validated['capture_batch_uuid']) && empty($validated['draft_ids'])) {
            $message = 'Indica qué plancha se va a oficializar.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['capture_batch_uuid' => [$message]],
            ], 422);
        }

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
                // Borradores viejos sin documento (antes se omitían con "Sin documento
                // para deduplicar persona"): reciben un número provisional único.
                if (! $draft->document_number) {
                    $draft->document_number = UnknownCandidate::nextPlaceholderDocument();
                    $draft->save();
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
                    $cargoLabel = $this->extractCargoLabelFromNotes((string) ($draft->notes ?? ''));
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => 'El cargo extraído no corresponde a un cargo oficial.'.($cargoLabel ? " Cargo detectado: {$cargoLabel}." : ''),
                    ];
                    continue;
                }

                if (! $slateBlockId) {
                    $activeSlates = Slate::query()
                        ->where('election_id', $draft->election_id)
                        ->where('is_active', true)
                        ->count();

                    $totalSlates = Slate::query()
                        ->where('election_id', $draft->election_id)
                        ->count();

                    $availableSlateBlocks = 0;
                    if ($electionBlockId) {
                        $availableSlateBlocks = SlateBlock::query()
                            ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
                            ->where('slate_blocks.election_id', $draft->election_id)
                            ->where('slate_blocks.election_block_id', $electionBlockId)
                            ->where('slates.is_active', true)
                            ->count();
                    }

                    $reason = 'No se pudo determinar a qué plancha pertenece el cargo.';
                    if ($totalSlates === 0) {
                        $reason .= ' La elección todavía no tiene planchas creadas.';
                    } elseif ($activeSlates === 0) {
                        $reason .= ' La elección tiene planchas, pero ninguna está activa.';
                    } elseif ($availableSlateBlocks === 0) {
                        $reason .= ' La plancha no tiene configurado este bloque.';
                    } else {
                        $reason .= ' Revisa el número de la plancha y vuelve a guardarla.';
                    }

                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => $reason,
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

                $isSubstitute = (bool) $draft->is_substitute
                    || $this->isSubstituteCargo((string) $this->extractCargoLabelFromNotes((string) ($draft->notes ?? '')));

                // La persona ya es candidata oficial en OTRO cargo o plancha de esta
                // elección: antes updateOrCreate la movía en silencio y dejaba vacío
                // su cargo anterior. Ahora no se toca y se avisa.
                $already = Candidate::query()
                    ->where('election_id', $draft->election_id)
                    ->where('person_id', $person->id)
                    ->where('is_active', true)
                    ->with(['slateBlock.slate:id,code', 'electionBlockPosition.position:id,name'])
                    ->first();

                if ($already && (
                    (int) $already->slate_block_id !== (int) $slateBlockId
                    || (int) $already->election_block_position_id !== (int) $electionBlockPositionId
                    || (bool) $already->is_substitute !== $isSubstitute
                )) {
                    $slate = preg_replace('/^P(?=[0-9])/i', '', (string) $already->slateBlock?->slate?->code);
                    $position = ($already->is_substitute ? 'suplente de ' : '').($already->electionBlockPosition?->position?->name ?? 'otro cargo');
                    $skipped++;
                    $issues[] = [
                        'draft_id' => $draft->id,
                        'reason' => trim($draft->first_name.' '.$draft->last_name).' (CC '.$draft->document_number.') ya es candidato oficial en la Plancha '.$slate.' ('.$position.'): no se registró de nuevo. Corrige el documento o rechaza este candidato.',
                    ];

                    continue;
                }

                $candidate = Candidate::query()->updateOrCreate(
                    [
                        'election_id' => $draft->election_id,
                        'person_id' => $person->id,
                    ],
                    [
                        'slate_block_id' => $slateBlockId,
                        'election_block_position_id' => $electionBlockPositionId,
                        'is_substitute' => $isSubstitute,
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
                    'is_substitute' => $isSubstitute,
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
            'message' => 'Oficialización completada.',
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
        [$positionCode, $blockCode] = $this->resolvePositionAndBlockCodeForCargo($normalizedCargo);

        $positionId = null;
        $blockId = null;
        $slateId = null;
        $slateBlockId = null;

        if ($positionCode !== null) {
            [$positionId, $blockId] = $this->ensurePositionCatalogForCode($positionCode, $blockCode);
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

    /**
     * "SUPLENTE DE PRESIDENTE", "Suplente Fiscal"...: se guarda en el mismo
     * cargo que el principal, pero marcado como suplente para que no cuente
     * como un segundo presidente al asignar curules.
     */
    private function isSubstituteCargo(string $cargoLabel): bool
    {
        return Str::of($cargoLabel)->ascii()->upper()->squish()->startsWith('SUPLENTE');
    }

    private function resolvePositionAndBlockCodeForCargo(string $normalizedCargo): array
    {
        $map = [
            'PRESIDENTE' => ['DIR_PRES', 'DIR'],
            'SUPLENTE DE PRESIDENTE' => ['DIR_PRES', 'DIR'],
            'SUPLENTE PRESIDENTE' => ['DIR_PRES', 'DIR'],
            'VICEPRESIDENTE' => ['DIR_VICE', 'DIR'],
            'VICE PRESIDENTE' => ['DIR_VICE', 'DIR'],
            'SUPLENTE DE VICEPRESIDENTE' => ['DIR_VICE', 'DIR'],
            'SUPLENTE VICEPRESIDENTE' => ['DIR_VICE', 'DIR'],
            'TESORERO' => ['DIR_TESO', 'DIR'],
            'SUPLENTE DE TESORERO' => ['DIR_TESO', 'DIR'],
            'SECRETARIO' => ['DIR_SECR', 'DIR'],
            'SECRETARIA' => ['DIR_SECR', 'DIR'],
            'SUPLENTE DE SECRETARIO' => ['DIR_SECR', 'DIR'],
            'DELEGADO ASOJUNTAS 1' => ['DEL_AJ_1', 'DEL'],
            'SUPLENTE DELEGADO ASOJUNTAS 1' => ['DEL_AJ_1', 'DEL'],
            'SUPLENTE DE DELEGADO ASOJUNTAS 1' => ['DEL_AJ_1', 'DEL'],
            'DELEGADO ASOJUNTAS 2' => ['DEL_AJ_2', 'DEL'],
            'SUPLENTE DELEGADO ASOJUNTAS 2' => ['DEL_AJ_2', 'DEL'],
            'SUPLENTE DE DELEGADO ASOJUNTAS 2' => ['DEL_AJ_2', 'DEL'],
            'DELEGADO ASOJUNTAS 3' => ['DEL_AJ_3', 'DEL'],
            'SUPLENTE DELEGADO ASOJUNTAS 3' => ['DEL_AJ_3', 'DEL'],
            'SUPLENTE DE DELEGADO ASOJUNTAS 3' => ['DEL_AJ_3', 'DEL'],
            'FISCAL' => ['FIS_PRIN', 'FIS'],
            'SUPLENTE FISCAL' => ['FIS_PRIN', 'FIS'],
            'SUPLENTE DE FISCAL' => ['FIS_PRIN', 'FIS'],
            'CONCILIADOR 1' => ['CYC_CONC_1', 'CYC'],
            'CONCILIADOR 2' => ['CYC_CONC_2', 'CYC'],
            'CONCILIADOR 3' => ['CYC_CONC_3', 'CYC'],
            'COMISION EMPRESARIAL' => ['CYC_EMP_COORD', 'CYC'],
            'COMISION DE EMPRESARIAL' => ['CYC_EMP_COORD', 'CYC'],
            'COORDINADOR COMISION EMPRESARIAL' => ['CYC_EMP_COORD', 'CYC'],
            'COORDINADOR DE COMISION EMPRESARIAL' => ['CYC_EMP_COORD', 'CYC'],
        ];

        if (isset($map[$normalizedCargo])) {
            return $map[$normalizedCargo];
        }

        if (preg_match('/^DELEGADO ASOJUNTAS\s*([123])$/', $normalizedCargo, $matches) === 1) {
            return ['DEL_AJ_'.(int) $matches[1], 'DEL'];
        }

        if (preg_match('/^SUPLENTE\s+(?:DE\s+)?DELEGADO ASOJUNTAS\s*([123])$/', $normalizedCargo, $matches) === 1) {
            return ['DEL_AJ_'.(int) $matches[1], 'DEL'];
        }

        if (preg_match('/^CONCILIADOR\s*([123])$/', $normalizedCargo, $matches) === 1) {
            return ['CYC_CONC_'.(int) $matches[1], 'CYC'];
        }

        return [null, null];
    }

    private function ensurePositionCatalogForCode(string $positionCode, ?string $blockCode): array
    {
        $blockCode = $blockCode ?: $this->defaultBlockCodeForPosition($positionCode);
        if (! $blockCode) {
            return [null, null];
        }

        $blockNames = [
            'DIR' => 'Directiva',
            'DEL' => 'Delegados Asojuntas',
            'FIS' => 'Fiscal',
            'CYC' => 'Comision de convivencia y conciliacion',
        ];

        $positionNames = [
            'DIR_PRES' => 'Presidente',
            'DIR_VICE' => 'Vicepresidente',
            'DIR_TESO' => 'Tesorero',
            'DIR_SECR' => 'Secretario',
            'DEL_AJ_1' => 'Delegado Asojuntas 1',
            'DEL_AJ_2' => 'Delegado Asojuntas 2',
            'DEL_AJ_3' => 'Delegado Asojuntas 3',
            'FIS_PRIN' => 'Fiscal',
            'CYC_CONC_1' => 'Conciliador 1',
            'CYC_CONC_2' => 'Conciliador 2',
            'CYC_CONC_3' => 'Conciliador 3',
            'CYC_EMP_COORD' => 'Comision empresarial',
        ];

        $block = Block::query()->firstOrCreate(
            ['code' => $blockCode],
            [
                'name' => $blockNames[$blockCode] ?? $blockCode,
                'description' => 'Creado automáticamente al registrar planchas.',
                'is_active' => true,
            ]
        );

        if (! $block->is_active) {
            $block->update(['is_active' => true]);
        }

        $position = Position::query()
            ->where('code', $positionCode)
            ->where('block_id', $block->id)
            ->first();

        // Jerarquía dentro del bloque: define qué cargo se provee primero al
        // asignar curules (antes quedaba en null y se perdía el orden).
        $positionOrder = [
            'DIR_PRES' => 1,
            'DIR_VICE' => 2,
            'DIR_TESO' => 3,
            'DIR_SECR' => 4,
            'DEL_AJ_1' => 1,
            'DEL_AJ_2' => 2,
            'DEL_AJ_3' => 3,
            'FIS_PRIN' => 1,
            'CYC_CONC_1' => 1,
            'CYC_CONC_2' => 2,
            'CYC_CONC_3' => 3,
            'CYC_EMP_COORD' => 4,
        ];

        if (! $position) {
            $position = Position::query()->create([
                'block_id' => $block->id,
                'name' => $positionNames[$positionCode] ?? $positionCode,
                'code' => $positionCode,
                'order_number' => $positionOrder[$positionCode] ?? null,
                'description' => 'Creado automáticamente al registrar planchas.',
                'is_active' => true,
            ]);
        } elseif (! $position->is_active) {
            $position->update(['is_active' => true]);
        }

        return [(int) $position->id, (int) $block->id];
    }

    private function defaultBlockCodeForPosition(string $positionCode): ?string
    {
        return match ($positionCode) {
            'DIR_PRES', 'DIR_VICE', 'DIR_TESO', 'DIR_SECR' => 'DIR',
            'DEL_AJ_1', 'DEL_AJ_2', 'DEL_AJ_3' => 'DEL',
            'FIS_PRIN' => 'FIS',
            'CYC_CONC_1', 'CYC_CONC_2', 'CYC_CONC_3', 'CYC_EMP_COORD' => 'CYC',
            default => null,
        };
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

    private function ensureSlateContextForApprovedDraft(CandidateDraft $draft): void
    {
        $electionId = (int) ($draft->election_id ?? 0);
        if ($electionId <= 0) {
            return;
        }

        $positionId = $draft->position_id;
        if (! $positionId) {
            $cargoLabel = $this->extractCargoLabelFromNotes((string) ($draft->notes ?? ''));
            if ($cargoLabel !== null) {
                $positionContext = $this->resolvePositionContextForCargo($electionId, $cargoLabel, null);
                $positionId = $positionContext['position_id'];
            }
        }

        $electionBlockId = null;
        if ($positionId) {
            $mapping = $this->ensureElectionStructureForPosition($electionId, (int) $positionId);
            $electionBlockId = $mapping['election_block_id'];
        }

        $slateId = $draft->slate_id;
        if (! $slateId && $draft->slate_block_id) {
            $slateId = SlateBlock::query()
                ->where('id', $draft->slate_block_id)
                ->value('slate_id');
        }

        if (! $slateId && $draft->capture_batch_uuid) {
            $batchSlateIds = CandidateDraft::query()
                ->where('capture_batch_uuid', $draft->capture_batch_uuid)
                ->where('election_id', $electionId)
                ->whereNotNull('slate_id')
                ->distinct()
                ->pluck('slate_id');

            if ($batchSlateIds->count() === 1) {
                $slateId = (int) $batchSlateIds->first();
            }
        }

        // Cada lote de captura es una plancha. Si el lote todavía no tiene
        // plancha se le crea la siguiente (antes se reutilizaba la primera de
        // la elección y todas las planchas terminaban fusionadas en
        // "Plancha 1"). Un borrador suelto, sin lote, sí usa la existente.
        if (! $slateId) {
            $slateId = $draft->capture_batch_uuid
                ? $this->createNextSlateForElection($electionId)
                : $this->ensureAutoSlateForElection($electionId);
        }

        $slateBlockId = $draft->slate_block_id;
        if (! $slateBlockId && $slateId && $electionBlockId) {
            $slateBlock = SlateBlock::query()->updateOrCreate(
                [
                    'election_id' => $electionId,
                    'slate_id' => $slateId,
                    'election_block_id' => $electionBlockId,
                ],
                [
                    'is_active' => true,
                ]
            );

            $slateBlockId = $slateBlock->id;
        }

        $updates = [];

        if ($positionId && (int) ($draft->position_id ?? 0) !== (int) $positionId) {
            $updates['position_id'] = (int) $positionId;
        }

        if ($slateId && (int) ($draft->slate_id ?? 0) !== (int) $slateId) {
            $updates['slate_id'] = (int) $slateId;
        }

        if ($slateBlockId && (int) ($draft->slate_block_id ?? 0) !== (int) $slateBlockId) {
            $updates['slate_block_id'] = (int) $slateBlockId;
        }

        if (! empty($updates)) {
            $draft->update($updates);
        }
    }

    private function ensureAutoSlateForElection(int $electionId): int
    {
        $existingActive = Slate::query()
            ->where('election_id', $electionId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($existingActive) {
            return (int) $existingActive->id;
        }

        $existingAny = Slate::query()
            ->where('election_id', $electionId)
            ->orderBy('id')
            ->first();

        if ($existingAny) {
            if (! $existingAny->is_active) {
                $existingAny->update(['is_active' => true]);
            }

            return (int) $existingAny->id;
        }

        return $this->createNextSlateForElection($electionId);
    }

    private function createNextSlateForElection(int $electionId): int
    {
        $nextNumber = $this->nextSlateNumberForElection($electionId);
        while (Slate::query()->where('election_id', $electionId)->where('code', 'P'.$nextNumber)->exists()) {
            $nextNumber++;
        }

        $slate = Slate::query()->create([
            'election_id' => $electionId,
            'code' => 'P'.$nextNumber,
            'name' => 'Plancha '.$nextNumber,
            'description' => 'Generada automáticamente al aprobar la plancha.',
            'is_active' => true,
        ]);

        return (int) $slate->id;
    }

    private function nextSlateNumberForElection(int $electionId): int
    {
        $maxNumber = 0;

        $slates = Slate::query()
            ->where('election_id', $electionId)
            ->get(['code', 'name']);

        foreach ($slates as $slate) {
            $code = Str::upper((string) ($slate->code ?? ''));
            $name = Str::upper((string) ($slate->name ?? ''));

            if (preg_match('/P(\d+)/', $code, $codeMatch) === 1) {
                $maxNumber = max($maxNumber, (int) $codeMatch[1]);
            }

            if (preg_match('/(\d+)/', $name, $nameMatch) === 1) {
                $maxNumber = max($maxNumber, (int) $nameMatch[1]);
            }
        }

        return max(1, $maxNumber + 1);
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
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return $disk;
                }
            } catch (Throwable $exception) {
                // Un disco remoto inalcanzable no debe tumbar la descarga:
                // se registra y se intenta con el siguiente.
                Log::warning('No se pudo consultar el disco de almacenamiento.', [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
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
            'No se encontro un ejecutable de Python válido para la extracción. '
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
