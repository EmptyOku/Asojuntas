<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;

class StateController extends Controller
{
    /**
     * Lista los departamentos del país.
     */
    public function index(Request $request): View
    {
        // Auditoría: Usamos withCount para métricas inmediatas en el panel
        $query = State::withCount('cities');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        $states = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.states.index', compact('states'));
    }

    /**
     * Formulario de creación.
     */
    public function create(): View
    {
        return view('admin.states.create');
    }

    /**
     * Almacena el departamento validando unicidad nacional.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:states,name',
            // El código (ej. DANE) debe ser único en toda la tabla
            'code' => 'required|string|max:20|unique:states,code',
        ]);

        State::create($validated);

        return redirect()->route('admin.states.index')
            ->with('success', 'Departamento registrado exitosamente en el catálogo maestro.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(State $state): View
    {
        return view('admin.states.edit', compact('state'));
    }

    /**
     * Actualiza el departamento manteniendo la protección de unicidad.
     */
    public function update(Request $request, State $state): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:states,name,' . $state->id,
            'code' => 'required|string|max:20|unique:states,code,' . $state->id,
        ]);

        $state->update($validated);

        return redirect()->route('admin.states.index')
            ->with('success', 'Datos del departamento actualizados.');
    }

    /**
     * Destrucción bloqueada si existen dependencias.
     */
    public function destroy(State $state): RedirectResponse
    {
        // El Escudo Geográfico: No puedes borrar un departamento si tiene ciudades adentro.
        if ($state->cities()->exists()) {
            return back()->with('error', 'Auditoría: Integridad referencial en riesgo. Este departamento contiene ciudades registradas. Debe reasignar o eliminar las ciudades primero.');
        }

        try {
            $state->delete();
            return redirect()->route('admin.states.index')
                ->with('success', 'Departamento eliminado del catálogo.');
        } catch (QueryException $e) {
            return back()->with('error', 'Error técnico: Existen registros ocultos que dependen de este departamento en la base de datos.');
        }
    }
}
