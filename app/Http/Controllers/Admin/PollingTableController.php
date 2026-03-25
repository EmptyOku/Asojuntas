<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PollingTable;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class PollingTableController extends Controller
{
    /**
     * Lista las mesas de votación por elección.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading de la elección
        $query = PollingTable::with('election');

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        $tables = $query->orderBy('code')->paginate(30)->withQueryString();
        $elections = Election::active()->get();

        return view('admin.polling_tables.index', compact('tables', 'elections'));
    }

    /**
     * Formulario de creación.
     */
    public function create(): View
    {
        $elections = Election::active()->get();
        return view('admin.polling_tables.create', compact('elections'));
    }

    /**
     * Almacena la mesa con validación de código único por elección.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'name'        => 'required|string|max:100',
            'code'        => [
                'required', 'string', 'max:50',
                // Unicidad: El código de mesa no puede repetirse en la misma elección
                Rule::unique('polling_tables')->where(function ($query) use ($request) {
                    return $query->where('election_id', $request->election_id);
                }),
            ],
            'location'    => 'nullable|string|max:255',
            'capacity'    => 'required|integer|min:1|max:5000',
        ]);

        $validated['is_active'] = $request->has('is_active');

        PollingTable::create($validated);

        return redirect()->route('admin.polling-tables.index')
            ->with('success', 'Mesa de votación configurada correctamente.');
    }

    /**
     * Actualiza la mesa.
     */
    public function update(Request $request, PollingTable $pollingTable): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'code'        => [
                'required', 'string', 'max:50',
                Rule::unique('polling_tables')->where(function ($query) use ($request) {
                    return $query->where('election_id', $request->election_id);
                })->ignore($pollingTable->id),
            ],
            'name'        => 'required|string|max:100',
            'capacity'    => 'required|integer|min:1',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $pollingTable->update($validated);

        return redirect()->route('admin.polling-tables.index')
            ->with('success', 'Configuración de mesa actualizada.');
    }

    /**
     * Elimina la mesa solo si no tiene registros de escrutinio (OCR).
     */
    public function destroy(PollingTable $pollingTable): RedirectResponse
    {
        // Auditoría Forense: Si ya hay actas enviadas por Python, NO se puede borrar.
        if ($pollingTable->scrutinyRecords()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo de integridad. Esta mesa ya tiene actas de escrutinio vinculadas (lecturas de IA o manuales). No puede ser eliminada.');
        }

        $pollingTable->delete();
        return redirect()->route('admin.polling-tables.index')
            ->with('success', 'Mesa de votación eliminada.');
    }
}
