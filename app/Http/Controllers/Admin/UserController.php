<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Person;
use App\Models\Neighborhood;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Lista los usuarios para la tabla de Vue.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['person.neighborhood', 'roles']);

        // Aquí puedes agregar filtros de búsqueda si los necesitas
        $users = $query->latest()->get(); // O paginate() si configuras paginación en Vue

        return response()->json([
            'data' => $users
        ]);
    }

    /**
     * Devuelve los barrios y marca si están ocupados (is_taken).
     */
    public function assignmentContext(): JsonResponse
    {
        $neighborhoods = Neighborhood::withExists('persons as is_taken')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $neighborhoods
        ]);
    }

    /**
     * Devuelve los roles disponibles.
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::with('permissions')->where('is_active', true)->orderBy('display_name')->get();
        return response()->json(['data' => $roles]);
    }

    /**
     * Crea Persona, Usuario y asigna Barrio en una sola transacción.
     * ESTE ES EL NÚCLEO DEL BLOQUEO.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validación Estricta
        $validated = $request->validate([
            // Validaciones de Persona
            'person.document_type_id' => 'required|exists:document_types,id',
            'person.document_number'  => 'required|string|max:30|unique:persons,document_number',
            'person.first_name'       => 'required|string|max:100',
            'person.last_name'        => 'required|string|max:100',
            // BLOQUEO DE BARRIO: Nadie más puede tener este neighborhood_id
            'person.neighborhood_id'  => 'required|exists:neighborhoods,id|unique:persons,neighborhood_id',

            // Validaciones de Usuario
            'user.username'           => 'required|string|max:50|unique:users,username',
            'user.email'              => 'required|email|max:150|unique:users,email',
            'user.password'           => 'required|string|min:8',
            'user.roles'              => 'required|array|min:1',
            'user.roles.*'            => 'exists:roles,id',
        ], [
            'person.neighborhood_id.unique' => 'Este barrio ya está asignado a otro jurado y está bloqueado.',
        ]);

        try {
            DB::beginTransaction();

            // 2. Crear Persona
            $person = Person::create([
                'document_type_id' => $validated['person']['document_type_id'],
                'document_number'  => $validated['person']['document_number'],
                'first_name'       => $validated['person']['first_name'],
                'last_name'        => $validated['person']['last_name'],
                'neighborhood_id'  => $validated['person']['neighborhood_id'],
                'is_active'        => true,
            ]);

            // 3. Crear Usuario asociado
            $user = User::create([
                'person_id' => $person->id,
                'username'  => $validated['user']['username'],
                'email'     => $validated['user']['email'],
                'password'  => Hash::make($validated['user']['password']),
                'is_active' => true,
            ]);

            // 4. Asignar Roles
            $pivotData = [];
            foreach ($validated['user']['roles'] as $roleId) {
                $pivotData[$roleId] = [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id() ?? 1
                ];
            }
            $user->roles()->sync($pivotData);

            DB::commit();

            return response()->json([
                'message' => 'Jurado registrado y barrio asignado exitosamente.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error crítico al procesar la creación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza solo los roles de un usuario existente.
     */
    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles'   => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $pivotData = [];
        foreach ($validated['roles'] as $roleId) {
            $pivotData[$roleId] = [
                'assigned_at' => now(),
                'assigned_by' => Auth::id() ?? 1
            ];
        }
        $user->roles()->sync($pivotData);

        return response()->json(['message' => 'Roles actualizados.']);
    }

    /**
     * Asigna un barrio a un usuario antiguo que no lo tenga.
     */
    public function assignNeighborhood(Request $request, User $user): JsonResponse
    {
        // Bloqueo: Si ya tiene barrio, rechazamos la petición
        if ($user->person && $user->person->neighborhood_id) {
            return response()->json(['message' => 'Este usuario ya tiene un barrio inalterable asignado.'], 403);
        }

        $validated = $request->validate([
            'neighborhood_id' => 'required|exists:neighborhoods,id|unique:persons,neighborhood_id',
        ], [
            'neighborhood_id.unique' => 'Este barrio ya fue tomado por otra persona.',
        ]);

        // Si el usuario no tiene una persona asociada (datos huérfanos), esto fallará. 
        // Se asume que el usuario tiene un person_id válido.
        if ($user->person) {
            $user->person->update(['neighborhood_id' => $validated['neighborhood_id']]);
        }

        return response()->json(['message' => 'Barrio asignado de forma inalterable.']);
    }
}