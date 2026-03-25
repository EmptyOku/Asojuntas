<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;

class CityController extends Controller
{
    /**
     * Lista las ciudades con carga eficiente de su departamento (State).
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading obligatorio del 'state'
        $query = City::with('state');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('state_id')) {
            $query->where('state_id', $request->state_id);
        }

        // Paginación obligatoria
        $cities = $query->orderBy('name')->paginate(20)->withQueryString();

        // Cargamos los departamentos para el filtro select de la vista
        $states = State::orderBy('name')->get();

        return view('admin.cities.index', compact('cities', 'states'));
    }

    /**
     * Muestra el formulario para crear una ciudad.
     */
    public function create(): View
    {
        $states = State::orderBy('name')->get();
        return view('admin.cities.create', compact('states'));
    }

    /**
     * Almacena la ciudad validando el código DANE.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:100',
            // El código debe ser único en toda Colombia (Ej: 11001 para Bogotá)
            'code' => 'required|string|max:30|unique:cities,code',
        ]);

        City::create($validated);

        return redirect()->route('admin.cities.index')
            ->with('success', 'Ciudad registrada correctamente.');
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(City $city): View
    {
        $states = State::orderBy('name')->get();
        return view('admin.cities.edit', compact('city', 'states'));
    }

    /**
     * Actualiza la ciudad protegiendo la unicidad del código.
     */
    public function update(Request $request, City $city): RedirectResponse
    {
        $validated = $request->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:100',
            // Ignoramos el ID de esta ciudad para la regla unique
            'code' => 'required|string|max:30|unique:cities,code,' . $city->id,
        ]);

        $city->update($validated);

        return redirect()->route('admin.cities.index')
            ->with('success', 'Ciudad actualizada correctamente.');
    }

    /**
     * Intenta eliminar la ciudad con una doble capa de seguridad.
     */
    public function destroy(City $city): RedirectResponse
    {
        // 1. Capa Preventiva (Código PHP puro):
        // Es más eficiente y limpio comprobar si tiene comunas antes de intentar el borrado.
        if ($city->communes()->exists()) {
            return redirect()->route('admin.cities.index')
                ->with('error', 'Auditoría: Bloqueo de seguridad. No puedes eliminar esta ciudad porque tiene comunas (' . $city->communes()->count() . ') vinculadas a ella.');
        }

        // 2. Capa Reactiva (Base de datos PostgreSQL):
        try {
            $city->delete();
            return redirect()->route('admin.cities.index')
                ->with('success', 'Ciudad eliminada del sistema.');

        } catch (QueryException $e) {
            if ($e->getCode() == "23503") {
                return redirect()->route('admin.cities.index')
                    ->with('error', 'Auditoría: Violación de integridad referencial. Esta ciudad está en uso en otra parte del sistema.');
            }
            throw $e;
        }
    }
}
