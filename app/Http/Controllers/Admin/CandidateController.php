<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Person;
use App\Models\SlateBlock;
use App\Models\ElectionBlockPosition;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class CandidateController extends Controller
{
    /**
     * Lista general de candidatos con filtros avanzados.
     */
    public function index(Request $request): View
    {
        // Auditoría de Rendimiento: Eager Loading obligatorio.
        // Si no cargas 'person' y 'election' aquí, tu servidor hará cientos de consultas SQL
        // ocultas al renderizar la tabla HTML.
        $query = Candidate::with(['person', 'election', 'slateBlock.slate', 'electionBlockPosition.position']);

        // Filtro por Elección (Vital para AsoJuntas)
        if ($request->filled('election_id')) {
            $query->where('election_id', $request->election_id);
        }

        // Filtro de búsqueda por nombre de la persona (A través de la relación)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('person', function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('document_number', 'ilike', "%{$search}%");
            });
        }

        $candidates = $query->latest()->paginate(20)->withQueryString();
        $elections = Election::orderBy('name')->get(); // Para el filtro en la vista

        return view('admin.candidates.index', compact('candidates', 'elections'));
    }

    /**
     * Muestra el formulario de inscripción de candidato.
     */
    public function create(): View
    {
        // Nota del Auditor: En producción real con miles de personas, no cargues todos los 'Person::all()'.
        // Usa un buscador AJAX (Select2 o Livewire). Para el alcance actual, esto funcionará.
        $elections = Election::active()->orderBy('name')->get();
        $persons = Person::orderBy('last_name')->limit(500)->get();

        return view('admin.candidates.create', compact('elections', 'persons'));
    }

    /**
     * Valida y almacena un nuevo candidato oficial.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'person_id' => [
                'required',
                'exists:persons,id',
                // Validación de Unicidad Compuesta: Una persona no puede ser candidato dos veces en la misma elección
                Rule::unique('candidates')->where(function ($query) use ($request) {
                    return $query->where('election_id', $request->election_id)
                                 ->where('person_id', $request->person_id);
                })
            ],
            'slate_block_id' => 'required|exists:slate_blocks,id',
            'election_block_position_id' => 'required|exists:election_block_positions,id',
            'ballot_number' => 'nullable|string|max:30',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Aquí, en una app más grande, validaríamos que slate_block_id pertenezca al election_id.
        // Lo dejaremos pasar asumiendo que el frontend filtra los selects correctamente.

        Candidate::create($validated);

        return redirect()->route('admin.candidates.index')
            ->with('success', 'Candidato inscrito exitosamente.');
    }

    /*Muestra el formulario para editar datos del candidato.*/
    public function edit(Candidate $candidate): View
    {
        // Cargamos las relaciones para la vista
        $elections = Election::active()->orderBy('name')->get();
        // Cargamos la persona actual para que el select no se rompa
        $persons = Person::where('id', $candidate->person_id)->get();

        return view('admin.candidates.edit', compact('candidate', 'elections', 'persons'));
    }

    /*Actualiza la información legal del candidato.
     */
    public function update(Request $request, Candidate $candidate): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'person_id' => [
                'required',
                'exists:persons,id',
                // Ignorar la regla de unicidad para el ID actual que estamos editando
                Rule::unique('candidates')->where(function ($query) use ($request) {
                    return $query->where('election_id', $request->election_id)
                                 ->where('person_id', $request->person_id);
                })->ignore($candidate->id)
            ],
            'slate_block_id' => 'required|exists:slate_blocks,id',
            'election_block_position_id' => 'required|exists:election_block_positions,id',
            'ballot_number' => 'nullable|string|max:30',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $candidate->update($validated);

        return redirect()->route('admin.candidates.index')
            ->with('success', 'Información del candidato actualizada.');
    }

    /* Revoca o elimina la candidatura (Si no tiene votos asignados).*/
    public function destroy(Candidate $candidate): RedirectResponse
    {
        try {
            $candidate->delete();
            return redirect()->route('admin.candidates.index')
                ->with('success', 'Candidato eliminado del sistema.');

        } catch (QueryException $e) {
            // Protección contra integridad referencial (PostgreSQL error 23503)
            // Esto salta si el candidato ya tiene curules asignadas (seat_allocations)
            if ($e->getCode() == "23503") {
                return redirect()->route('admin.candidates.index')
                    ->with('error', 'Auditoría: No se puede eliminar a este candidato porque ya tiene resultados electorales o curules vinculadas. Proceda a desactivarlo (' . $candidate->ballot_number . ').');
            }
            throw $e;
        }
    }
}
