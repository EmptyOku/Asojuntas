<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class BlockController extends Controller
{
    /**
     * Lista el catálogo maestro de bloques electorales.
     */
    public function index(Request $request): View
    {
        // Auditoría: Carga de métricas para visualizar el impacto del bloque
        $query = Block::withCount(['positions', 'elections']);

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $blocks = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.blocks.index', compact('blocks'));
    }

    public function create(): View
    {
        return view('admin.blocks.create');
    }

    /**
     * Almacena un nuevo bloque asegurando unicidad absoluta.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:blocks,name',
            'code'        => 'required|string|max:20|unique:blocks,code',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Block::create($validated);

        return redirect()->route('admin.blocks.index')
            ->with('success', 'Bloque electoral registrado correctamente en el catálogo.');
    }

    public function edit(Block $block): View
    {
        return view('admin.blocks.edit', compact('block'));
    }

    /**
     * Actualiza el bloque manteniendo las restricciones de unicidad.
     */
    public function update(Request $request, Block $block): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:blocks,name,' . $block->id,
            'code'        => 'required|string|max:20|unique:blocks,code,' . $block->id,
            'description' => 'nullable|string|max:500',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $block->update($validated);

        return redirect()->route('admin.blocks.index')
            ->with('success', 'Configuración del bloque actualizada.');
    }

    /**
     * Intenta eliminar el bloque bajo validación forense estricta.
     */
    public function destroy(Block $block): RedirectResponse
    {
        // Regla de Integridad Referencial: Un bloque en uso es inmutable ante la eliminación.
        if ($block->positions()->exists() || $block->electionBlocks()->exists() || $block->candidateDrafts()->exists()) {
            return back()->with('error', 'Auditoría: Violación de integridad referencial. Este bloque ya contiene cargos asignados, está vinculado a una elección o tiene lecturas de la IA asociadas. Proceda a desactivarlo (is_active = false) en lugar de eliminarlo.');
        }

        $block->delete();

        return redirect()->route('admin.blocks.index')
            ->with('success', 'Bloque electoral eliminado estructuralmente.');
    }
}
