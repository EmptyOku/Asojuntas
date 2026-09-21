<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Neighborhood;
use App\Models\Person;
use App\Models\PollingTable;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrailLogger;
use App\Services\LegacyRbacAuditTrail;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Registra en la bitácora el detalle de roles de un usuario, solo cuando de verdad
     * cambió algo (evita filas vacías cada vez que se reenvía el mismo conjunto de roles).
     */
    private function logRoleAssignment(User $user, array $rolesBefore, array $rolesAfter): void
    {
        $before = $rolesBefore;
        $after = $rolesAfter;
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        app(AuditTrailLogger::class)->recordSystemEvent('role_assignment', [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'roles_before' => $rolesBefore,
            'roles_after' => $rolesAfter,
        ], User::class, $user->id);
    }

    /**
     * Tamaños de página permitidos para el listado de usuarios.
     */
    private const USERS_PER_PAGE_OPTIONS = [10, 20, 30, 50];

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['person.neighborhood:id,name,code,commune_id', 'roles:id,name,display_name']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('person', function ($personQuery) use ($search): void {
                        $personQuery->where('document_number', 'ilike', "%{$search}%")
                            ->orWhere('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('person.neighborhood', function ($neighborhoodQuery) use ($search): void {
                        $neighborhoodQuery->where('name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('person.neighborhood.commune', function ($communeQuery) use ($search): void {
                        $communeQuery->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $perPage = $request->integer('per_page', 20);
        $perPage = in_array($perPage, self::USERS_PER_PAGE_OPTIONS, true) ? $perPage : 20;

        $paginator = $query->latest()->paginate($perPage)->withQueryString();

        $paginator->getCollection()->transform(fn ($user) => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'person' => $user->person ? [
                'id' => $user->person->id,
                'document_type_id' => $user->person->document_type_id,
                'document_number' => $user->person->document_number,
                'first_name' => $user->person->first_name,
                'middle_name' => $user->person->middle_name,
                'last_name' => $user->person->last_name,
                'second_last_name' => $user->person->second_last_name,
                // Se listan los campos explícitamente (en vez de pasar el modelo)
                // para no serializar los accessors president_name/vicepresident_name
                // de Neighborhood, que disparan queries de escrutinio no relacionadas
                // con este listado (ver app/Models/Neighborhood.php $appends).
                'neighborhood' => $user->person->neighborhood ? [
                    'id' => $user->person->neighborhood->id,
                    'name' => $user->person->neighborhood->name,
                    'code' => $user->person->neighborhood->code,
                    'commune_id' => $user->person->neighborhood->commune_id,
                ] : null,
            ] : null,
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $paginator,
        ]);
    }

    public function getAvailablePersons(Request $request): JsonResponse
    {
        $persons = Person::whereDoesntHave('user')
            ->where('is_active', true)
            ->select('id', 'first_name', 'last_name', 'document_number')
            ->orderBy('last_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $persons,
        ]);
    }

    public function assignmentContext(Request $request): JsonResponse
    {
        $commune_id = $request->integer('commune_id', null);

        $query = Neighborhood::query()->select(['id', 'name', 'code', 'commune_id']);

        if ($commune_id) {
            $query->where('commune_id', $commune_id);
        } else {
            $query->limit(50);
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

        $assignedNeighborhoodIds = Person::query()
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
                    $person = Person::find($value);
                    if (! $person || ! $person->is_active) {
                        $fail('La persona seleccionada no existe o no está activa.');
                    }
                },
            ],
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
            'neighborhood_id' => ['nullable', 'active_exists:neighborhoods,id'],
            'is_active' => 'sometimes|boolean',
        ]);

        $digitizerRole = Role::where('name', 'digitizer')->first();
        if ($digitizerRole && in_array($digitizerRole->id, $validated['roles'])) {
            $person = Person::find($validated['person_id']);
            $effectiveNeighborhoodId = ($validated['neighborhood_id'] ?? null) ?: $person?->neighborhood_id;

            if (empty($effectiveNeighborhoodId)) {
                $message = 'El barrio es obligatorio para asignar rol de Jurado.';
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['neighborhood_id' => [$message]],
                ], 422);
            }

            $alreadyAssigned = User::whereHas('person', function ($q) use ($effectiveNeighborhoodId) {
                $q->where('neighborhood_id', $effectiveNeighborhoodId);
            })->exists();

            if ($alreadyAssigned) {
                $message = 'Este barrio ya tiene un jurado asignado. Selecciona otro barrio.';
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['neighborhood_id' => [$message]],
                ], 422);
            }
        }

        try {
            $user = DB::transaction(function () use ($validated) {
                if (! empty($validated['neighborhood_id'])) {
                    Person::where('id', $validated['person_id'])
                        ->update(['neighborhood_id' => $validated['neighborhood_id']]);
                }

                $user = User::create([
                    'person_id' => $validated['person_id'],
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'is_active' => $validated['is_active'] ?? true,
                    'email_verified_at' => now(),
                ]);

                $roles = Role::whereIn('id', $validated['roles'])->get();
                $user->syncRoles($roles);
                LegacyRbacAuditTrail::syncUserRoles($user->id, $validated['roles'], Auth::id());

                $this->logRoleAssignment($user, [], $roles->pluck('display_name')->values()->all());

                return $user->load(['person.neighborhood', 'roles:id,name,display_name']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente con roles y barrio asignado.',
                'data' => $user,
            ], 201);

        } catch (QueryException $e) {
            \Log::error('Database error creating user', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos al crear el usuario.',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Error creating user', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $user->loadMissing('person');
        $person = $user->person;

        $validated = $request->validate([
            'document_type_id' => 'sometimes|exists:document_types,id',
            'document_number' => [
                'sometimes', 'string', 'max:30',
                Rule::unique('persons')->where(function ($query) use ($request, $person) {
                    return $query->where('document_type_id', $request->document_type_id ?? $person?->document_type_id);
                })->ignore($person?->id),
            ],
            'first_name' => 'sometimes|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'neighborhood_id' => [
                'nullable',
                'active_exists:neighborhoods,id',
                function ($attribute, $value, $fail) use ($user) {
                    if (empty($value)) return;

                    $isDigitizer = $user->roles()->where('name', 'digitizer')->exists();
                    if (! $isDigitizer) return;

                    $alreadyAssigned = User::where('id', '!=', $user->id)
                        ->whereHas('person', function ($q) use ($value) {
                            $q->where('neighborhood_id', $value);
                        })->exists();

                    if ($alreadyAssigned) {
                        $fail('Este barrio ya tiene un jurado asignado. Selecciona otro barrio.');
                    }
                },
            ],
            'username' => 'required|string|max:50|unique:users,username,'.$user->id,
            'email' => 'required|email|max:150|unique:users,email,'.$user->id,
        ]);

        try {
            DB::transaction(function () use ($validated, $user, $person) {
                $personData = collect($validated)->only([
                    'document_type_id', 'document_number', 'first_name', 'middle_name',
                    'last_name', 'second_last_name', 'neighborhood_id',
                ])->toArray();

                if ($person && ! empty($personData)) {
                    $person->update($personData);
                }

                $user->update(collect($validated)->only(['username', 'email'])->toArray());
            });

            $user->load(['person.neighborhood:id,name,code,commune_id', 'roles:id,name,display_name']);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente.',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al actualizar el usuario.'], 500);
        }
    }

    public function toggleActive(User $user): JsonResponse
    {
        try {
            $user->update(['is_active' => ! $user->is_active]);
            $user->load(['person.neighborhood:id,name,code,commune_id', 'roles:id,name,display_name']);

            return response()->json([
                'success' => true,
                'message' => $user->is_active ? 'Usuario habilitado correctamente.' : 'Usuario deshabilitado correctamente.',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error toggling user status', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al cambiar el estado del usuario.'], 500);
        }
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user->update(['password' => Hash::make($validated['password'])]);
            return response()->json(['success' => true, 'message' => 'Contraseña restablecida correctamente.']);
        } catch (\Exception $e) {
            \Log::error('Error resetting password', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al restablecer la contraseña.'], 500);
        }
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        $digitizerRole = Role::where('name', 'digitizer')->first();
        if ($digitizerRole && in_array($digitizerRole->id, $validated['roles'])) {
            $user->loadMissing('person');
            if (! $user->person || ! $user->person->neighborhood_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede asignar el rol de Jurado sin un barrio asignado a la persona.',
                ], 422);
            }
        }

        $rolesBefore = $user->roles()->pluck('display_name')->values()->all();
        $roles = Role::whereIn('id', $validated['roles'])->get();

        $user->syncRoles($roles);
        LegacyRbacAuditTrail::syncUserRoles($user->id, $validated['roles'], Auth::id());

        $this->logRoleAssignment($user, $rolesBefore, $roles->pluck('display_name')->values()->all());

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
                'active_exists:neighborhoods,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value) {
                        $isTaken = User::where('id', '!=', $user->id)
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
        $user->person->update(['neighborhood_id' => $validated['neighborhood_id'] ?? null]);

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

    public function creationContext(): JsonResponse
    {
        $roles = Role::select('id', 'name', 'display_name')
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $neighborhoods = Neighborhood::select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $personsWithoutUser = Person::whereDoesntHave('user')
            ->where('is_active', true)
            ->select('id', 'document_number', 'first_name', 'middle_name', 'last_name', 'second_last_name', 'neighborhood_id')
            ->with('neighborhood:id,name')
            ->orderBy('first_name')
            ->limit(50)
            ->get()
            ->map(function ($person) {
                $fullName = trim(
                    $person->first_name.' '.
                    ($person->middle_name ? $person->middle_name.' ' : '').
                    $person->last_name.' '.
                    ($person->second_last_name ?? '')
                );

                return [
                    'id' => $person->id,
                    'document_number' => $person->document_number,
                    'full_name' => $fullName,
                    'label' => $person->document_number.' - '.$fullName,
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

    public function searchPersonsForDropdown(Request $request): JsonResponse
    {
        $term = $request->query('q');

        $query = Person::query()
            ->select('id', 'document_number', 'first_name', 'middle_name', 'last_name', 'second_last_name')
            ->where('is_active', true)
            ->whereDoesntHave('user');

        if (! empty($term)) {
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
                $person->first_name.' '.
                ($person->middle_name ? $person->middle_name.' ' : '').
                $person->last_name.' '.
                ($person->second_last_name ?? '')
            );

            return [
                'id' => $person->id,
                'label' => $person->document_number.' - '.$fullName,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}