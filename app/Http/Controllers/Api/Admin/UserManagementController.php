<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Neighborhood;
use App\Models\PollingTable;
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

        $users = $query->latest()->paginate((int) $request->integer('per_page', 20))->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function assignmentContext(): JsonResponse
    {
        $neighborhoods = Neighborhood::query()
            ->select(['id', 'name', 'code'])
            ->with([
                'elections' => function ($query): void {
                    $query->select(['id', 'neighborhood_id', 'is_active', 'name', 'election_date'])
                        ->where('is_active', true)
                        ->latest('election_date')
                        ->with([
                            'pollingTables' => function ($tableQuery): void {
                                $tableQuery->select(['id', 'election_id', 'name', 'code', 'is_active'])
                                    ->where('is_active', true)
                                    ->orderBy('id');
                            },
                        ]);
                },
            ])
            ->orderBy('name')
            ->get();

        $payload = $neighborhoods->map(function (Neighborhood $neighborhood): array {
            $activeElection = $neighborhood->elections->first();
            $suggestedTable = $activeElection?->pollingTables?->first();

            return [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'code' => $neighborhood->code,
                'active_election_id' => $activeElection?->id,
                'active_election_name' => $activeElection?->name,
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
            'person_id' => 'nullable|exists:persons,id|unique:users,person_id',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'person_id' => $validated['person_id'] ?? null,
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
                'email_verified_at' => now(),
            ]);

            $pivotData = [];
            foreach ($validated['roles'] as $roleId) {
                $pivotData[$roleId] = [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id(),
                ];
            }

            $user->roles()->sync($pivotData);

            return $user->load(['person', 'roles:id,name,display_name']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente',
            'data' => $user,
        ], 201);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        $pivotData = [];
        foreach ($validated['roles'] as $roleId) {
            $pivotData[$roleId] = [
                'assigned_at' => now(),
                'assigned_by' => Auth::id(),
            ];
        }

        $user->roles()->sync($pivotData);
        $user->load(['person', 'roles:id,name,display_name']);

        return response()->json([
            'success' => true,
            'message' => 'Roles actualizados correctamente',
            'data' => $user,
        ]);
    }

    public function syncNeighborhood(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
        ]);

        if (! $user->person_id) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene persona asociada para asignar barrio.',
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
}