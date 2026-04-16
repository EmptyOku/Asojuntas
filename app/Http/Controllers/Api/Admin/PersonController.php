<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Models\Neighborhood;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    /**
     * Lista personas sin usuario asignado (para asignación de usuarios).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Person::with(['documentType', 'neighborhood'])
            ->where('is_active', true);

        // Buscar por documento, nombre o correo
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereRaw(
                        "CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name, ' ', COALESCE(second_last_name, '')) ILIKE ?",
                        ["%{$search}%"]
                    );
            });
        }

        // Filtrar por barrio
        if ($request->filled('neighborhood_id')) {
            $query->where('neighborhood_id', $request->integer('neighborhood_id'));
        }

        // Filtrar por tipo de documento
        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->integer('document_type_id'));
        }

        $persons = $query->latest()->paginate((int) $request->integer('per_page', 20))->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $persons,
        ]);
    }

    /**
     * Obtiene el contexto necesario para la creación de personas
     * (tipos de documento, barrios, etc.).
     */
    public function context(): JsonResponse
    {
        $documentTypes = DocumentType::select('id', 'name', 'code')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $neighborhoods = Neighborhood::select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'document_types' => $documentTypes,
                'neighborhoods' => $neighborhoods,
            ],
        ]);
    }

    /**
     * Crea una nueva persona.
     * Valida unicidad de documento según tipo de documento.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'document_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('persons')->where(function ($query) use ($request) {
                    return $query->where('document_type_id', $request->document_type_id);
                }),
            ],
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150|unique:persons,email',
            'address' => 'nullable|string|max:255',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->get('is_active', true);

        try {
            $person = Person::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Persona creada exitosamente.',
                'data' => $person->load(['documentType', 'neighborhood']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear persona: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene los detalles de una persona específica.
     */
    public function show(Person $person): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $person->load(['documentType', 'neighborhood', 'user.roles']),
        ]);
    }

    /**
     * Actualiza los datos de una persona.
     */
    public function update(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'sometimes|exists:document_types,id',
            'document_number' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('persons')->where(function ($query) use ($request) {
                    return $query->where('document_type_id', $request->document_type_id ?? $person->document_type_id);
                })->ignore($person->id),
            ],
            'first_name' => 'sometimes|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150|unique:persons,email,' . $person->id,
            'address' => 'nullable|string|max:255',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $person->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Persona actualizada exitosamente.',
                'data' => $person->load(['documentType', 'neighborhood']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar persona: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista personas que NO tienen usuario asignado
     * (para seleccionar al crear un usuario).
     */
    public function getPersonsWithoutUser(Request $request): JsonResponse
    {
        $search = $request->query('q');

        $query = Person::select('id', 'document_number', 'first_name', 'middle_name', 'last_name', 'second_last_name', 'neighborhood_id')
            ->with('neighborhood:id,name')
            ->where('is_active', true)
            ->whereDoesntHave('user');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('document_number', 'ilike', "%{$search}%");
            });
        }

        $persons = $query->orderBy('first_name')->limit(20)->get();

        $data = $persons->map(function ($person) {
            $fullName = trim(
                $person->first_name . ' ' .
                ($person->middle_name ? $person->middle_name . ' ' : '') .
                $person->last_name . ' ' .
                ($person->second_last_name ?? '')
            );

            return [
                'id' => $person->id,
                'document_number' => $person->document_number,
                'label' => $person->document_number . ' - ' . $fullName,
                'neighborhood' => $person->neighborhood ? [
                    'id' => $person->neighborhood->id,
                    'name' => $person->neighborhood->name,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
