<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompleteUserRequest;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompleteUserManagementController extends Controller
{
    public function store(StoreCompleteUserRequest $request): JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($request): User {
                $data = $request->validated();
                $person = Person::firstOrCreate(
                    ['document_type_id' => $data['document_type_id'], 'document_number' => $data['document_number']],
                    collect($data)->only([
                        'first_name', 'middle_name', 'last_name', 'second_last_name', 'neighborhood_id',
                    ])->merge(['is_active' => true])->all(),
                );

                if ($person->user()->exists()) {
                    throw ValidationException::withMessages([
                        'document_number' => 'La persona ya tiene una cuenta de usuario.',
                    ]);
                }

                $user = User::create([
                    'person_id' => $person->id,
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'is_active' => $data['is_active'] ?? true,
                    'email_verified_at' => now(),
                ]);
                $user->syncRoles(Role::whereIn('id', $data['roles'])->get());

                return $user->load(['person', 'roles.permissions']);
            });

            return response()->json(['success' => true, 'data' => $user], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => 'No fue posible crear la cuenta.'], 500);
        }
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['roles' => ['required', 'array', 'min:1'], 'roles.*' => ['exists:roles,id']]);
        $user->syncRoles(Role::whereIn('id', $data['roles'])->get());

        return response()->json(['success' => true, 'data' => $user->load('roles.permissions')]);
    }

    public function toggleStatus(User $user): JsonResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }
}