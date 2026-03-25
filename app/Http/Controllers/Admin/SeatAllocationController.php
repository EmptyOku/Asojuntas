<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeatAllocation;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeatAllocationController extends Controller
{
    /**
     * Tablero de Resultados Finales: Muestra cómo quedaron repartidas las curules.
     */
    public function index(Request $request): View
    {
        // Auditoría: Deep Eager Loading para construir la tabla de resultados sin colapsar la base de datos.
        $query = SeatAllocation::with([
            'consolidationRun',
            'electionBlockPosition.position',
            'slateBlock.slate',
            'candidate.person'
        ]);

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        if ($request->filled('consolidation_run_id')) {
            $query->where('consolidation_run_id', $request->consolidation_run_id);
        }

        // El orden es fundamental: Agrupamos por cargo y luego por el orden en que ganaron la curul.
        $allocations = $query->orderBy('election_block_position_id')
                             ->orderBy('allocation_order')
                             ->paginate(50)
                             ->withQueryString();

        $elections = Election::orderBy('name')->get();

        return view('admin.seat_allocations.index', compact('allocations', 'elections'));
    }

    /**
     * Muestra la justificación matemática de una curul específica.
     */
    public function show(SeatAllocation $seatAllocation): View
    {
        $seatAllocation->load([
            'consolidationRun',
            'electionBlockPosition.position',
            'slateBlock.slate',
            'candidate.person'
        ]);

        return view('admin.seat_allocations.show', compact('seatAllocation'));
    }

    // AUDITORÍA ESTRICTA:
    // Los métodos create(), store(), edit(), update() y destroy() están bloqueados intencionalmente.
    // La asignación de curules SÓLO la realiza el servicio de consolidación (ConsolidationService)
    // mediante algoritmos matemáticos, nunca por intervención humana directa.
}
