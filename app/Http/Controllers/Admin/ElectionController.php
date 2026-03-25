<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Neighborhood;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;

class ElectionController extends Controller
{
    /**
     * Lista las elecciones con sus barrios asociados.
     */
    public function index(Request $request): View
    {
        // Auditoría: Carga del barrio para evitar N+1
        $query = Election::with('neighborhood');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('period_year')) {
            $query->where('period_year', $request->period_year);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $elections = $query->latest('election_date')->paginate(20)->withQueryString();
        $neighborhoods = Neighborhood::orderBy('name')->get();

        return view('admin.elections.index', compact('elections', 'neighborhoods'));
    }

    /**
     * Muestra el formulario para crear una elección.
     */
    public function create(): View
    {
        // En producción, con miles de barrios, esto debería ser un buscador AJAX.
        // Por ahora, para el alcance actual, cargamos todos.
        $neighborhoods = Neighborhood::orderBy('name')->get();
        return view('admin.elections.create', compact('neighborhoods'));
    }

    /**
     * Almacena una nueva elección bajo reglas estrictas.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'neighborhood_id' => 'required|exists:neighborhoods,id',
            'name'            => 'required|string|max:150',
            'code'            => 'required|string|max:50|unique:elections,code',
            'election_date'   => 'required|date',
            'period_year'     => 'required|integer|min:2020|max:2100',
            'description'     => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Election::create($validated);

        return redirect()->route('admin.elections.index')
            ->with('success', 'Elección creada. Ahora debe configurar los bloques y mesas de votación.');
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(Election $election): View
    {
        $neighborhoods = Neighborhood::orderBy('name')->get();
        return view('admin.elections.edit', compact('election', 'neighborhoods'));
    }

    /**
     * Actualiza los datos de la elección.
     */
    public function update(Request $request, Election $election): RedirectResponse
    {
        $validated = $request->validate([
            'neighborhood_id' => 'required|exists:neighborhoods,id',
            'name'            => 'required|string|max:150',
            'code'            => 'required|string|max:50|unique:elections,code,' . $election->id,
            'election_date'   => 'required|date',
            'period_year'     => 'required|integer|min:2020|max:2100',
            'description'     => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $election->update($validated);

        return redirect()->route('admin.elections.index')
            ->with('success', 'Datos de la elección actualizados.');
    }

    /**
     * Intenta eliminar la elección (Acción de máximo riesgo).
     */
    public function destroy(Election $election): RedirectResponse
    {
        // Capa Preventiva: Una elección con candidatos o mesas JAMÁS debe borrarse.
        if ($election->candidates()->exists() || $election->pollingTables()->exists()) {
            return back()->with('error', 'Auditoría: Destrucción denegada. Esta elección ya tiene candidatos o mesas configuradas. Debe desactivarla (is_active = false) para mantener el rastro histórico.');
        }

        // Capa Reactiva de Base de Datos
        try {
            $election->delete();
            return redirect()->route('admin.elections.index')
                ->with('success', 'Elección eliminada exitosamente (No contenía datos vinculados).');

        } catch (QueryException $e) {
            if ($e->getCode() == "23503") {
                return back()->with('error', 'Auditoría: Violación de llave foránea. Existen actas, planchas o cálculos que dependen de esta elección.');
            }
            throw $e;
        }
    }
}
