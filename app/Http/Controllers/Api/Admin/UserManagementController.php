<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
        $query = User::with(['person', 'roles:id,name,display_name']);

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
}