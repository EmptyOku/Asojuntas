<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Neighborhood;
use App\Models\Slate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NeighborhoodController extends Controller
{
    /**
     * Buscador de barrios para el módulo de captura de planchas.
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->query('q');

        if (empty($term)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $neighborhoods = Neighborhood::with(['commune'])
            ->whereLike('name', "%{$term}%")
            ->orWhereLike('code', "%{$term}%")
            ->orderBy('name')
            ->get();

        $neighborhoodIds = $neighborhoods->pluck('id');

        // Una elección activa por barrio (la más reciente), en un solo query
        // en vez de un elections()->first() por cada fila del resultado.
        $activeElectionByNeighborhood = Election::query()
            ->whereIn('neighborhood_id', $neighborhoodIds)
            ->where('is_active', true)
            ->orderByDesc('election_date')
            ->get(['id', 'neighborhood_id', 'name', 'election_date'])
            ->unique('neighborhood_id')
            ->keyBy('neighborhood_id');

        // Conteo de planchas por elección, también en un solo query.
        $slatesCountByElection = Slate::query()
            ->whereIn('election_id', $activeElectionByNeighborhood->pluck('id'))
            ->selectRaw('election_id, count(*) as aggregate')
            ->groupBy('election_id')
            ->pluck('aggregate', 'election_id');

        $data = $neighborhoods->map(function ($neighborhood) use ($activeElectionByNeighborhood, $slatesCountByElection) {
            $activeElection = $activeElectionByNeighborhood->get($neighborhood->id);

            return [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'commune' => [
                    'name' => $neighborhood->commune->name ?? 'S/C',
                ],
                'active_election' => $activeElection ? [
                    'id' => $activeElection->id,
                    'name' => $activeElection->name,
                    'slates_count' => (int) ($slatesCountByElection[$activeElection->id] ?? 0),
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
