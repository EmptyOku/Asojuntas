<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    /**
     * Lista los cargos maestros organizados por bloque y orden.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading del bloque para evitar N+1 consultas.
        $query = Position::with('block');

        if ($request->filled('block_id')) {
            $query->where('block_id', $request->block_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        // Ordenamos primero por bloque y luego por el número de orden asignado
        $positions = $query->orderBy('block_id')
                          ->orderBy('order_number')
                          ->paginate(20)
                          ->withQueryString();

        $blocks = Block::active()->get();

        return view('admin.positions.index', compact('positions', 'blocks'));
    }

    /**
     * Formulario de creación de cargo.
     */
    public function create(): View
    {
        $blocks = Block::active()->get();
        return view('admin.positions.create', compact('blocks'));
    }

    /**
     * Almacena el cargo con validación de orden único por bloque.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'block_id'     => 'required|exists:blocks,id',
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:50|unique:positions,code',
            'order_number' => [
                'required', 'integer', 'min:1',
                // Unicidad: No permitimos el mismo orden para dos cargos del mismo bloque
                Rule::unique('positions')->where(function ($query) use ($request) {
                    return $query->where('block_id', $request->block_id);
                }),
            ],
            'description'  => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Position::create($validated);

        return redirect()->route('admin.positions.index')
            ->with('success', 'Cargo maestro creado exitosamente.');
    }

    /**
     * Actualiza el cargo.
     */
    public function update(Request $request, Position $position): RedirectResponse
    {
        $validated = $request->validate([
            'block_id'     => 'required|exists:blocks,id',
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:50|unique:positions,code,' . $position->id,
            'order_number' => [
                'required', 'integer', 'min:1',
                Rule::unique('positions')->where(function ($query) use ($request) {
                    return $query->where('block_id', $request->block_id);
                })->ignore($position->id),
            ],
            'description'  => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $position->update($validated);

        return redirect()->route('admin.positions.index')
            ->with('success', 'Cargo actualizado correctamente.');
    }

    /**
     * Elimina el cargo si no está en uso electoral.
     */
    public function destroy(Position $position): RedirectResponse
    {
        // Auditoría Preventiva: Si el cargo ya fue asignado a una elección, no se borra.
        if ($position->electionBlockPositions()->exists() || $position->candidateDrafts()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo de integridad. Este cargo ya forma parte de una configuración electoral o tiene borradores de candidatos. Desactívelo en su lugar.');
        }

        try {
            $position->delete();
            return redirect()->route('admin.positions.index')
                ->with('success', 'Cargo maestro eliminado.');
        } catch (QueryException $e) {
            return back()->with('error', 'Error técnico: El cargo tiene dependencias en la base de datos.');
        }
    }
}
