<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectionBlockPosition;
use App\Models\ElectionBlock;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class ElectionBlockPositionController extends Controller
{
    /**
     * Lista la distribución de cargos y vacantes.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading profundo para ver la Elección, el Bloque y el Cargo.
        $query = ElectionBlockPosition::with(['electionBlock.election', 'electionBlock.block', 'position']);

        if ($request->filled('election_block_id')) {
            $query->where('election_block_id', $request->election_block_id);
        }

        $positions = $query->latest()->paginate(25)->withQueryString();
        $electionBlocks = ElectionBlock::with(['election', 'block'])->get();

        return view('admin.election_block_positions.index', compact('positions', 'electionBlocks'));
    }

    /**
     * Formulario para asignar vacantes a un cargo en un bloque.
     */
    public function create(): View
    {
        $electionBlocks = ElectionBlock::with(['election', 'block'])->active()->get();
        $availablePositions = Position::active()->get();

        return view('admin.election_block_positions.create', compact('electionBlocks', 'availablePositions'));
    }

    /**
     * Almacena la configuración de vacantes.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_block_id' => 'required|exists:election_blocks,id',
            'position_id'       => [
                'required',
                'exists:positions,id',
                // Unicidad: No puedes asignar el mismo cargo dos veces al mismo bloque electoral
                Rule::unique('election_block_positions')->where(function ($query) use ($request) {
                    return $query->where('election_block_id', $request->election_block_id);
                }),
            ],
            'vacancies'         => 'required|integer|min:1|max:100',
        ]);

        // Extraemos el block_id automáticamente del electionBlock para mantener la integridad
        $electionBlock = ElectionBlock::findOrFail($request->election_block_id);
        $validated['block_id'] = $electionBlock->block_id;
        $validated['is_active'] = $request->has('is_active');

        ElectionBlockPosition::create($validated);

        return redirect()->route('admin.election-block-positions.index')
            ->with('success', 'Distribución de cargos configurada correctamente.');
    }

    /**
     * Actualiza el número de vacantes (Solo si no hay candidatos inscritos).
     */
    public function update(Request $request, ElectionBlockPosition $electionBlockPosition): RedirectResponse
    {
        $validated = $request->validate([
            'vacancies' => 'required|integer|min:1|max:100',
        ]);

        // Auditoría Preventiva: Si ya hay candidatos, cambiar las vacantes es un riesgo legal.
        if ($electionBlockPosition->candidates()->exists() && $request->vacancies < $electionBlockPosition->candidates()->count()) {
            return back()->with('error', 'Auditoría: Error de lógica. No puede reducir las vacantes a un número menor que los candidatos ya inscritos.');
        }

        $validated['is_active'] = $request->has('is_active');
        $electionBlockPosition->update($validated);

        return redirect()->route('admin.election-block-positions.index')
            ->with('success', 'Configuración de vacantes actualizada.');
    }

    /**
     * Elimina la configuración del cargo.
     */
    public function destroy(ElectionBlockPosition $electionBlockPosition): RedirectResponse
    {
        // Bloqueo total si hay registros vinculados
        if ($electionBlockPosition->candidates()->exists() || $electionBlockPosition->seatAllocations()->exists()) {
            return back()->with('error', 'Auditoría: Imposible eliminar. Este cargo ya tiene candidatos o resultados vinculados.');
        }

        $electionBlockPosition->delete();
        return redirect()->route('admin.election-block-positions.index')
            ->with('success', 'Configuración de cargo eliminada.');
    }
}
