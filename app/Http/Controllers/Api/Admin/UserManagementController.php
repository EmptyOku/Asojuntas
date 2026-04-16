<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Neighborhood;
use App\Models\Person;
use App\Models\PollingTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['person.neighborhood:id,name,code', 'roles:id,name,display_name']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('person', function ($personQuery) use ($search): void {
                        $personQuery->where('document_number', 'ilike', "%{$search}%")
                            ->orWhere('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        // Limitar a 50 registros máximo para rapidez
        $users = $query->latest()
            ->limit(50)
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'person' => $user->person ? [
                    'id' => $user->person->id,
                    'first_name' => $user->person->first_name,
                    'last_name' => $user->person->last_name,
                    'document_number' => $user->person->document_number,
                    'neighborhood' => $user->person->neighborhood,
                ] : null,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                ])->toArray(),
            ])
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Retorna las personas físicas activas que aún no tienen una cuenta de usuario.
     */
    public function getAvailablePersons(Request $request): JsonResponse
    {
        $persons = \App\Models\Person::whereDoesntHave('user')
            ->where('is_active', true)
            ->select('id', 'first_name', 'last_name', 'document_number')
            ->orderBy('last_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $persons
        ]);
    }

    /**
     * Retorna el contexto de asignación para Vue (Barrios, mesas y si están ocupados).
     */
    public function assignmentContext(Request $request): JsonResponse
    {
        $commune_id = $request->integer('commune_id', null);

        // 1. Obtener barrios con sus elecciones activas
        $query = Neighborhood::query()
            ->select(['id', 'name', 'code', 'commune_id']);

        if ($commune_id) {
            $query->where('commune_id', $commune_id);
        } else {
            $query->limit(50); // Sin filtro de comuna, limita a 50
        }

        $neighborhoods = $query
            ->with([
                'elections' => function ($query): void {
                    $query->select(['id', 'neighborhood_id', 'is_active', 'name', 'election_date'])
                        ->where('is_active', true)
                        ->latest('election_date')
                        ->limit(1)
                        ->with([
                            'pollingTables' => function ($tableQuery): void {
                                $tableQuery->select(['id', 'election_id', 'name', 'code', 'is_active'])
                                    ->where('is_active', true)
                                    ->orderBy('id')
                                    ->limit(1);
                            },
                        ]);
                },
            ])
            ->orderBy('name')
            ->get();

        // 2. Obtener todos los barrios que ya tienen usuario asignado
        $assignedNeighborhoodIds = \App\Models\Person::query()
            ->whereNotNull('neighborhood_id')
            ->whereHas('user')
            ->pluck('neighborhood_id')
            ->unique()
            ->toArray();

        $payload = $neighborhoods->map(function (Neighborhood $neighborhood) use ($assignedNeighborhoodIds): array {
            $activeElection = $neighborhood->elections->first();
            $suggestedTable = $activeElection?->pollingTables?->first();

            return [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'code' => $neighborhood->code,
                'active_election_id' => $activeElection?->id,
                'active_election_name' => $activeElection?->name,
                'is_assigned' => in_array($neighborhood->id, $assignedNeighborhoodIds),
                'suggested_polling_table' => $suggestedTable ? [
                    'id' => $suggestedTable->id,
                    'name' => $suggestedTable->name,
                    'code' => $suggestedTable->code,
                ] : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_id' => [
                'required',
                'exists:persons,id',
                'unique:users,person_id',
                function ($attribute, $value, $fail) {
                    // Validar que la persona exista y esté activa
                    $person = Person::find($value);
                    if (!$person || !$person->is_active) {
                        $fail('La persona seleccionada no existe o no está activa.');
                    }
                },
            ],
            'username'  => 'required|string|max:50|unique:users,username',
            'email'     => 'required|email|max:150|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'roles'     => 'required|array|min:1',
            'roles.*'   => 'exists:roles,id',
            'neighborhood_id' => [
                'nullable',
                'exists:neighborhoods,id',
                function ($attribute, $value, $fail) use ($request) {
                    // Buscar el rol de digitizer (jurado)
                    $digitizerRole = Role::where('name', 'digitizer')->first();
                    
                    // Si se seleccionó el rol de jurado
                    if ($digitizerRole && in_array($digitizerRole->id, $request->roles ?? [])) {
                        
                        // El barrio es obligatorio para jurados
                        if (empty($value)) {
                            $fail('El barrio es obligatorio para asignar rol de Jurado.');
                            return;
                        }
                        
                        // Verificar que el barrio no esté asignado a otro usuario
                        $alreadyAssigned = User::whereHas('person', function ($q) use ($value) {
                            $q->where('neighborhood_id', $value);
                        })->exists();

                        if ($alreadyAssigned) {
                            $fail('Este barrio ya tiene un jurado asignado. Selecciona otro barrio.');
                        }

                        // Verificar que el barrio sea válido
                        $neighborhood = Neighborhood::find($value);
                        if (!$neighborhood) {
                            $fail('El barrio seleccionado no existe.');
                        }
                    }
                }
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $user = DB::transaction(function () use ($validated) {

                // Actualizar la persona con el barrio asignado
                if (!empty($validated['neighborhood_id'])) {
                    Person::where('id', $validated['person_id'])
                        ->update(['neighborhood_id' => $validated['neighborhood_id']]);
                }

                // Crear el usuario
                $user = User::create([
                    'person_id' => $validated['person_id'], 
                    'username'  => $validated['username'],
                    'email'     => $validated['email'],
                    'password'  => Hash::make($validated['password']),
                    'is_active' => $validated['is_active'] ?? true,
                    'email_verified_at' => now(),
                ]);

                // Asignar roles con auditoría
                $pivotData = [];
                foreach ($validated['roles'] as $roleId) {
                    $pivotData[$roleId] = [
                        'assigned_at' => now(),
                        'assigned_by' => Auth::id() ?? 1,
                    ];
                }
                $user->roles()->sync($pivotData);

                return $user->load(['person.neighborhood', 'roles:id,name,display_name']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente con roles y barrio asignado.',
                'data'    => $user,
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database error creating user', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos al crear el usuario.',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Error creating user', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        // Validar que si se asigna el rol de jurado, la persona tenga barrio
        $digitizerRole = Role::where('name', 'digitizer')->first();
        if ($digitizerRole && in_array($digitizerRole->id, $validated['roles'])) {
            $user->loadMissing('person');
            if (!$user->person || !$user->person->neighborhood_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede asignar el rol de Jurado sin un barrio asignado a la persona.',
                ], 422);
            }
        }

        $pivotData = [];
        foreach ($validated['roles'] as $roleId) {
            $pivotData[$roleId] = [
                'assigned_at' => now(),
                'assigned_by' => Auth::id() ?? 1,
            ];
        }

        $user->roles()->sync($pivotData);
        $user->load(['person.neighborhood', 'roles:id,name,display_name']);

        return response()->json([
            'success' => true,
            'message' => 'Roles actualizados correctamente.',
            'data' => $user,
        ]);
    }

    public function syncNeighborhood(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'neighborhood_id' => [
                'nullable',
                'exists:neighborhoods,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value) {
                        // Verifica si otro usuario ya tiene asignada una persona de ese barrio
                        $isTaken = \App\Models\User::where('id', '!=', $user->id)
                            ->whereHas('person', function ($q) use ($value) {
                                $q->where('neighborhood_id', $value);
                            })->exists();
                            
                        if ($isTaken) {
                            $fail('Este barrio ya está asignado a otro usuario. Por favor elige un barrio diferente.');
                        }
                    }
                },
            ],
        ]);

        if (! $user->person_id) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una persona asociada para asignarle un barrio.',
            ], 422);
        }

        $user->loadMissing('person');

        $user->person->update([
            'neighborhood_id' => $validated['neighborhood_id'] ?? null,
        ]);

        $suggestedPollingTable = null;
        if (! empty($validated['neighborhood_id'])) {
            $activeElectionId = Election::query()
                ->where('neighborhood_id', (int) $validated['neighborhood_id'])
                ->where('is_active', true)
                ->latest('election_date')
                ->value('id');

            if ($activeElectionId) {
                $suggestedPollingTable = PollingTable::query()
                    ->where('election_id', (int) $activeElectionId)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first(['id', 'name', 'code']);
            }
        }

        $user->load(['person.neighborhood:id,name,code', 'roles:id,name,display_name']);

        return response()->json([
            'success' => true,
            'message' => 'Barrio del usuario actualizado correctamente.',
            'data' => [
                'user' => $user,
                'suggested_polling_table' => $suggestedPollingTable,
            ],
        ]);
    }

    /**
     * Obtiene el contexto completo para crear un usuario:
     * - Roles disponibles
     * - Personas sin usuario asignado
     * - Barrios disponibles
     * - Información del mandatario (usuario actual)
     */
    public function creationContext(): JsonResponse
    {
        $roles = \App\Models\Role::select('id', 'name', 'display_name')
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $neighborhoods = Neighborhood::select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $personsWithoutUser = \App\Models\Person::whereDoesntHave('user')
            ->where('is_active', true)
            ->select('id', 'document_number', 'first_name', 'middle_name', 'last_name', 'second_last_name', 'neighborhood_id')
            ->with('neighborhood:id,name')
            ->orderBy('first_name')
            ->limit(50)
            ->get()
            ->map(function ($person) {
                $fullName = trim(
                    $person->first_name . ' ' .
                    ($person->middle_name ? $person->middle_name . ' ' : '') .
                    $person->last_name . ' ' .
                    ($person->second_last_name ?? '')
                );

                return [
                    'id' => $person->id,
                    'document_number' => $person->document_number,
                    'full_name' => $fullName,
                    'label' => $person->document_number . ' - ' . $fullName,
                    'neighborhood_id' => $person->neighborhood_id,
                    'neighborhood' => $person->neighborhood ? [
                        'id' => $person->neighborhood->id,
                        'name' => $person->neighborhood->name,
                    ] : null,
                ];
            });

        $admin = Auth::user();
        $adminInfo = $admin ? [
            'id' => $admin->id,
            'username' => $admin->username,
            'email' => $admin->email,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles,
                'neighborhoods' => $neighborhoods,
                'persons' => $personsWithoutUser,
                'admin' => $adminInfo,
            ],
        ]);
    }

    /**
     * =========================================================================
     * NUEVO: Endpoint ligero para buscar personas físicas en el formulario de 
     * creación de usuarios.
     * =========================================================================
     */
    public function searchPersonsForDropdown(Request $request): JsonResponse
    {
        $term = $request->query('q');

        $query = \App\Models\Person::query()
            ->select('id', 'document_number', 'first_name', 'middle_name', 'last_name', 'second_last_name')
            ->where('is_active', true)
            ->whereDoesntHave('user');

        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'ilike', "%{$term}%")
                  ->orWhere('last_name', 'ilike', "%{$term}%")
                  ->orWhere('document_number', 'ilike', "%{$term}%")
                  ->orWhereRaw("first_name || ' ' || last_name ILIKE ?", ["%{$term}%"]);
            });
        }

        $persons = $query->orderBy('first_name')->limit(15)->get();

        $data = $persons->map(function ($person) {
            $fullName = trim(
                $person->first_name . ' ' .
                ($person->middle_name ? $person->middle_name . ' ' : '') .
                $person->last_name . ' ' .
                ($person->second_last_name ?? '')
            );

            return [
                'id' => $person->id,
                'label' => $person->document_number . ' - ' . $fullName
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }
}