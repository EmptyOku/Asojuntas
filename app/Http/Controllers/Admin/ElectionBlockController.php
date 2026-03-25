<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectionBlock;
use App\Models\Election;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;

class ElectionBlockController extends Controller
{
    /**
     * Lista la configuración de bloques por elección.
     */
    public function index(Request $request): View
    {
        // Auditoría: Carga profunda para ver la elección y el nombre del bloque original.
        $query = ElectionBlock::with(['election', 'block']);

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        $electionBlocks = $query->latest()->paginate(20)->withQueryString();
        $elections = Election::orderBy('name')->get();

        return view('admin.election_blocks.index', compact('electionBlocks', 'elections'));
    }

    /**
     * Muestra el formulario para asignar un bloque a una elección.
     */
    public function create(): View
    {
        $elections = Election::active()->get();
        $blocks = Block::active()->get();
        return view('admin.election_blocks.create', compact('elections', 'blocks'));
    }

    /**
     * Vincula un bloque a una elección (Capa de configuración).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'block_id'    => 'required|exists:blocks,id',
            // Evitamos duplicar el mismo bloque en la misma elección
            'block_id'    => 'unique:election_blocks,block_id,NULL,id,election_id,' . $request->election_id,
        ]);

        $validated['is_active'] = $request->has('is_active');

        ElectionBlock::create($validated);

        return redirect()->route('admin.election-blocks.index')
            ->with('success', 'Bloque vinculado a la elección exitosamente.');
    }

    /**
     * Muestra el detalle y las relaciones (Cargos, resultados, etc.)
     */
    public function show(ElectionBlock $electionBlock): View
    {
        $electionBlock->load(['election', 'block', 'electionBlockPositions.position']);
        return view('admin.election_blocks.show', compact('electionBlock'));
    }

    /**
     * Elimina la vinculación si no hay datos electorales ya registrados.
     */
    public function destroy(ElectionBlock $electionBlock): RedirectResponse
    {
        // Auditoría de Integridad: Si ya hay planchas o resultados, NO se puede borrar.
        if ($electionBlock->slateBlocks()->exists() || $electionBlock->seatAllocations()->exists()) {
            return back()->with('error', 'Auditoría: Operación bloqueada. Este bloque ya tiene planchas inscritas o resultados calculados. Desactívelo en su lugar.');
        }

        try {
            $electionBlock->delete();
            return redirect()->route('admin.election-blocks.index')
                ->with('success', 'Vinculación de bloque eliminada.');
        } catch (QueryException $e) {
            return back()->with('error', 'Error de base de datos: Integridad referencial activa.');
        }
    }
}
