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
                    return (int) ($plancha['curules'] ?? 0) > 0 && ! empty($plancha['slate_block_id']);
                })
                ->values();

            $candidatosPorPlancha = collect();
            if ($planchasCurules->isNotEmpty()) {
                $candidatosPorPlancha = Candidate::with([
                    'person',
                    'electionBlockPosition.position',
                    'slateBlock.slate',
                ])
                    ->where('election_id', $election->id)
                    ->whereIn('slate_block_id', $planchasCurules->pluck('slate_block_id')->all())
                    ->whereHas('electionBlockPosition', function ($query) use ($eb): void {
                        $query->where('election_block_id', $eb->id);
                    })
                    ->orderByRaw('COALESCE(ballot_number, \'\') ASC')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('slate_block_id');
            }

            foreach ($planchasCurules as $planchaCurules) {
                $slateBlockId = (int) $planchaCurules['slate_block_id'];
                $seatsForSlate = max(0, (int) ($planchaCurules['curules'] ?? 0));
                if ($seatsForSlate === 0) {
                    continue;
                }

                $candidatos = collect($candidatosPorPlancha->get($slateBlockId, []))
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
                        'plancha' => data_get($c, 'slateBlock.slate.name', '—'),
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
                    'total' => $allocation['votos_validos'] + $nulos,
                    'blancos' => $blancos,
                    'nulos' => $nulos,
                ],
            ];
        }

        foreach ($aggregatedOcrBlocks as $normalizedName => $ocrBlock) {
            if (isset($processedBlockNames[$normalizedName])) {
                continue;
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
            $allocation = $this->allocateSeatsByQuota($votosPlanchas, $cargosAProveer, (int) ($ocrBlock['votes']['blancos'] ?? 0));

            foreach ($allocation['planchas'] as $voteRow) {
                $overallSlateVotes[$voteRow['plancha']] = ($overallSlateVotes[$voteRow['plancha']] ?? 0) + (int) $voteRow['votos'];
            }

            $resultadosFormateados[] = [
                'nombre_bloque' => $ocrBlock['name'],
                'codigo_bloque' => null,
                'cargos_a_proveer' => $cargosAProveer,
                'votos_validos' => $allocation['votos_validos'],
                'cuociente_electoral' => $allocation['cuociente_electoral'],
                'votos_planchas' => $allocation['planchas'],
                'cargos' => [],
                'plancha_ganadora' => $allocation['winner'],
                'estadisticas' => [
                    'validos' => $allocation['votos_validos'],
                    'total' => $allocation['votos_validos'] + (int) ($ocrBlock['votes']['nulos'] ?? 0),
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