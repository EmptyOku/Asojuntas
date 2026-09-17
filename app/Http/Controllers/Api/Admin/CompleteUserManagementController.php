<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompleteUserRequest;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyRbacAuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompleteUserManagementController extends Controller
{
    public function store(StoreCompleteUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $digitizerRole = Role::where('name', 'digitizer')->first();
        if ($digitizerRole && in_array($digitizerRole->id, $data['roles'])) {
            if (empty($data['neighborhood_id'])) {
                $message = 'El barrio es obligatorio para asignar rol de Jurado.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['neighborhood_id' => [$message]],
                ], 422);
            }

            $alreadyAssigned = User::whereHas('person', function ($q) use ($data) {
                $q->where('neighborhood_id', $data['neighborhood_id']);
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
            $user = DB::transaction(function () use ($data): User {
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
                LegacyRbacAuditTrail::syncUserRoles($user->id, $data['roles'], Auth::id());

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
}
