<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlateBlock;
use App\Models\Slate;
use App\Models\ElectionBlock;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class SlateBlockController extends Controller
{
    /**
     * Lista los bloques en los que se divide cada plancha.
     */
    public function index(Request $request): View
    {
        // Auditoría: withCount para saber rápidamente cuántos candidatos tiene cada bloque
        $query = SlateBlock::with(['election', 'slate', 'electionBlock.block'])
                           ->withCount('candidates');

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        if ($request->filled('slate_id')) {
            $query->where('slate_id', $request->slate_id);
        }

        $slateBlocks = $query->latest()->paginate(25)->withQueryString();

        return view('admin.slate_blocks.index', compact('slateBlocks'));
    }

    public function create(): View
    {
        $slates = Slate::where('is_active', true)->orderBy('name')->get();
        $electionBlocks = ElectionBlock::with('block')->where('is_active', true)->get();

        return view('admin.slate_blocks.create', compact('slates', 'electionBlocks'));
    }

    /**
     * Almacena el bloque asegurando que no haya cruce de elecciones diferentes.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slate_id'          => 'required|exists:slates,id',
            'election_block_id' => [
                'required',
                'exists:election_blocks,id',
                // Unicidad: Una plancha no puede tener dos veces el bloque "Directivos"
                Rule::unique('slate_blocks')->where(function ($query) use ($request) {
                    return $query->where('slate_id', $request->slate_id);
                }),
            ],
        ]);

        // REGLA DE NEGOCIO ESTRICTA (Anticorrupción de datos cruzados)
        $slate = Slate::findOrFail($request->slate_id);
        $electionBlock = ElectionBlock::findOrFail($request->election_block_id);

        if ($slate->election_id !== $electionBlock->election_id) {
            return back()->withInput()->with('error', 'Auditoría: Incongruencia de datos detectada. Está intentando mezclar una plancha y un bloque que pertenecen a elecciones diferentes.');
        }

        // Auto-asignamos el election_id desde el modelo padre para garantizar integridad
        $validated['election_id'] = $slate->election_id;
        $validated['is_active']   = $request->has('is_active');

        SlateBlock::create($validated);

        return redirect()->route('admin.slate-blocks.index')
            ->with('success', 'Subdivisión de plancha configurada correctamente.');
    }

    public function edit(SlateBlock $slateBlock): View
    {
        $slates = Slate::where('is_active', true)->orderBy('name')->get();
        $electionBlocks = ElectionBlock::with('block')->where('is_active', true)->get();

        return view('admin.slate_blocks.edit', compact('slateBlock', 'slates', 'electionBlocks'));
    }

    public function update(Request $request, SlateBlock $slateBlock): RedirectResponse
    {
        $validated = $request->validate([
            'slate_id'          => 'required|exists:slates,id',
            'election_block_id' => [
                'required',
                'exists:election_blocks,id',
                Rule::unique('slate_blocks')->where(function ($query) use ($request) {
                    return $query->where('slate_id', $request->slate_id);
                })->ignore($slateBlock->id),
            ],
        ]);

        $slate = Slate::findOrFail($request->slate_id);
        $electionBlock = ElectionBlock::findOrFail($request->election_block_id);

        if ($slate->election_id !== $electionBlock->election_id) {
            return back()->withInput()->with('error', 'Auditoría: Incongruencia de datos detectada en la actualización.');
        }

        $validated['election_id'] = $slate->election_id;
        $validated['is_active']   = $request->has('is_active');

        $slateBlock->update($validated);

        return redirect()->route('admin.slate-blocks.index')
            ->with('success', 'Configuración actualizada.');
    }

    public function destroy(SlateBlock $slateBlock): RedirectResponse
    {
        // El escudo máximo: Si hay candidatos, resultados de actas o curules ganadas, es sagrado.
        if ($slateBlock->candidates()->exists() || $slateBlock->scrutinyBlockResults()->exists() || $slateBlock->seatAllocations()->exists()) {
            return back()->with('error', 'Auditoría: Destrucción denegada. Este bloque de plancha ya es parte integral de la elección (contiene candidatos, actas tabuladas o curules asignadas). Desactívelo en su lugar.');
        }

        $slateBlock->delete();
        return redirect()->route('admin.slate-blocks.index')
            ->with('success', 'Bloque de plancha eliminado.');
    }
}
