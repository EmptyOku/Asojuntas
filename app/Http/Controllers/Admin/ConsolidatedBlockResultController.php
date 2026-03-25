<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsolidatedBlockResult;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsolidatedBlockResultController extends Controller
{
    /**
     * Muestra la tabla de posiciones (Resultados Consolidados).
     * Solo lectura. Filtrado estricto.
     */
    public function index(Request $request): View
    {
        // Auditoría de Rendimiento: Carga profunda (Deep Eager Loading).
        // Necesitamos llegar hasta el nombre del bloque y de la plancha para que la tabla tenga sentido.
        $query = ConsolidatedBlockResult::with([
            'consolidationRun',
            'election',
            'electionBlock.block',
            'slateBlock.slate'
        ]);

        // Filtro obligatorio: Un reporte de resultados no sirve si mezcla elecciones.
        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        // Filtro por Estado (Ej: Ver solo resultados oficiales)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Se ordena primero por el Bloque Electoral, y luego por porcentaje de votos de Mayor a Menor.
        // Esto automáticamente renderiza quién va ganando en cada bloque.
        $results = $query->orderBy('election_block_id')
                         ->orderBy('vote_percentage', 'desc')
                         ->paginate(50)
                         ->withQueryString();

        // Para el select de filtros en la vista
        $elections = Election::orderBy('name')->get();

        return view('admin.consolidated_results.index', compact('results', 'elections'));
    }

    /**
     * Muestra el detalle forense de cómo se llegó a este resultado.
     */
    public function show(ConsolidatedBlockResult $result): View
    {
        // Cargamos todas las relaciones necesarias para el reporte de detalle
        $result->load([
            'consolidationRun.creator', // Quién ejecutó el cálculo
            'election',
            'electionBlock.block',
            'slateBlock.slate'
        ]);

        return view('admin.consolidated_results.show', compact('result'));
    }

}
