<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\DocumentType;
use App\Models\Neighborhood;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    /**
     * Lista personas con filtros de búsqueda por identidad.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading de identidad y ubicación para rendimiento.
        $query = Person::with(['documentType', 'neighborhood.commune.city']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('document_number', 'ilike', "%{$search}%")
                  ->orWhere('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $people = $query->latest()->paginate(25)->withQueryString();
        $documentTypes = DocumentType::active()->get();

        return view('admin.people.index', compact('people', 'documentTypes'));
    }

    /**
     * Formulario de creación de persona.
     */
    public function create(): View
    {
        $documentTypes = DocumentType::active()->get();
        $neighborhoods = Neighborhood::orderBy('name')->get();
        return view('admin.people.create', compact('documentTypes', 'neighborhoods'));
    }

    /**
     * Almacena la persona validando la unicidad del documento.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'document_number'  => [
                'required', 'string', 'max:30',
                // Unicidad compuesta: No puede haber dos personas con mismo tipo y nro de doc.
                Rule::unique('persons')->where(function ($query) use ($request) {
                    return $query->where('document_type_id', $request->document_type_id);
                }),
            ],
            'neighborhood_id'  => 'required|exists:neighborhoods,id',
            'first_name'       => 'required|string|max:100',
            'middle_name'      => 'nullable|string|max:100',
            'last_name'        => 'required|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'birth_date'       => 'nullable|date|before:today',
            'phone'            => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:150|unique:persons,email',
            'address'          => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Person::create($validated);

        return redirect()->route('admin.people.index')
            ->with('success', 'Registro de persona creado exitosamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Person $person): View
    {
        $documentTypes = DocumentType::active()->get();
        $neighborhoods = Neighborhood::orderBy('name')->get();
        return view('admin.people.edit', compact('person', 'documentTypes', 'neighborhoods'));
    }

    /**
     * Actualiza los datos de la persona.
     */
    public function update(Request $request, Person $person): RedirectResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'document_number'  => [
                'required', 'string', 'max:30',
                Rule::unique('persons')->where(function ($query) use ($request) {
                    return $query->where('document_type_id', $request->document_type_id);
                })->ignore($person->id),
            ],
            'neighborhood_id'  => 'required|exists:neighborhoods,id',
            'first_name'       => 'required|string|max:100',
            'last_name'        => 'required|string|max:100',
            'email'            => 'nullable|email|max:150|unique:persons,email,' . $person->id,
            // ... otras validaciones similares a store
        ]);

        $validated['is_active'] = $request->has('is_active');
        $person->update($validated);

        return redirect()->route('admin.people.index')
            ->with('success', 'Información de la persona actualizada.');
    }

    /**
     * Elimina el registro si no tiene vínculos electorales.
     */
    public function destroy(Person $person): RedirectResponse
    {
        // Auditoría Preventiva: Si es candidato o usuario, NO se borra.
        if ($person->user()->exists() || $person->candidates()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo de seguridad. Esta persona tiene un perfil de usuario o es candidato oficial. Desactívela en lugar de borrarla.');
        }

        $person->delete();
        return redirect()->route('admin.people.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
