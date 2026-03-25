<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use App\Models\Commune;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class NeighborhoodController extends Controller
{
    /**
     * Lista los barrios con su jerarquía (Comuna -> Ciudad).
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading anidado. Cargamos la comuna y de paso la ciudad
        // para que la tabla sea legible sin hacer cientos de consultas SQL.
        $query = Neighborhood::with(['commune.city']);

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }

        $neighborhoods = $query->orderBy('name')->paginate(20)->withQueryString();
        $communes = Commune::orderBy('name')->get();

        return view('admin.neighborhoods.index', compact('neighborhoods', 'communes'));
    }

    /**
     * Formulario de creación de barrio.
     */
    public function create(): View
    {
        $communes = Commune::with('city')->orderBy('name')->get();
        return view('admin.neighborhoods.create', compact('communes'));
    }

    /**
     * Almacena el barrio con validación de contexto local.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'name'       => 'required|string|max:150',
            // El código solo debe ser único DENTRO de la misma comuna.
            'code'       => [
                'required',
                'string',
                'max:50',
                Rule::unique('neighborhoods')->where(function ($query) use ($request) {
                    return $query->where('commune_id', $request->commune_id);
                }),
            ],
        ]);

        Neighborhood::create($validated);

        return redirect()->route('admin.neighborhoods.index')
            ->with('success', 'Barrio registrado exitosamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Neighborhood $neighborhood): View
    {
        $communes = Commune::with('city')->orderBy('name')->get();
        return view('admin.neighborhoods.edit', compact('neighborhood', 'communes'));
    }

    /**
     * Actualiza el barrio validando la unicidad del código.
     */
    public function update(Request $request, Neighborhood $neighborhood): RedirectResponse
    {
        $validated = $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'name'       => 'required|string|max:150',
            'code'       => [
                'required',
                'string',
                'max:50',
                Rule::unique('neighborhoods')->where(function ($query) use ($request) {
                    return $query->where('commune_id', $request->commune_id);
                })->ignore($neighborhood->id),
            ],
        ]);

        $neighborhood->update($validated);

        return redirect()->route('admin.neighborhoods.index')
            ->with('success', 'Datos del barrio actualizados.');
    }

    /**
     * Elimina el barrio solo si no hay personas o elecciones vinculadas.
     */
    public function destroy(Neighborhood $neighborhood): RedirectResponse
    {
        // Capa de Seguridad 1: Personas empadronadas.
        if ($neighborhood->persons()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo de integridad. No se puede eliminar el barrio porque tiene ' . $neighborhood->persons()->count() . ' personas registradas en él.');
        }

        // Capa de Seguridad 2: Elecciones configuradas.
        if ($neighborhood->elections()->exists()) {
            return back()->with('error', 'Auditoría: Riesgo legal. Este barrio tiene procesos electorales históricos o activos vinculados.');
        }

        try {
            $neighborhood->delete();
            return redirect()->route('admin.neighborhoods.index')
                ->with('success', 'Barrio eliminado correctamente.');
        } catch (QueryException $e) {
            return back()->with('error', 'Error técnico de base de datos (Llave foránea).');
        }
    }
}
