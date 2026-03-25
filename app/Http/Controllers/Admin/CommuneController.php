<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class CommuneController extends Controller
{
    /**
     * Lista las comunas optimizando la carga de la ciudad a la que pertenecen.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading de 'city' para evitar N+1
        $query = Commune::with('city');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        $communes = $query->orderBy('name')->paginate(20)->withQueryString();
        $cities = City::orderBy('name')->get();

        return view('admin.communes.index', compact('communes', 'cities'));
    }

    /**
     * Muestra el formulario de creación.
     */
    public function create(): View
    {
        $cities = City::orderBy('name')->get();
        return view('admin.communes.create', compact('cities'));
    }

    /**
     * Almacena la comuna con validación de unicidad compuesta.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,id',
            'name' => 'required|string|max:100',
            // El código solo debe ser único dentro de la misma ciudad
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('communes')->where(function ($query) use ($request) {
                    return $query->where('city_id', $request->city_id);
                })
            ],
        ]);

        Commune::create($validated);

        return redirect()->route('admin.communes.index')
            ->with('success', 'Comuna registrada correctamente.');
    }

    /**
     * Muestra el formulario de edición.
     */
    public function edit(Commune $commune): View
    {
        $cities = City::orderBy('name')->get();
        return view('admin.communes.edit', compact('commune', 'cities'));
    }

    /**
     * Actualiza la comuna protegiendo la integridad de sus datos.
     */
    public function update(Request $request, Commune $commune): RedirectResponse
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,id',
            'name' => 'required|string|max:100',
            // Unicidad compuesta, ignorando el registro actual
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('communes')->where(function ($query) use ($request) {
                    return $query->where('city_id', $request->city_id);
                })->ignore($commune->id)
            ],
        ]);

        $commune->update($validated);

        return redirect()->route('admin.communes.index')
            ->with('success', 'Comuna actualizada correctamente.');
    }

    /**
     * Elimina la comuna con restricciones defensivas.
     */
    public function destroy(Commune $commune): RedirectResponse
    {
        // Capa preventiva: Verificamos dependencias inferiores
        if ($commune->neighborhoods()->exists()) {
            return redirect()->route('admin.communes.index')
                ->with('error', 'Auditoría: No se puede eliminar esta comuna porque tiene barrios (' . $commune->neighborhoods()->count() . ') vinculados a ella.');
        }

        try {
            $commune->delete();
            return redirect()->route('admin.communes.index')
                ->with('success', 'Comuna eliminada del sistema.');

        } catch (QueryException $e) {
            // Capa reactiva para PostgreSQL (Violación de FK)
            if ($e->getCode() == "23503") {
                return redirect()->route('admin.communes.index')
                    ->with('error', 'Auditoría: Violación de integridad referencial. Esta comuna está en uso.');
            }
            throw $e;
        }
    }
}
