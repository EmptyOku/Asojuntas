<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use App\Models\ElectionBlock;
use App\Models\ScrutinyBlockResult;
use App\Models\Candidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeighborhoodDirectoryController extends Controller
{
    /**
     * Lista todos los barrios con sus respectivos presidentes y vicepresidentes.
     */
    public function index(): JsonResponse
    {
        $neighborhoods = Neighborhood::with([
            'commune',
            'elections' => function ($query) {
                $query->latest('election_date')->where('is_active', true);
            },
            'elections.candidates' => function ($query) {
                $query->where('is_active', true);
            },
            'elections.candidates.person',
            'elections.candidates.electionBlockPosition.position'
        ])
        ->orderBy('name', 'asc')
        ->get();

        $data = $neighborhoods->map(function ($neighborhood) {
            $latestElection = $neighborhood->elections->first();
            $presidentName = null;
            $vicepresidentName = null;

            if ($latestElection) {
                $president = $latestElection->candidates->first(function ($candidate) {
                    $positionName = strtolower($candidate->electionBlockPosition->position->name ?? '');
                    return str_contains($positionName, 'presidente') && !str_contains($positionName, 'vice');
                });

                $vicepresident = $latestElection->candidates->first(function ($candidate) {
                    $positionName = strtolower($candidate->electionBlockPosition->position->name ?? '');
                    return str_contains($positionName, 'vicepresidente');
                });

                if ($president && $president->person) {
                    $presidentName = $president->person->first_name . ' ' . $president->person->last_name;
                }

                if ($vicepresident && $vicepresident->person) {
                    $vicepresidentName = $vicepresident->person->first_name . ' ' . $vicepresident->person->last_name;
                }
            }

            return [
                'id'                 => $neighborhood->id,
                'name'               => $neighborhood->name,
                'code'               => $neighborhood->code,
                'commune'            => $neighborhood->commune,
                'president_name'     => $presidentName,
                'vicepresident_name' => $vicepresidentName,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Muestra los resultados del escrutinio para un barrio, con el detalle
     * completo de cada cargo de la plancha ganadora (formato acta física).
     */
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

        $electionBlocks = ElectionBlock::with('block')
            ->where('election_id', $election->id)
            ->get();

        $resultadosFormateados = [];

        foreach ($electionBlocks as $eb) {
            // ── 1. Votos por plancha ──────────────────────────────────────
            $resultadosBloque = ScrutinyBlockResult::with(['slateBlock.slate'])
                ->where('election_id', $election->id)
                ->where('election_block_id', $eb->id)
                ->get();

            $votosPlanchas = [];
            $ganadorSlateBlockId = null;
            $maxVotos = -1;

            foreach ($resultadosBloque as $res) {
                if (!in_array($res->status, ['approved', 'reviewed'])) continue;
                if (!$res->slateBlock || !$res->slateBlock->slate) continue;

                $votosPlanchas[] = [
                    'plancha' => $res->slateBlock->slate->name,
                    'votos'   => $res->votes,
                ];

                if ($res->votes > $maxVotos) {
                    $maxVotos            = $res->votes;
                    $ganadorSlateBlockId = $res->slateBlock->id;
                }
            }

            // Ordenar de mayor a menor
            usort($votosPlanchas, fn($a, $b) => $b['votos'] <=> $a['votos']);

            // ── 2. Cargos de la plancha ganadora (formato acta) ──────────
            // Estructura: [ 'cargo' => 'Presidente (A)', 'persona' => [...] ]
            $cargos = [];

            if ($ganadorSlateBlockId) {
                // Traemos todos los candidatos del slate_block ganador
                // ordenados por ballot_number para respetar el orden del acta
                $candidatos = Candidate::with([
                    'person',
                    'electionBlockPosition.position'
                ])
                ->where('slate_block_id', $ganadorSlateBlockId)
                ->orderBy('ballot_number')
                ->get();

                foreach ($candidatos as $c) {
                    $person   = $c->person;
                    $position = $c->electionBlockPosition->position ?? null;

                    $cargos[] = [
                        'cargo'   => $position->name ?? 'Sin cargo',
                        'persona' => [
                            'nombre'          => trim(
                                $person->first_name  . ' ' .
                                ($person->middle_name ? $person->middle_name . ' ' : '') .
                                $person->last_name   . ' ' .
                                ($person->second_last_name ?? '')
                            ),
                            'identificacion'  => $person->document_number ?? '—',
                            'celular'         => $person->phone           ?? '—',
                            'correo'          => $person->email           ?? '—',
                        ],
                    ];
                }
            }

            // Solo añadimos el bloque si tiene votos o cargos
            if (count($votosPlanchas) > 0 || count($cargos) > 0) {
                $totalValidos = collect($votosPlanchas)->sum('votos');

                $resultadosFormateados[] = [
                    'nombre_bloque' => $eb->block->name ?? 'Bloque',
                    'codigo_bloque' => $eb->block->code ?? null,
                    'votos_planchas' => $votosPlanchas,
                    'cargos'         => $cargos,           // ← nuevo: un item por cargo
                    'estadisticas'   => [
                        'validos' => $totalValidos,
                        'total'   => $totalValidos,
                        'blancos' => 0,
                        'nulos'   => 0,
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $neighborhood->id,
                'name'       => $neighborhood->name,
                'resultados' => $resultadosFormateados,
            ],
        ]);
    }
}
