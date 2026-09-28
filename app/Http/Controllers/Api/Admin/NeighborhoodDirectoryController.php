<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Commune;
use App\Models\Neighborhood;
use App\Models\ElectionBlock;
use App\Models\ElectionBlockPosition;
use App\Models\PollingTable;
use App\Models\Position;
use App\Models\Slate;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyRecord;
use App\Models\Candidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NeighborhoodDirectoryController extends Controller
{
    /**
     * Lista todos los barrios con sus respectivos presidentes y vicepresidentes.
     * OPTIMIZADO: Paginación de servidor (15 registros) para carga ultra rápida.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->filteredNeighborhoodQuery($request)->with([
            'commune',
            'elections' => function ($query) {
                $query->latest('election_date')->where('is_active', true)->limit(1);
            },
            'elections.candidates' => function ($query) {
                $query->where('is_active', true);
            },
            'elections.candidates.person',
            'elections.candidates.electionBlockPosition.position'
        ]);

        // Paginación de servidor: 15 por defecto; la vista puede pedir entre 5 y 50.
        $perPage = min(50, max(5, (int) $request->integer('per_page', 15)));
        $neighborhoodsPaginator = $query->orderBy('name', 'asc')->paginate($perPage);

        // Transformamos solo los 15 registros de la página actual
        $neighborhoodsItems = collect($neighborhoodsPaginator->items())
            ->map(fn($neighborhood) => $this->toNeighborhoodRow($neighborhood))
            ->values()
            ->toArray();

        // Extras opcionales: la vista que ya tiene las comunas o no usa los
        // conteos masivos (el Directorio al buscar) los omite con
        // with_communes=0 / with_bulk_counts=0 y se ahorra dos consultas.
        // Por defecto se incluyen, como antes.
        $withCommunes = $request->boolean('with_communes', true);
        $withBulkCounts = $request->boolean('with_bulk_counts', true);

        // Conteos masivos para indicadores superiores. El paginador ya contó
        // el total con el mismo filtro: los barrios sin elección activa son
        // ese total menos los que sí la tienen (una consulta menos).
        $bulkCloseCount = $withBulkCounts
            ? $this->filteredNeighborhoodQuery($request)
                ->whereHas('elections', function ($electionQuery): void {
                    $electionQuery->where('is_active', true);
                })
                ->count()
            : null;
        $bulkCreateCount = $bulkCloseCount === null ? null : $neighborhoodsPaginator->total() - $bulkCloseCount;

        $communes = $withCommunes
            ? Commune::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn($commune) => [
                    'id' => $commune->id,
                    'name' => $commune->name,
                ])
                ->toArray()
            : null;

        $response = [
            'success' => true,
            'data' => [
                'neighborhoods' => $neighborhoodsItems,
                'pagination' => [
                    'current_page' => $neighborhoodsPaginator->currentPage(),
                    'last_page'    => $neighborhoodsPaginator->lastPage(),
                    'per_page'     => $neighborhoodsPaginator->perPage(),
                    'total'        => $neighborhoodsPaginator->total(),
                    'from'         => $neighborhoodsPaginator->firstItem() ?? 0,
                    'to'           => $neighborhoodsPaginator->lastItem() ?? 0,
                ],
                'communes' => $communes,
                'bulk_counts' => [
                    'create' => $bulkCreateCount,
                    'close' => $bulkCloseCount,
                ],
            ],
        ];
        
        return response()->json($response);
    }

    public function createElection(int $id): JsonResponse
    {
        $neighborhood = Neighborhood::findOrFail($id);

        $alreadyActive = Election::query()
            ->where('neighborhood_id', $neighborhood->id)
            ->where('is_active', true)
            ->exists();

        if ($alreadyActive) {
            return response()->json([
                'success' => false,
                'message' => 'Este barrio ya tiene una elección activa.',
            ], 422);
        }

        $election = DB::transaction(function () use ($neighborhood): Election {
            return $this->scaffoldElection($neighborhood);
        });

        return response()->json([
            'success' => true,
            'message' => 'Elección creada correctamente para el barrio.',
            'data' => [
                'election_id' => $election->id,
            ],
        ], 201);
    }

    public function closeElection(int $id): JsonResponse
    {
        $neighborhood = Neighborhood::findOrFail($id);

        $activeElection = Election::query()
            ->where('neighborhood_id', $neighborhood->id)
            ->where('is_active', true)
            ->latest('election_date')
            ->first();

        if (! $activeElection) {
            return response()->json([
                'success' => false,
                'message' => 'No hay elección activa para cerrar en este barrio.',
            ], 404);
        }

        $activeElection->is_active = false;
        $activeElection->save();

        PollingTable::query()
            ->where('election_id', $activeElection->id)
            ->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Elección cerrada correctamente.',
        ]);
    }

    public function createAllElections(Request $request): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $neighborhoodIds = $this->filteredNeighborhoodQuery($request)->pluck('id');

        if ($neighborhoodIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No se encontraron barrios para procesar.',
                'data' => [
                    'created' => 0,
                    'skipped' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $total = $neighborhoodIds->count();
        $activeNeighborhoodIds = Election::query()
            ->whereIn('neighborhood_id', $neighborhoodIds)
            ->where('is_active', true)
            ->pluck('neighborhood_id')
            ->all();

        $targets = Neighborhood::query()
            ->whereIn('id', $neighborhoodIds)
            ->whereNotIn('id', $activeNeighborhoodIds)
            ->get();

        $catalog = $this->getElectionScaffoldCatalog();
        $created = 0;

        foreach ($targets as $neighborhood) {
            DB::transaction(function () use ($neighborhood, $catalog): void {
                $this->scaffoldElection($neighborhood, $catalog);
            });

            $created++;
        }

        $skipped = max($total - $created, 0);

        return response()->json([
            'success' => true,
            'message' => 'Proceso masivo de creación finalizado.',
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
                'total' => $total,
            ],
        ]);
    }

    public function closeAllElections(Request $request): JsonResponse
    {
        $neighborhoodIds = $this->filteredNeighborhoodQuery($request)->pluck('id');

        if ($neighborhoodIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No se encontraron barrios para procesar.',
                'data' => [
                    'closed' => 0,
                    'skipped' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $total = $neighborhoodIds->count();
        $activeElectionIds = Election::query()
            ->whereIn('neighborhood_id', $neighborhoodIds)
            ->where('is_active', true)
            ->pluck('id');

        if ($activeElectionIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Proceso masivo de cierre finalizado.',
                'data' => [
                    'closed' => 0,
                    'skipped' => $total,
                    'total' => $total,
                ],
            ]);
        }

        $now = now();

        // Cierre masivo sin eventos Eloquent para evitar timeouts en lotes grandes.
        Election::query()
            ->whereIn('id', $activeElectionIds)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        PollingTable::query()
            ->whereIn('election_id', $activeElectionIds)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        $closed = $activeElectionIds->count();
        $skipped = max($total - $closed, 0);

        return response()->json([
            'success' => true,
            'message' => 'Proceso masivo de cierre finalizado.',
            'data' => [
                'closed' => $closed,
                'skipped' => $skipped,
                'total' => $total,
            ],
        ]);
    }

    private function filteredNeighborhoodQuery(Request $request)
    {
        $query = Neighborhood::query();

        if ($request->filled('commune_id')) {
            $query->where('commune_id', (int) $request->input('commune_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $likeTerm = '%'.mb_strtolower($search).'%';

            // whereRaw + LOWER() en vez de 'ilike': 'ilike' es exclusivo de
            // PostgreSQL y rompe en SQLite (entorno local).
            $query->where(function ($q) use ($likeTerm): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$likeTerm])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$likeTerm]);
            });
        }

        return $query;
    }

    private function scaffoldElection(Neighborhood $neighborhood, ?array $catalog = null): Election
    {
        $catalog = $catalog ?? $this->getElectionScaffoldCatalog();
        $timestamp = now()->format('YmdHis');
        $year = (int) now()->format('Y');

        $election = Election::create([
            'neighborhood_id' => $neighborhood->id,
            'name' => 'Eleccion JAC '.$neighborhood->name.' '.$year,
            'code' => 'JAC-'.$neighborhood->code.'-'.$timestamp,
            'election_date' => now()->toDateString(),
            'period_year' => $year,
            'is_active' => true,
            'description' => 'Creada desde Geografia Electoral.',
        ]);

        PollingTable::updateOrCreate(
            [
                'election_id' => $election->id,
                'code' => 'MESA-001',
            ],
            [
                'name' => 'Mesa Única',
                'location' => $neighborhood->name,
                'capacity' => 500,
                'is_active' => true,
            ]
        );

        $blockIds = $catalog['block_ids'];
        $positions = $catalog['positions'];
        $blockCodeById = $catalog['block_code_by_id'];

        $electionBlockIds = [];
        foreach (['DIR', 'DEL', 'FIS'] as $blockCode) {
            $blockId = $blockIds[$blockCode] ?? null;
            if (! $blockId) {
                continue;
            }

            $electionBlock = ElectionBlock::updateOrCreate(
                [
                    'election_id' => $election->id,
                    'block_id' => $blockId,
                ],
                [
                    'is_active' => true,
                ]
            );

            $electionBlockIds[$blockCode] = $electionBlock->id;
        }

        foreach ($positions as $position) {
            $blockCode = $blockCodeById[$position->block_id] ?? null;
            $electionBlockId = $blockCode ? ($electionBlockIds[$blockCode] ?? null) : null;

            if (! $electionBlockId) {
                continue;
            }

            ElectionBlockPosition::updateOrCreate(
                [
                    'election_block_id' => $electionBlockId,
                    'position_id' => $position->id,
                ],
                [
                    'block_id' => $position->block_id,
                    'vacancies' => 1,
                    'is_active' => true,
                ]
            );
        }

        $slates = [];
        foreach ([1, 2, 3] as $number) {
            $slates[$number] = Slate::updateOrCreate(
                [
                    'election_id' => $election->id,
                    'code' => 'P'.$number,
                ],
                [
                    'name' => 'Plancha '.$number,
                    'description' => 'Plancha base '.$number.' para la elección '.$neighborhood->name,
                    'is_active' => true,
                ]
            );
        }

        foreach ($slates as $slate) {
            foreach ($electionBlockIds as $blockCode => $electionBlockId) {
                DB::table('slate_blocks')->updateOrInsert(
                    [
                        'slate_id' => $slate->id,
                        'election_block_id' => $electionBlockId,
                    ],
                    [
                        'election_id' => $election->id,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        return $election;
    }

    private function getElectionScaffoldCatalog(): array
    {
        $blockIds = DB::table('blocks')
            ->whereIn('code', ['DIR', 'DEL', 'FIS'])
            ->pluck('id', 'code')
            ->toArray();

        $positions = Position::query()
            ->whereIn('code', ['DIR_PRES', 'DIR_VICE', 'DIR_TESO', 'DEL_1', 'DEL_2', 'FIS_PRIN'])
            ->get(['id', 'block_id', 'code']);

        return [
            'block_ids' => $blockIds,
            'block_code_by_id' => array_flip($blockIds),
            'positions' => $positions,
        ];
    }

    private function toNeighborhoodRow($neighborhood): array
    {
        $latestElection = $neighborhood->elections->first();
        $presidentName = null;
        $vicepresidentName = null;

        if ($latestElection) {
            // Solo principales (el suplente no es "el presidente") y en orden
            // fijo, para que no cambie entre recargas.
            $principals = $latestElection->candidates
                ->reject(fn ($candidate) => (bool) $candidate->is_substitute)
                ->sortBy('id');

            $president = $principals->first(
                fn ($candidate) => $this->candidateHoldsPosition($candidate, 'DIR_PRES', 'presidente')
            );

            $vicepresident = $principals->first(
                fn ($candidate) => $this->candidateHoldsPosition($candidate, 'DIR_VICE', 'vicepresidente')
            );

            if ($president && $president->person) {
                $presidentName = trim(implode(' ', array_filter([
                    $president->person->first_name ?? null,
                    $president->person->last_name ?? null,
                ])));
            }

            if ($vicepresident && $vicepresident->person) {
                $vicepresidentName = trim(implode(' ', array_filter([
                    $vicepresident->person->first_name ?? null,
                    $vicepresident->person->last_name ?? null,
                ])));
            }
        }

        return [
            'id' => $neighborhood->id,
            'name' => $neighborhood->name,
            'code' => $neighborhood->code,
            'commune' => $neighborhood->commune ? [
                'id' => $neighborhood->commune->id,
                'name' => $neighborhood->commune->name,
            ] : null,
            'president_name' => $presidentName,
            'vicepresident_name' => $vicepresidentName,
            'has_active_election' => $latestElection !== null,
            'active_election' => $latestElection ? [
                'id' => $latestElection->id,
                'name' => $latestElection->name,
                'code' => $latestElection->code,
                'election_date' => $latestElection->election_date?->toDateString(),
                'period_year' => $latestElection->period_year,
            ] : null,
        ];
    }

    /**
     * Por código de cargo (DIR_PRES / DIR_VICE); si el cargo no tiene código,
     * por nombre exacto ("Presidente" no debe coincidir con "Vicepresidente").
     */
    private function candidateHoldsPosition($candidate, string $code, string $name): bool
    {
        $position = data_get($candidate, 'electionBlockPosition.position');

        if (! $position) {
            return false;
        }

        if ($position->code) {
            return strtoupper((string) $position->code) === $code;
        }

        return mb_strtolower(trim((string) $position->name)) === $name;
    }

    public function show($id, Request $request): JsonResponse
    {
        ini_set('max_execution_time', 120);

        $neighborhood = Neighborhood::findOrFail($id);
        $election = $neighborhood->elections()
            ->where('is_active', true)
            ->latest('election_date')
            ->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'No hay elecciones activas para este barrio.',
                'data'    => null
            ]);
        }

        $scrutinyRecord = \App\Models\ScrutinyRecord::query()
            ->with([
                'extractions:id,scrutiny_record_id,status,confidence_score,created_at,normalized_payload',
                'blockResults:id,scrutiny_record_id,election_id,election_block_id,slate_block_id,votes,status',
                'blockResults.electionBlock:id,block_id',
                'blockResults.electionBlock.block:id,name,code',
                'blockResults.slateBlock:id,slate_id',
                'blockResults.slateBlock.slate:id,code,name',
            ])
            ->where('election_id', $election->id)
            ->whereIn('status', ['draft', 'pending', 'pending_review', 'reviewed', 'approved', 'consolidated'])
            ->latest('updated_at')
            ->first();

        $aggregatedOcrBlocks = [];
        if ($scrutinyRecord) {
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
                    if ($normalizedName === '' || isset($aggregatedOcrBlocks[$normalizedName])) {
                        continue;
                    }

                    $aggregatedOcrBlocks[$normalizedName] = [
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
        }

        $electionBlocks = ElectionBlock::with(['block'])
            ->where('election_id', $election->id)
            ->get();
        $electionBlocksBySequence = $electionBlocks->values()->mapWithKeys(function ($block, $index) {
            return [($index + 1) => $block];
        });

        // Todo lo que el bucle por bloque necesita se precarga una sola vez
        // por elección (antes eran ~10 consultas por bloque).
        $electionBlockIds = $electionBlocks->pluck('id')->all();

        $resultsByBlock = ScrutinyBlockResult::with(['slateBlock.slate'])
            ->where('election_id', $election->id)
            ->whereIn('status', ['approved', 'reviewed'])
            ->get()
            ->groupBy('election_block_id');

        $vacanciesByBlock = ElectionBlockPosition::query()
            ->whereIn('election_block_id', $electionBlockIds)
            ->select('election_block_id', DB::raw('COALESCE(SUM(vacancies), 0) AS total_vacancies'))
            ->groupBy('election_block_id')
            ->pluck('total_vacancies', 'election_block_id');

        $positionsByBlock = ElectionBlockPosition::with('position')
            ->whereIn('election_block_id', $electionBlockIds)
            ->orderBy('id')
            ->get()
            ->groupBy('election_block_id');

        $candidatesByBlock = Candidate::with([
            'person',
            'electionBlockPosition.position',
            'slateBlock.slate',
        ])
            ->where('election_id', $election->id)
            ->get()
            ->filter(fn ($candidate) => $candidate->electionBlockPosition !== null)
            ->groupBy(fn ($candidate) => $candidate->electionBlockPosition->election_block_id);

        $slateBlockIdByBlockAndName = [];
        \App\Models\SlateBlock::query()
            ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
            ->where('slate_blocks.election_id', $election->id)
            ->orderBy('slate_blocks.id')
            ->get(['slate_blocks.id', 'slate_blocks.election_block_id', 'slates.name'])
            ->each(function ($row) use (&$slateBlockIdByBlockAndName): void {
                $slateBlockIdByBlockAndName[$row->election_block_id][mb_strtolower(trim((string) $row->name))] ??= $row->id;
            });

        $resultadosFormateados = [];
        $overallSlateVotes = [];
        $processedBlockNames = [];

        foreach ($electionBlocks as $eb) {
            $blockName = (string) ($eb->block->name ?? 'Bloque');
            $blockNormalizedName = $this->normalizeBlockName($blockName);
            if ($blockNormalizedName !== '') {
                $processedBlockNames[$blockNormalizedName] = true;
            }

            $resultadosBloque = $resultsByBlock->get($eb->id, collect());
            $blockCandidates = $candidatesByBlock->get($eb->id, collect());
            $blockPositions = $positionsByBlock->get($eb->id, collect());

            $slateVotes = [];
            foreach ($resultadosBloque as $res) {
                if (! $res->slateBlock || ! $res->slateBlock->slate) {
                    continue;
                }

                $slateName = $res->slateBlock->slate->name;
                if (! isset($slateVotes[$slateName])) {
                    $slateVotes[$slateName] = [
                        'plancha' => $slateName,
                        'votos' => 0,
                        'slate_block_id' => $res->slate_block_id,
                    ];
                }

                $slateVotes[$slateName]['votos'] += (int) $res->votes;
            }

            $ocrBlock = $aggregatedOcrBlocks[$blockNormalizedName] ?? null;
            $blancos = 0;
            $nulos = 0;

            if ($ocrBlock) {
                $blancos = (int) ($ocrBlock['votes']['blancos'] ?? 0);
                $nulos = (int) ($ocrBlock['votes']['nulos'] ?? 0);
            }

            if (empty($slateVotes) && $ocrBlock) {
                $slateVotes = [
                    'Plancha 1' => [
                        'plancha' => 'Plancha 1',
                        'votos' => (int) ($ocrBlock['votes']['plancha_1'] ?? 0),
                        'slate_block_id' => null,
                    ],
                    'Plancha 2' => [
                        'plancha' => 'Plancha 2',
                        'votos' => (int) ($ocrBlock['votes']['plancha_2'] ?? 0),
                        'slate_block_id' => null,
                    ],
                    'Plancha 3' => [
                        'plancha' => 'Plancha 3',
                        'votos' => (int) ($ocrBlock['votes']['plancha_3'] ?? 0),
                        'slate_block_id' => null,
                    ],
                ];
            }

            if (empty($slateVotes)) {
                continue;
            }

            $cargosAProveer = (int) ($vacanciesByBlock[$eb->id] ?? 0);
            $allocation = $this->allocateSeatsByQuota(array_values($slateVotes), $cargosAProveer, $blancos);

            foreach ($allocation['planchas'] as $voteRow) {
                $overallSlateVotes[$voteRow['plancha']] = ($overallSlateVotes[$voteRow['plancha']] ?? 0) + (int) $voteRow['votos'];
            }

            $winner = $allocation['winner'];
            $cargos = $this->assignCargosBySeats(
                $allocation['planchas'],
                $blockPositions,
                $blockCandidates,
                $slateBlockIdByBlockAndName[$eb->id] ?? []
            );

            if (count($cargos) > $cargosAProveer) {
                $cargos = array_slice($cargos, 0, $cargosAProveer);
            }

            $resultadosFormateados[] = [
                'nombre_bloque' => $blockName,
                'codigo_bloque' => $eb->block->code ?? null,
                'cargos_a_proveer' => $cargosAProveer,
                'votos_validos' => $allocation['votos_validos'],
                'cuociente_electoral' => $allocation['cuociente_electoral'],
                'votos_planchas' => $allocation['planchas'],
                'cargos' => $cargos,
                'plancha_ganadora' => $winner,
                'planchas_ganadoras' => $allocation['winners'],
                'estadisticas' => [
                    'validos' => $allocation['votos_validos'],
                    'total' => $allocation['votos_validos'],
                    'blancos' => $blancos,
                    'nulos' => $nulos,
                ],
            ];
        }

        foreach ($aggregatedOcrBlocks as $normalizedName => $ocrBlock) {
            if (isset($processedBlockNames[$normalizedName])) {
                continue;
            }

            $resolvedElectionBlock = null;
            if (preg_match('/bloque\D*(\d+)/i', (string) ($ocrBlock['name'] ?? ''), $matches) === 1) {
                $blockSequence = (int) ($matches[1] ?? 0);
                if ($blockSequence > 0) {
                    $resolvedElectionBlock = $electionBlocksBySequence->get($blockSequence);
                }
            }

            $votosPlanchas = [
                [
                    'plancha' => 'Plancha 1',
                    'votos' => (int) ($ocrBlock['votes']['plancha_1'] ?? 0),
                    'slate_block_id' => null,
                ],
                [
                    'plancha' => 'Plancha 2',
                    'votos' => (int) ($ocrBlock['votes']['plancha_2'] ?? 0),
                    'slate_block_id' => null,
                ],
                [
                    'plancha' => 'Plancha 3',
                    'votos' => (int) ($ocrBlock['votes']['plancha_3'] ?? 0),
                    'slate_block_id' => null,
                ],
            ];

            $cargosAProveer = 0;
            $nombreBloque = $ocrBlock['name'];
            $codigoBloque = null;

            if ($resolvedElectionBlock) {
                $cargosAProveer = (int) ($vacanciesByBlock[$resolvedElectionBlock->id] ?? 0);

                $nombreBloque = $resolvedElectionBlock->block->name ?? $nombreBloque;
                $codigoBloque = $resolvedElectionBlock->block->code ?? null;
            }

            $allocation = $this->allocateSeatsByQuota($votosPlanchas, $cargosAProveer, (int) ($ocrBlock['votes']['blancos'] ?? 0));

            foreach ($allocation['planchas'] as $voteRow) {
                $overallSlateVotes[$voteRow['plancha']] = ($overallSlateVotes[$voteRow['plancha']] ?? 0) + (int) $voteRow['votos'];
            }

            $resultadosFormateados[] = [
                'nombre_bloque' => $nombreBloque,
                'codigo_bloque' => $codigoBloque,
                'cargos_a_proveer' => $cargosAProveer,
                'votos_validos' => $allocation['votos_validos'],
                'cuociente_electoral' => $allocation['cuociente_electoral'],
                'votos_planchas' => $allocation['planchas'],
                'cargos' => [],
                'plancha_ganadora' => $allocation['winner'],
                'estadisticas' => [
                    'validos' => $allocation['votos_validos'],
                    'total' => $allocation['votos_validos'],
                    'blancos' => (int) ($ocrBlock['votes']['blancos'] ?? 0),
                    'nulos' => (int) ($ocrBlock['votes']['nulos'] ?? 0),
                ],
            ];
        }

        $planchaGanadoraGlobal = null;
        if (! empty($overallSlateVotes)) {
            arsort($overallSlateVotes);
            $planchaGanadoraGlobal = [
                'plancha' => array_key_first($overallSlateVotes),
                'votos' => (int) reset($overallSlateVotes),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $neighborhood->id,
                'name'       => $neighborhood->name,
                'resultados' => $resultadosFormateados,
                'plancha_ganadora' => $planchaGanadoraGlobal,
            ],
        ]);
    }

    /**
     * Reporte consolidado para directorio de candidatos.
     * Incluye por barrio:
     * - estado de registro de planchas (con candidatos activos)
     * - cuocientes electorales por bloque (solo si existe escrutinio)
     * - avisos cuando no hay planchas registradas o no hay escrutinio
     */
    public function report(Request $request): JsonResponse
    {
        ini_set('max_execution_time', 180);

        $neighborhoods = $this->filteredNeighborhoodQuery($request)
            ->with('commune:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'commune_id']);

        $neighborhoodIds = $neighborhoods->pluck('id')->all();

        $activeElectionsByNeighborhood = Election::query()
            ->whereIn('neighborhood_id', $neighborhoodIds)
            ->where('is_active', true)
            ->orderByDesc('election_date')
            ->orderByDesc('id')
            ->get(['id', 'neighborhood_id', 'name', 'code', 'election_date'])
            ->unique('neighborhood_id')
            ->keyBy('neighborhood_id');

        $electionIds = $activeElectionsByNeighborhood
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $slatesByElection = Slate::query()
            ->whereIn('election_id', $electionIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'election_id', 'name', 'code'])
            ->groupBy('election_id');

        $candidateCountByElectionAndSlate = Candidate::query()
            ->join('slate_blocks', 'slate_blocks.id', '=', 'candidates.slate_block_id')
            ->whereIn('candidates.election_id', $electionIds)
            ->where('candidates.is_active', true)
            ->select([
                'candidates.election_id',
                'slate_blocks.slate_id',
                DB::raw('COUNT(candidates.id) AS total_candidates'),
            ])
            ->groupBy('candidates.election_id', 'slate_blocks.slate_id')
            ->get()
            ->groupBy('election_id')
            ->map(function ($rows) {
                return $rows->keyBy('slate_id');
            });

        $scrutinyElectionIds = ScrutinyRecord::query()
            ->whereIn('election_id', $electionIds)
            ->whereIn('status', ['draft', 'pending', 'pending_review', 'reviewed', 'approved', 'consolidated'])
            ->distinct()
            ->pluck('election_id')
            ->all();
        $scrutinyElectionIdSet = array_flip($scrutinyElectionIds);

        $electionBlocks = ElectionBlock::query()
            ->with('block:id,name,code')
            ->whereIn('election_id', $electionIds)
            ->get(['id', 'election_id', 'block_id']);
        $electionBlocksByElection = $electionBlocks->groupBy('election_id');
        $electionBlockIds = $electionBlocks->pluck('id')->all();

        $vacanciesByElectionBlock = ElectionBlockPosition::query()
            ->whereIn('election_block_id', $electionBlockIds)
            ->select('election_block_id', DB::raw('COALESCE(SUM(vacancies), 0) AS total_vacancies'))
            ->groupBy('election_block_id')
            ->pluck('total_vacancies', 'election_block_id');

        $aggregatedResults = ScrutinyBlockResult::query()
            ->join('slate_blocks', 'slate_blocks.id', '=', 'scrutiny_block_results.slate_block_id')
            ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
            ->whereIn('scrutiny_block_results.election_id', $electionIds)
            ->whereIn('scrutiny_block_results.status', ['approved', 'reviewed'])
            ->select([
                'scrutiny_block_results.election_id',
                'scrutiny_block_results.election_block_id',
                'scrutiny_block_results.slate_block_id',
                'slates.name AS slate_name',
                DB::raw('SUM(scrutiny_block_results.votes) AS total_votes'),
            ])
            ->groupBy(
                'scrutiny_block_results.election_id',
                'scrutiny_block_results.election_block_id',
                'scrutiny_block_results.slate_block_id',
                'slates.name'
            )
            ->get();

        $resultsByElectionAndBlock = [];
        foreach ($aggregatedResults as $resultRow) {
            $key = (int) $resultRow->election_id.'|'.(int) $resultRow->election_block_id;
            if (! isset($resultsByElectionAndBlock[$key])) {
                $resultsByElectionAndBlock[$key] = [];
            }

            $resultsByElectionAndBlock[$key][] = [
                'plancha' => (string) $resultRow->slate_name,
                'votos' => (int) $resultRow->total_votes,
                'slate_block_id' => (int) $resultRow->slate_block_id,
            ];
        }

        $reportRows = [];
        $withRegisteredSlates = 0;
        $withoutRegisteredSlates = 0;
        $withScrutiny = 0;
        $withoutScrutiny = 0;

        foreach ($neighborhoods as $neighborhood) {
            $election = $activeElectionsByNeighborhood->get($neighborhood->id);

            if (! $election) {
                $withoutRegisteredSlates++;
                $withoutScrutiny++;

                $reportRows[] = [
                    'neighborhood_id' => $neighborhood->id,
                    'neighborhood_name' => $neighborhood->name,
                    'commune_name' => $neighborhood->commune?->name,
                    'has_active_election' => false,
                    'has_registered_slate' => false,
                    'has_scrutiny' => false,
                    'slates' => [],
                    'cuocientes' => [],
                    'warnings' => [
                        'Este barrio no tiene elección activa.',
                        'No se realiza cálculo de cuociente porque no tiene escrutinio hecho.',
                    ],
                ];

                continue;
            }

            $slates = collect($slatesByElection->get($election->id, collect()));
            $candidateCountBySlate = collect($candidateCountByElectionAndSlate->get($election->id, collect()));

            $slateRows = $slates->map(function ($slate) use ($candidateCountBySlate): array {
                $totalCandidates = (int) data_get($candidateCountBySlate->get($slate->id), 'total_candidates', 0);
                $isRegistered = $totalCandidates > 0;

                return [
                    'id' => $slate->id,
                    'name' => $slate->name,
                    'code' => $slate->code,
                    'registered' => $isRegistered,
                    'total_candidates' => $totalCandidates,
                    'message' => $isRegistered
                        ? 'Plancha registrada.'
                        : 'Esta plancha no se ha registrado.',
                ];
            })->values()->toArray();

            $hasRegisteredSlate = collect($slateRows)->contains(function (array $row): bool {
                return (bool) ($row['registered'] ?? false);
            });

            if ($hasRegisteredSlate) {
                $withRegisteredSlates++;
            } else {
                $withoutRegisteredSlates++;
            }

            $hasScrutiny = isset($scrutinyElectionIdSet[$election->id]);

            if ($hasScrutiny) {
                $withScrutiny++;
            } else {
                $withoutScrutiny++;
            }

            $warnings = [];
            if (! $hasRegisteredSlate) {
                $warnings[] = 'Esta plancha no se ha registrado.';
            }
            if (! $hasScrutiny) {
                $warnings[] = 'No se realiza cálculo de cuociente porque no tiene escrutinio hecho.';
            }

            $quotaRows = [];
            if ($hasScrutiny) {
                $electionBlocksForElection = collect($electionBlocksByElection->get($election->id, collect()));

                foreach ($electionBlocksForElection as $electionBlock) {
                    $resultKey = (int) $election->id.'|'.(int) $electionBlock->id;
                    $slateVotes = $resultsByElectionAndBlock[$resultKey] ?? [];

                    if (empty($slateVotes)) {
                        continue;
                    }

                    $cargosAProveer = (int) ($vacanciesByElectionBlock[$electionBlock->id] ?? 0);

                    $allocation = $this->allocateSeatsByQuota($slateVotes, $cargosAProveer, 0);

                    $quotaRows[] = [
                        'block_name' => $electionBlock->block->name ?? 'Bloque',
                        'block_code' => $electionBlock->block->code ?? null,
                        'cargos_a_proveer' => $cargosAProveer,
                        'votos_validos' => $allocation['votos_validos'],
                        'cuociente_electoral' => $allocation['cuociente_electoral'],
                        'planchas' => $allocation['planchas'],
                    ];
                }
            }

            $reportRows[] = [
                'neighborhood_id' => $neighborhood->id,
                'neighborhood_name' => $neighborhood->name,
                'commune_name' => $neighborhood->commune?->name,
                'has_active_election' => true,
                'has_registered_slate' => $hasRegisteredSlate,
                'has_scrutiny' => $hasScrutiny,
                'slates' => $slateRows,
                'cuocientes' => $quotaRows,
                'warnings' => $warnings,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'generated_at' => now()->toDateTimeString(),
                'summary' => [
                    'total_neighborhoods' => count($reportRows),
                    'with_registered_slates' => $withRegisteredSlates,
                    'without_registered_slates' => $withoutRegisteredSlates,
                    'with_scrutiny' => $withScrutiny,
                    'without_scrutiny' => $withoutScrutiny,
                ],
                'rows' => $reportRows,
            ],
        ]);
    }

    /**
     * Obtiene solo las comunas (para selector rápido)
     */
    public function communes(): JsonResponse
    {
        $communes = Commune::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn($commune) => [
                'id' => $commune->id,
                'name' => $commune->name,
            ])
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $communes,
        ]);
    }

    /**
     * Devuelve las comunas como FeatureCollection GeoJSON para pintarlas
     * e interactuar con ellas en el mapa electoral.
     */
    /**
     * Estados de scrutiny_records que cuentan como "acta recibida" (cualquier
     * punto del flujo, desde que se sube hasta que se consolida).
     */
    private const ACTA_RECIBIDA_ESTADOS = ['draft', 'pending', 'pending_review', 'reviewed', 'approved', 'consolidated'];

    public function communesGeo(): JsonResponse
    {
        $progress = $this->actaProgressByCommune();

        $features = Commune::query()
            ->whereNotNull('boundary')
            ->withCount('neighborhoods')
            ->orderBy('name')
            ->get()
            ->map(function (Commune $commune) use ($progress) {
                $mesasTotal = $progress[$commune->id]['total'] ?? 0;
                $mesasRecibidas = $progress[$commune->id]['recibidas'] ?? 0;
                $mesasAtrasadas = $progress[$commune->id]['atrasadas'] ?? 0;
                $pct = $mesasTotal > 0 ? (int) round($mesasRecibidas / $mesasTotal * 100) : 0;

                return [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $commune->id,
                        'name' => $commune->name,
                        'code' => $commune->code,
                        'neighborhoods_count' => $commune->neighborhoods_count,
                        'mesas_total' => $mesasTotal,
                        'mesas_recibidas' => $mesasRecibidas,
                        'mesas_atrasadas' => $mesasAtrasadas,
                        'actas_pct' => $pct,
                        'semaforo' => $this->semaforo($pct),
                    ],
                    'geometry' => $commune->boundary,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Mesas totales, con acta recibida y atrasadas (elección vencida o de
     * hoy pasada la hora de corte, sin acta), agrupadas por comuna.
     *
     * @return array<int, array{total: int, recibidas: int, atrasadas: int}>
     */
    private function actaProgressByCommune(): array
    {
        $today = now()->toDateString();
        $cutoffReached = now()->hour >= (int) config('electoral.acta_cutoff_hour', 16);

        $totales = DB::table('polling_tables as pt')
            ->join('elections as e', 'e.id', '=', 'pt.election_id')
            ->join('neighborhoods as n', 'n.id', '=', 'e.neighborhood_id')
            ->where('e.is_active', true)
            ->whereNull('n.deleted_at')
            ->selectRaw('n.commune_id as commune_id, count(distinct pt.id) as total')
            ->groupBy('n.commune_id')
            ->pluck('total', 'commune_id');

        $recibidas = DB::table('polling_tables as pt')
            ->join('elections as e', 'e.id', '=', 'pt.election_id')
            ->join('neighborhoods as n', 'n.id', '=', 'e.neighborhood_id')
            ->join('scrutiny_records as sr', function ($join) {
                $join->on('sr.polling_table_id', '=', 'pt.id')->whereNull('sr.deleted_at');
            })
            ->where('e.is_active', true)
            ->whereNull('n.deleted_at')
            ->whereIn('sr.status', self::ACTA_RECIBIDA_ESTADOS)
            ->selectRaw('n.commune_id as commune_id, count(distinct pt.id) as recibidas')
            ->groupBy('n.commune_id')
            ->pluck('recibidas', 'commune_id');

        $atrasadas = DB::table('polling_tables as pt')
            ->join('elections as e', 'e.id', '=', 'pt.election_id')
            ->join('neighborhoods as n', 'n.id', '=', 'e.neighborhood_id')
            ->where('e.is_active', true)
            ->whereNull('n.deleted_at')
            ->where(function ($q) use ($today, $cutoffReached) {
                $q->where('e.election_date', '<', $today);
                if ($cutoffReached) {
                    $q->orWhere('e.election_date', '=', $today);
                }
            })
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('scrutiny_records as sr')
                    ->whereColumn('sr.polling_table_id', 'pt.id')
                    ->whereNull('sr.deleted_at')
                    ->whereIn('sr.status', self::ACTA_RECIBIDA_ESTADOS);
            })
            ->selectRaw('n.commune_id as commune_id, count(distinct pt.id) as atrasadas')
            ->groupBy('n.commune_id')
            ->pluck('atrasadas', 'commune_id');

        $communeIds = $totales->keys()->merge($recibidas->keys())->merge($atrasadas->keys())->unique();

        return $communeIds->mapWithKeys(fn ($id) => [
            $id => [
                'total' => (int) ($totales[$id] ?? 0),
                'recibidas' => (int) ($recibidas[$id] ?? 0),
                'atrasadas' => (int) ($atrasadas[$id] ?? 0),
            ],
        ])->all();
    }

    /**
     * Umbral de avance de recepcion de actas, pensado para lectura a
     * distancia en el mapa (sin ver los puntos individuales): por debajo de
     * 30% se considera critico (rojo), entre 30% y 79% en progreso
     * (amarillo), y de 80% en adelante practicamente completo (verde).
     */
    private function semaforo(int $pct): string
    {
        if ($pct >= 80) {
            return 'verde';
        }

        return $pct >= 30 ? 'amarillo' : 'rojo';
    }

    /**
     * Devuelve un punto por cada barrio / JAC que ya tiene coordenadas,
     * como FeatureCollection GeoJSON, agrupable por comuna en el mapa.
     * Incluye si tiene acta recibida, si esta atrasada, y el ganador de
     * la presidencia cuando ya hay resultados escrutados.
     */
    public function neighborhoodsGeo(): JsonResponse
    {
        $barrios = Neighborhood::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('commune:id,name,code')
            ->orderBy('commune_id')
            ->orderBy('map_order')
            ->get();

        $neighborhoodIds = $barrios->pluck('id');

        $elections = Election::query()
            ->whereIn('neighborhood_id', $neighborhoodIds)
            ->where('is_active', true)
            ->get(['id', 'neighborhood_id', 'election_date'])
            ->keyBy('neighborhood_id');

        $electionIds = $elections->pluck('id');

        $pollingTableIdsByElection = PollingTable::query()
            ->whereIn('election_id', $electionIds)
            ->where('is_active', true)
            ->get(['id', 'election_id'])
            ->groupBy('election_id')
            ->map(fn ($rows) => $rows->pluck('id'));

        $allPollingTableIds = $pollingTableIdsByElection->flatten();

        $pollingTablesConActa = DB::table('scrutiny_records')
            ->whereIn('polling_table_id', $allPollingTableIds)
            ->whereNull('deleted_at')
            ->whereIn('status', self::ACTA_RECIBIDA_ESTADOS)
            ->distinct()
            ->pluck('polling_table_id')
            ->flip();

        $winnersByElection = $this->winningPresidents($electionIds->all());

        $today = now()->toDateString();
        $cutoffReached = now()->hour >= (int) config('electoral.acta_cutoff_hour', 16);

        $features = $barrios->map(function (Neighborhood $barrio) use (
            $elections,
            $pollingTableIdsByElection,
            $pollingTablesConActa,
            $winnersByElection,
            $today,
            $cutoffReached
        ) {
            $election = $elections->get($barrio->id);
            $hasActa = false;
            $atrasada = false;
            $winner = null;

            if ($election) {
                $tableIds = $pollingTableIdsByElection->get($election->id, collect());
                $hasActa = $tableIds->contains(fn ($id) => $pollingTablesConActa->has($id));

                $electionDate = $election->election_date instanceof \DateTimeInterface
                    ? $election->election_date->format('Y-m-d')
                    : (string) $election->election_date;

                $venceHoy = $electionDate === $today && $cutoffReached;
                $atrasada = ! $hasActa && ($electionDate < $today || $venceHoy);

                $winner = $winnersByElection[$election->id] ?? null;
            }

            return [
                'type' => 'Feature',
                'properties' => [
                    'id' => $barrio->id,
                    'name' => $barrio->name,
                    'code' => $barrio->code,
                    'commune_id' => $barrio->commune_id,
                    'commune_name' => $barrio->commune?->name,
                    'commune_code' => $barrio->commune?->code,
                    'map_order' => $barrio->map_order,
                    'has_acta' => $hasActa,
                    'atrasada' => $atrasada,
                    'winner' => $winner,
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $barrio->longitude, (float) $barrio->latitude],
                ],
            ];
        })->values()->all();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Nombre del presidente de la plancha ganadora (mas votos en el bloque
     * Directiva) de cada elección, en dos consultas para todas a la vez
     * (antes eran 2-3 consultas por elección, en cada refresco del mapa).
     *
     * @param  array<int, int>  $electionIds
     * @return array<int, string>  election_id => nombre
     */
    private function winningPresidents(array $electionIds): array
    {
        if ($electionIds === []) {
            return [];
        }

        // Ordenado por votos: el primer resultado de cada elección es el ganador.
        $winningSlateBlocks = DB::table('scrutiny_block_results as sbr')
            ->join('election_blocks as eb', 'eb.id', '=', 'sbr.election_block_id')
            ->join('blocks as b', 'b.id', '=', 'eb.block_id')
            ->where('b.code', 'DIR')
            ->whereIn('sbr.election_id', $electionIds)
            ->orderByDesc('sbr.votes')
            ->get(['sbr.election_id', 'sbr.slate_block_id'])
            ->unique('election_id')
            ->filter(fn ($row) => $row->slate_block_id !== null)
            ->pluck('slate_block_id', 'election_id');

        if ($winningSlateBlocks->isEmpty()) {
            return [];
        }

        $presidents = DB::table('candidates as c')
            ->join('election_block_positions as ebp', 'ebp.id', '=', 'c.election_block_position_id')
            ->join('positions as p', 'p.id', '=', 'ebp.position_id')
            ->join('persons as pe', 'pe.id', '=', 'c.person_id')
            ->where('p.code', 'DIR_PRES')
            ->whereIn('c.election_id', $winningSlateBlocks->keys())
            ->whereIn('c.slate_block_id', $winningSlateBlocks->values())
            ->orderBy('c.id')
            ->get(['c.election_id', 'c.slate_block_id', 'pe.first_name', 'pe.last_name']);

        $winners = [];
        foreach ($presidents as $row) {
            $electionId = (int) $row->election_id;

            if (isset($winners[$electionId]) || (int) $winningSlateBlocks[$electionId] !== (int) $row->slate_block_id) {
                continue;
            }

            $winners[$electionId] = trim($row->first_name.' '.$row->last_name);
        }

        return $winners;
    }

    /**
     * Ubica (o reubica) manualmente un barrio en el mapa: se ingresa la
     * coordenada a mano desde el panel de administracion, sin depender de
     * un archivo GeoJSON. Si es la primera vez que se ubica, se le asigna
     * el siguiente orden dentro de su comuna.
     */
    public function updateLocation(Request $request, int $id): JsonResponse
    {
        $barrio = Neighborhood::with('commune')->find($id);

        if (! $barrio) {
            return response()->json(['success' => false, 'message' => 'Barrio no encontrado.'], 404);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // La coordenada solo se acepta si cae dentro del contorno de la
        // comuna a la que pertenece el barrio; evita ubicar por error un
        // barrio de una comuna dentro del territorio de otra.
        $ring = $barrio->commune?->boundary['coordinates'][0] ?? null;

        if ($ring && ! $this->pointInPolygon((float) $validated['latitude'], (float) $validated['longitude'], $ring)) {
            throw ValidationException::withMessages([
                'latitude' => "La coordenada debe estar dentro del contorno de {$barrio->commune->name}.",
            ]);
        }

        if ($barrio->map_order === null) {
            $maxOrder = Neighborhood::where('commune_id', $barrio->commune_id)->max('map_order');
            $validated['map_order'] = $maxOrder === null ? 0 : $maxOrder + 1;
        }

        $barrio->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $barrio->id,
                'name' => $barrio->name,
                'latitude' => (float) $barrio->latitude,
                'longitude' => (float) $barrio->longitude,
                'map_order' => $barrio->map_order,
            ],
        ]);
    }

    /**
     * Quita la ubicacion de un barrio (por si se ingreso por error).
     */
    public function clearLocation(int $id): JsonResponse
    {
        $barrio = Neighborhood::find($id);

        if (! $barrio) {
            return response()->json(['success' => false, 'message' => 'Barrio no encontrado.'], 404);
        }

        $barrio->update(['latitude' => null, 'longitude' => null]);

        return response()->json(['success' => true]);
    }

    /**
     * Crea un barrio nuevo a mano (sin depender de un GeoJSON), directamente
     * desde el panel del mapa. El codigo se genera a partir del nombre.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commune_id' => ['required', 'integer', 'exists:communes,id'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $commune = Commune::find($validated['commune_id']);
        $name = trim($validated['name']);

        $this->assertNameAvailable($commune->id, $name);

        $barrio = Neighborhood::create([
            'commune_id' => $commune->id,
            'name' => $name,
            'code' => $this->generateNeighborhoodCode($commune, $name),
            'type' => 'barrio',
            'source_name' => 'Ingreso manual',
            'is_verified' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $barrio->id,
                'name' => $barrio->name,
                'code' => $barrio->code,
                'commune_id' => $barrio->commune_id,
                'has_coordinates' => false,
            ],
        ], 201);
    }

    /**
     * Renombra un barrio existente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $barrio = Neighborhood::find($id);

        if (! $barrio) {
            return response()->json(['success' => false, 'message' => 'Barrio no encontrado.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($validated['name']);
        $this->assertNameAvailable($barrio->commune_id, $name, excludeId: $barrio->id);

        $barrio->update(['name' => $name]);

        return response()->json([
            'success' => true,
            'data' => ['id' => $barrio->id, 'name' => $barrio->name],
        ]);
    }

    /**
     * Elimina (borrado suave) un barrio ingresado manualmente. Se bloquea si
     * ya tiene actas registradas, para no perder evidencia electoral real.
     */
    public function destroy(int $id): JsonResponse
    {
        $barrio = Neighborhood::find($id);

        if (! $barrio) {
            return response()->json(['success' => false, 'message' => 'Barrio no encontrado.'], 404);
        }

        $tieneActas = ScrutinyRecord::whereHas('election', fn ($q) => $q->where('neighborhood_id', $barrio->id))
            ->whereIn('status', self::ACTA_RECIBIDA_ESTADOS)
            ->exists();

        if ($tieneActas) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar: este barrio ya tiene actas registradas.',
            ], 422);
        }

        $barrio->delete();

        return response()->json(['success' => true]);
    }

    private function assertNameAvailable(int $communeId, string $name, ?int $excludeId = null): void
    {
        $exists = Neighborhood::where('commune_id', $communeId)
            ->where('name', $name)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un barrio con ese nombre en esta comuna.',
            ]);
        }
    }

    private function generateNeighborhoodCode(Commune $commune, string $name): string
    {
        $slug = strtoupper(Str::of($name)->ascii()->slug('-'));
        $base = $commune->code.'-'.$slug;
        $code = $base;
        $suffix = 1;

        while (Neighborhood::where('commune_id', $commune->id)->where('code', $code)->exists()) {
            $code = $base.'-'.(++$suffix);
        }

        return $code;
    }

    /**
     * Ray casting estandar: true si (lat, lng) cae dentro del anillo de un
     * Polygon GeoJSON (coordenadas en [lng, lat], como las guarda Commune::boundary).
     *
     * @param  list<array{0: float, 1: float}>  $ring
     */
    private function pointInPolygon(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    public function listForForms(Request $request): JsonResponse
    {
        $query = Neighborhood::query()
            ->select('id', 'name', 'commune_id', 'latitude', 'longitude')
            ->orderBy('name', 'asc');

        // Filtrar por comuna si se proporciona
        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->integer('commune_id'));
        }

        $neighborhoods = $query
            ->limit(100)
            ->get()
            ->map(fn($neighborhood) => [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'has_coordinates' => $neighborhood->latitude !== null && $neighborhood->longitude !== null,
                'latitude' => $neighborhood->latitude,
                'longitude' => $neighborhood->longitude,
            ])
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => $neighborhoods
        ]);
    }

    private function allocateSeatsByQuota(array $planchas, int $cargosAProveer, int $blancos): array
    {
        $normalizedPlanchas = [];
        $votosPlanchas = 0;

        foreach ($planchas as $row) {
            $votes = max(0, (int) ($row['votos'] ?? 0));
            $normalizedPlanchas[] = [
                'plancha' => (string) ($row['plancha'] ?? 'Sin nombre'),
                'votos' => $votes,
                'slate_block_id' => $row['slate_block_id'] ?? null,
                'entero' => 0,
                'residuo' => 0.0,
                'curules' => 0,
            ];
            $votosPlanchas += $votes;
        }

        $votosValidos = $votosPlanchas + max(0, $blancos);
        $cuocienteElectoral = $cargosAProveer > 0 ? ($votosValidos / $cargosAProveer) : 0.0;

        $cargosAsignados = 0;
        foreach ($normalizedPlanchas as $index => $plancha) {
            if ($cuocienteElectoral > 0) {
                $exacto = $plancha['votos'] / $cuocienteElectoral;
                $entero = (int) floor($exacto);
                $residuo = $exacto - $entero;
            } else {
                $entero = 0;
                $residuo = 0.0;
            }

            $normalizedPlanchas[$index]['entero'] = $entero;
            $normalizedPlanchas[$index]['residuo'] = $residuo;
            $normalizedPlanchas[$index]['curules'] = $entero;
            $cargosAsignados += $entero;
        }

        $cargosRestantes = max(0, $cargosAProveer - $cargosAsignados);

        usort($normalizedPlanchas, function (array $left, array $right): int {
            return ($right['residuo'] <=> $left['residuo'])
                ?: ($right['votos'] <=> $left['votos'])
                ?: strcmp($left['plancha'], $right['plancha']);
        });

        // Las curules que no alcanzaron cuociente entero van a los mayores
        // residuos. Solo compiten planchas con votos: antes el reparto rotaba
        // por todas y una plancha con 0 votos podía quedarse con una curul.
        $conVotos = array_keys(array_filter($normalizedPlanchas, fn (array $plancha): bool => $plancha['votos'] > 0));
        $countConVotos = count($conVotos);
        if ($countConVotos > 0 && $cargosRestantes > 0) {
            for ($seat = 0; $seat < $cargosRestantes; $seat++) {
                $normalizedPlanchas[$conVotos[$seat % $countConVotos]]['curules']++;
            }
        }

        usort($normalizedPlanchas, function (array $left, array $right): int {
            return ($right['curules'] <=> $left['curules'])
                ?: ($right['votos'] <=> $left['votos'])
                ?: ($right['residuo'] <=> $left['residuo'])
                ?: strcmp($left['plancha'], $right['plancha']);
        });

        $maxCurules = $normalizedPlanchas[0]['curules'] ?? 0;
        $winners = array_values(array_filter($normalizedPlanchas, function (array $plancha) use ($maxCurules): bool {
            return (int) ($plancha['curules'] ?? 0) === (int) $maxCurules;
        }));

        $winner = count($winners) === 1 ? $winners[0] : null;

        return [
            'votos_validos' => $votosValidos,
            'cuociente_electoral' => $cuocienteElectoral,
            'cargos_a_proveer' => $cargosAProveer,
            'cargos_asignados' => $cargosAsignados,
            'cargos_restantes' => $cargosRestantes,
            'planchas' => $normalizedPlanchas,
            'winner' => $winner,
            'winners' => $winners,
        ];
    }

    private function normalizeBlockName(string $name): string
    {
        // Sin tildes: el OCR lee "COMISIÓN ... CONCILIACIÓN" y el catálogo
        // guarda "Comision ... conciliacion"; deben ser el mismo bloque.
        $normalized = mb_strtolower(trim(Str::ascii($name)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    /**
     * Provee cada cargo del bloque UNA sola vez.
     *
     * Las planchas llegan ordenadas como las deja allocateSeatsByQuota (más
     * curules y más votos primero). Cada curul ocupa el siguiente cargo en
     * jerarquía (order_number): la plancha mayoritaria provee la presidencia y
     * los primeros cargos; la siguiente continúa donde quedó la anterior. Para
     * cada cargo se toma al candidato PRINCIPAL que esa plancha inscribió para
     * ese mismo cargo; su suplente se informa aparte.
     *
     * Antes cada plancha llenaba cargos desde el primero: dos planchas con
     * curules daban dos presidentes y nadie quedaba de tesorero o secretario.
     *
     * @param  array<int, array<string, mixed>>  $planchas
     * @param  Collection  $blockPositions  cargos del bloque (ElectionBlockPosition con position)
     * @param  Collection  $blockCandidates  candidatos del bloque, con person cargado
     * @param  array<string, int>  $slateBlockIdByName  nombre de plancha (minúsculas) => slate_block_id
     */
    private function assignCargosBySeats(
        array $planchas,
        Collection $blockPositions,
        Collection $blockCandidates,
        array $slateBlockIdByName
    ): array {
        // Un puesto por vacante, en orden jerárquico (sin orden, al final).
        $slots = $blockPositions
            ->sort(fn ($a, $b) => [$a->position?->order_number ?? PHP_INT_MAX, $a->id]
                <=> [$b->position?->order_number ?? PHP_INT_MAX, $b->id])
            ->flatMap(fn ($ebp) => array_fill(0, max(0, (int) $ebp->vacancies), $ebp))
            ->values();

        $cargos = [];
        $usedCandidateIds = [];
        $slotIndex = 0;

        foreach ($planchas as $plancha) {
            $seats = max(0, (int) ($plancha['curules'] ?? 0));
            $nombrePlancha = (string) ($plancha['plancha'] ?? '—');
            $slateBlockId = $plancha['slate_block_id']
                ?? ($slateBlockIdByName[mb_strtolower(trim($nombrePlancha))] ?? null);

            $delaPlancha = $slateBlockId
                ? $blockCandidates->where('slate_block_id', (int) $slateBlockId)->sortBy('id')
                : collect();

            for ($seat = 0; $seat < $seats && $slotIndex < $slots->count(); $seat++, $slotIndex++) {
                $ebp = $slots[$slotIndex];
                $delCargo = $delaPlancha->where('election_block_position_id', $ebp->id);

                $principal = $delCargo->first(
                    fn ($c) => ! $c->is_substitute && ! isset($usedCandidateIds[$c->id])
                );
                $suplente = $delCargo->first(
                    fn ($c) => $c->is_substitute && ! isset($usedCandidateIds[$c->id])
                );

                foreach ([$principal, $suplente] as $used) {
                    if ($used) {
                        $usedCandidateIds[$used->id] = true;
                    }
                }

                $cargos[] = [
                    'cargo' => $ebp->position->name ?? 'Sin cargo',
                    'plancha' => $nombrePlancha,
                    'persona' => $principal?->person
                        ? $this->personaPayload($principal->person)
                        : ['nombre' => '—', 'identificacion' => '—', 'celular' => '—', 'correo' => '—'],
                    'suplente' => $suplente?->person ? $this->personFullName($suplente->person) : null,
                    // La plancha ganó la curul pero no tiene inscrito a nadie para este cargo.
                    'sin_candidato' => $principal === null,
                ];
            }
        }

        return $cargos;
    }

    private function personaPayload($person): array
    {
        return [
            'nombre' => $this->personFullName($person),
            'identificacion' => $person->document_number ?? '—',
            'celular' => $person->phone ?? '—',
            'correo' => $person->email ?? '—',
        ];
    }

    private function personFullName($person): string
    {
        return trim(implode(' ', array_filter([
            $person->first_name ?? null,
            $person->middle_name ?? null,
            $person->last_name ?? null,
            $person->second_last_name ?? null,
        ])));
    }

    /**
     * Endpoint ligero para el autocompletado de barrios al crear una Persona.
     */
    public function searchForDropdown(Request $request): JsonResponse
    {
        $term = $request->query('q');

        $query = Neighborhood::query()
            ->select('id', 'name', 'commune_id')
            ->with('commune:id,name');

        if (!empty($term)) {
            $likeTerm = '%'.mb_strtolower((string) $term).'%';

            $query->where(function ($q) use ($likeTerm): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$likeTerm])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$likeTerm]);
            });
        }

        $neighborhoods = $query->orderBy('name')->limit(15)->get();

        $data = $neighborhoods
            ->map(fn($neighborhood) => [
                'id' => $neighborhood->id,
                'label' => $neighborhood->name . ' (' . ($neighborhood->commune->name ?? 'Sin comuna') . ')'
            ])
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }
}