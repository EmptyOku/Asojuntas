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
use Illuminate\Support\Facades\DB;

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

        // 🔥 PAGINACIÓN DE SERVIDOR: Solo procesamos 15 registros por consulta
        $neighborhoodsPaginator = $query->orderBy('name', 'asc')->paginate(15);

        // Transformamos solo los 15 registros de la página actual
        $neighborhoodsItems = collect($neighborhoodsPaginator->items())
            ->map(fn($neighborhood) => $this->toNeighborhoodRow($neighborhood))
            ->values()
            ->toArray();

        // Conteos masivos para indicadores superiores
        $bulkBaseQuery = $this->filteredNeighborhoodQuery($request);
        $bulkCreateCount = (clone $bulkBaseQuery)
            ->whereDoesntHave('elections', function ($electionQuery): void {
                $electionQuery->where('is_active', true);
            })
            ->count();

        $bulkCloseCount = (clone $bulkBaseQuery)
            ->whereHas('elections', function ($electionQuery): void {
                $electionQuery->where('is_active', true);
            })
            ->count();

        $communes = Commune::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn($commune) => [
                'id' => $commune->id,
                'name' => $commune->name,
            ])
            ->toArray();

        $response = [
            'success' => true,
            'data' => [
                'neighborhoods' => $neighborhoodsItems,
                'pagination' => [
                    'current_page' => $neighborhoodsPaginator->currentPage(),
                    'last_page'    => $neighborhoodsPaginator->lastPage(),
                    'per_page'     => $neighborhoodsPaginator->perPage(),
                    'total'        => $neighborhoodsPaginator->total(),
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

            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
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
            $president = $latestElection->candidates->first(function ($candidate) {
                $positionName = strtolower((string) data_get($candidate, 'electionBlockPosition.position.name', ''));
                return str_contains($positionName, 'presidente') && ! str_contains($positionName, 'vice');
            });

            $vicepresident = $latestElection->candidates->first(function ($candidate) {
                $positionName = strtolower((string) data_get($candidate, 'electionBlockPosition.position.name', ''));
                return str_contains($positionName, 'vicepresidente');
            });

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

        $resultadosFormateados = [];
        $overallSlateVotes = [];
        $processedBlockNames = [];

        foreach ($electionBlocks as $eb) {
            $blockName = (string) ($eb->block->name ?? 'Bloque');
            $blockNormalizedName = $this->normalizeBlockName($blockName);
            if ($blockNormalizedName !== '') {
                $processedBlockNames[$blockNormalizedName] = true;
            }

            $resultadosBloque = ScrutinyBlockResult::with(['slateBlock.slate'])
                ->where('election_id', $election->id)
                ->where('election_block_id', $eb->id)
                ->whereIn('status', ['approved', 'reviewed'])
                ->get();

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

            $cargosAProveer = (int) ElectionBlockPosition::query()
                ->where('election_block_id', $eb->id)
                ->sum('vacancies');
            $allocation = $this->allocateSeatsByQuota(array_values($slateVotes), $cargosAProveer, $blancos);

            foreach ($allocation['planchas'] as $voteRow) {
                $overallSlateVotes[$voteRow['plancha']] = ($overallSlateVotes[$voteRow['plancha']] ?? 0) + (int) $voteRow['votos'];
            }

            $winner = $allocation['winner'];
            $cargos = [];
            $planchasCurules = collect($allocation['planchas'])
                ->filter(function (array $plancha): bool {
                    return (int) ($plancha['curules'] ?? 0) > 0;
                })
                ->values();

            foreach ($planchasCurules as $planchaCurules) {
                $seatsForSlate = max(0, (int) ($planchaCurules['curules'] ?? 0));
                if ($seatsForSlate === 0) {
                    continue;
                }

                $planchaNombre = (string) ($planchaCurules['plancha'] ?? '—');
                $slateBlockId = $this->resolveSlateBlockIdByPlanchaName($election->id, $eb->id, $planchaNombre);
                $candidatos = collect();

                if ($slateBlockId) {
                    $candidatos = Candidate::with([
                        'person',
                        'electionBlockPosition.position',
                        'slateBlock.slate',
                    ])
                        ->where('election_id', $election->id)
                        ->where('slate_block_id', $slateBlockId)
                        ->whereHas('electionBlockPosition', function ($query) use ($eb): void {
                            $query->where('election_block_id', $eb->id);
                        })
                        ->orderByRaw('COALESCE(ballot_number, \'\') ASC')
                        ->orderBy('id')
                        ->get();
                }

                if ($candidatos->isNotEmpty()) {
                    $candidatos = $candidatos
                        ->sort(function ($left, $right): int {
                            $leftOrder = (int) data_get($left, 'electionBlockPosition.position.order_number', PHP_INT_MAX);
                            $rightOrder = (int) data_get($right, 'electionBlockPosition.position.order_number', PHP_INT_MAX);

                            return $leftOrder <=> $rightOrder
                                ?: strcmp((string) ($left->ballot_number ?? ''), (string) ($right->ballot_number ?? ''))
                                ?: ($left->id <=> $right->id);
                        })
                        ->unique(function ($candidate) {
                            return $candidate->election_block_position_id;
                        })
                        ->take($seatsForSlate)
                        ->values();

                    foreach ($candidatos as $c) {
                        $person = $c->person;
                        $position = $c->electionBlockPosition->position ?? null;

                        $cargos[] = [
                            'cargo' => $position->name ?? 'Sin cargo',
                            'plancha' => data_get($c, 'slateBlock.slate.name', $planchaNombre),
                            'persona' => [
                                'nombre' => trim(
                                    $person->first_name . ' ' .
                                    ($person->middle_name ? $person->middle_name . ' ' : '') .
                                    $person->last_name . ' ' .
                                    ($person->second_last_name ?? '')
                                ),
                                'identificacion' => $person->document_number ?? '—',
                                'celular' => $person->phone ?? '—',
                                'correo' => $person->email ?? '—',
                            ],
                        ];
                    }
                    continue;
                }

                $cargosPlancha = $this->buildCargosFromPlanchaName(
                    $election->id,
                    $eb->id,
                    $planchaNombre,
                    $seatsForSlate
                );

                if (! empty($cargosPlancha)) {
                    $cargos = array_merge($cargos, $cargosPlancha);
                    continue;
                }

                $cargos = array_merge(
                    $cargos,
                    $this->buildEmptyCargoEntries($eb->id, $planchaNombre, $seatsForSlate)
                );
            }

            if (empty($cargos) && $cargosAProveer > 0) {
                $cargos = $this->buildCargosFromCandidates($election->id, $eb->id, $cargosAProveer);
            }

            if (empty($cargos) && $cargosAProveer > 0) {
                $cargos = $this->buildEmptyCargoEntries($eb->id, (string) $blockName, $cargosAProveer);
            }

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
                $cargosAProveer = (int) ElectionBlockPosition::query()
                    ->where('election_block_id', $resolvedElectionBlock->id)
                    ->sum('vacancies');

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

    public function listForForms(Request $request): JsonResponse
    {
        $query = Neighborhood::query()
            ->select('id', 'name', 'commune_id')
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

        $countPlanchas = count($normalizedPlanchas);
        if ($countPlanchas > 0 && $cargosRestantes > 0) {
            for ($seat = 0; $seat < $cargosRestantes; $seat++) {
                $normalizedPlanchas[$seat % $countPlanchas]['curules']++;
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
        $normalized = mb_strtolower(trim($name));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function buildCargosFromCandidates(int $electionId, int $electionBlockId, int $limit): array
    {
        $candidatos = Candidate::with([
            'person',
            'electionBlockPosition.position',
            'slateBlock.slate',
        ])
            ->where('election_id', $electionId)
            ->whereHas('electionBlockPosition', function ($query) use ($electionBlockId): void {
                $query->where('election_block_id', $electionBlockId);
            })
            ->get()
            ->sort(function ($left, $right): int {
                $leftOrder = (int) data_get($left, 'electionBlockPosition.position.order_number', PHP_INT_MAX);
                $rightOrder = (int) data_get($right, 'electionBlockPosition.position.order_number', PHP_INT_MAX);

                return $leftOrder <=> $rightOrder
                    ?: strcmp((string) ($left->ballot_number ?? ''), (string) ($right->ballot_number ?? ''))
                    ?: ($left->id <=> $right->id);
            })
            ->unique('election_block_position_id')
            ->take($limit)
            ->values();

        return $candidatos->map(function ($candidate): array {
            $person = $candidate->person;
            $position = $candidate->electionBlockPosition->position ?? null;

            return [
                'cargo' => $position->name ?? 'Sin cargo',
                'plancha' => data_get($candidate, 'slateBlock.slate.name', '—'),
                'persona' => [
                    'nombre' => trim(
                        $person->first_name . ' ' .
                        ($person->middle_name ? $person->middle_name . ' ' : '') .
                        $person->last_name . ' ' .
                        ($person->second_last_name ?? '')
                    ),
                    'identificacion' => $person->document_number ?? '—',
                    'celular' => $person->phone ?? '—',
                    'correo' => $person->email ?? '—',
                ],
            ];
        })->all();
    }

    private function buildEmptyCargoEntries(int $electionBlockId, string $planchaName, int $limit): array
    {
        $positions = ElectionBlockPosition::with('position')
            ->where('election_block_id', $electionBlockId)
            ->orderBy('id')
            ->get();

        if ($positions->isEmpty()) {
            return array_fill(0, max(1, $limit), [
                'cargo' => 'Sin cargo',
                'plancha' => $planchaName,
                'persona' => [
                    'nombre' => '—',
                    'identificacion' => '—',
                    'celular' => '—',
                    'correo' => '—',
                ],
            ]);
        }

        return $positions
            ->take(max(1, $limit))
            ->map(function ($position) use ($planchaName): array {
            return [
                'cargo' => $position->position->name ?? 'Sin cargo',
                'plancha' => $planchaName,
                'persona' => [
                    'nombre' => '—',
                    'identificacion' => '—',
                    'celular' => '—',
                    'correo' => '—',
                ],
            ];
        })
        ->values()
        ->all();
    }

    private function buildCargosFromPlanchaName(int $electionId, int $electionBlockId, string $planchaName, int $limit): array
    {
        $normalizedPlancha = mb_strtolower(trim($planchaName));
        if ($normalizedPlancha === '') {
            return [];
        }

        $candidatos = Candidate::with([
            'person',
            'electionBlockPosition.position',
            'slateBlock.slate',
        ])
            ->where('election_id', $electionId)
            ->whereHas('electionBlockPosition', function ($query) use ($electionBlockId): void {
                $query->where('election_block_id', $electionBlockId);
            })
            ->whereHas('slateBlock.slate', function ($query) use ($normalizedPlancha): void {
                $query->whereRaw('LOWER(name) = ?', [$normalizedPlancha]);
            })
            ->orderByRaw('COALESCE(ballot_number, \'\') ASC')
            ->orderBy('id')
            ->get()
            ->sort(function ($left, $right): int {
                $leftOrder = (int) data_get($left, 'electionBlockPosition.position.order_number', PHP_INT_MAX);
                $rightOrder = (int) data_get($right, 'electionBlockPosition.position.order_number', PHP_INT_MAX);

                return $leftOrder <=> $rightOrder
                    ?: strcmp((string) ($left->ballot_number ?? ''), (string) ($right->ballot_number ?? ''))
                    ?: ($left->id <=> $right->id);
            })
            ->unique('election_block_position_id')
            ->take(max(1, $limit))
            ->values();

        return $candidatos->map(function ($candidate) use ($planchaName): array {
            $person = $candidate->person;
            $position = $candidate->electionBlockPosition->position ?? null;

            return [
                'cargo' => $position->name ?? 'Sin cargo',
                'plancha' => data_get($candidate, 'slateBlock.slate.name', $planchaName),
                'persona' => [
                    'nombre' => trim(
                        $person->first_name . ' ' .
                        ($person->middle_name ? $person->middle_name . ' ' : '') .
                        $person->last_name . ' ' .
                        ($person->second_last_name ?? '')
                    ),
                    'identificacion' => $person->document_number ?? '—',
                    'celular' => $person->phone ?? '—',
                    'correo' => $person->email ?? '—',
                ],
            ];
        })->all();
    }

    private function resolveSlateBlockIdByPlanchaName(int $electionId, int $electionBlockId, string $planchaName): ?int
    {
        return \App\Models\SlateBlock::query()
            ->join('slates', 'slates.id', '=', 'slate_blocks.slate_id')
            ->where('slate_blocks.election_id', $electionId)
            ->where('slate_blocks.election_block_id', $electionBlockId)
            ->whereRaw('LOWER(slates.name) = ?', [mb_strtolower(trim($planchaName))])
            ->value('slate_blocks.id');
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
            $query->where('name', 'ilike', "%{$term}%")
                  ->orWhere('code', 'ilike', "%{$term}%");
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