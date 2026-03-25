<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slate;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class SlateController extends Controller
{
    /**
     * Lista las planchas inscritas por elección.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading de la elección
        $query = Slate::with('election');

        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', 'ilike', "%{$request->search}%");
            });
        }

        // Ordenamos por código (Ej: Plancha 1, Plancha 2...) para facilitar la lectura
        $slates = $query->orderBy('election_id')->orderBy('code')->paginate(20)->withQueryString();
        $elections = Election::orderBy('name')->get();

        return view('admin.slates.index', compact('slates', 'elections'));
    }

    /**
     * Formulario para inscribir una nueva plancha.
     */
    public function create(): View
    {
        // Filtramos manualmente las elecciones activas (ya que no usamos Scope)
        $elections = Election::where('is_active', true)->orderBy('name')->get();
        return view('admin.slates.create', compact('elections'));
    }

    /**
     * Almacena la plancha asegurando unicidad del código.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'name'        => 'required|string|max:150',
            'code'        => [
                'required', 'string', 'max:50',
                // UNICIDAD COMPUESTA: La Plancha "1" puede existir en 2024 y en 2026,
                // pero NO dos veces en la misma elección de 2026.
                Rule::unique('slates')->where(function ($query) use ($request) {
                    return $query->where('election_id', $request->election_id);
                }),
            ],
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Slate::create($validated);

        return redirect()->route('admin.slates.index')
            ->with('success', 'Plancha electoral registrada exitosamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Slate $slate): View
    {
        $elections = Election::where('is_active', true)->orderBy('name')->get();
        return view('admin.slates.edit', compact('slate', 'elections'));
    }

    /**
     * Actualiza la plancha manteniendo la integridad del código.
     */
    public function update(Request $request, Slate $slate): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'name'        => 'required|string|max:150',
            'code'        => [
                'required', 'string', 'max:50',
                Rule::unique('slates')->where(function ($query) use ($request) {
                    return $query->where('election_id', $request->election_id);
                })->ignore($slate->id),
            ],
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $slate->update($validated);

        return redirect()->route('admin.slates.index')
            ->with('success', 'Datos de la plancha actualizados.');
    }

    /**
     * Elimina la plancha SOLO si está vacía.
     */
    public function destroy(Slate $slate): RedirectResponse
    {
        // Auditoría Preventiva: Si la plancha ya tiene bloques configurados o si la IA
        // ya mandó candidatos borradores a esta plancha, se bloquea la eliminación.
        if ($slate->slateBlocks()->exists() || $slate->candidateDrafts()->exists()) {
            return back()->with('error', 'Auditoría: Imposible eliminar. Esta plancha ya tiene bloques internos configurados o candidatos pre-inscritos por la IA. Debe desactivarla (is_active = false) para no romper la base de datos.');
        }

        $slate->delete();
        return redirect()->route('admin.slates.index')
            ->with('success', 'Plancha electoral eliminada.');
    }
}
