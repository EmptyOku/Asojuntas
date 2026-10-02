<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompleteUserRequest;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrailLogger;
use App\Services\ElectoralAccessGuard;
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

        // Los roles se leen una sola vez (antes: una consulta "exists" por rol y otra
        // para asignarlos). Cada viaje a la BD remota cuesta ~350 ms.
        $roles = Role::whereIn('id', $data['roles'])->get();
        if ($roles->count() !== count(array_unique($data['roles']))) {
            throw ValidationException::withMessages(['roles' => 'Alguno de los roles seleccionados no existe.']);
        }

        $guard = app(ElectoralAccessGuard::class);
        $rolePermissions = $guard->permissionsForRoles($data['roles']);
        $guard->assertCanGrant(Auth::user(), $rolePermissions, 'asignar esos roles');

        if ($guard->permissionsRequireNeighborhood($rolePermissions)) {
            if (empty($data['neighborhood_id'])) {
                $message = 'El barrio es obligatorio: el rol elegido solo opera dentro de un barrio.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['neighborhood_id' => [$message]],
                ], 422);
            }

            if ($guard->permissionsAreJury($rolePermissions) && $guard->neighborhoodHasJury((int) $data['neighborhood_id'])) {
                $message = 'Este barrio ya tiene un jurado asignado. Selecciona otro barrio.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['neighborhood_id' => [$message]],
                ], 422);
            }
        }

        try {
            // Persona y cuenta en una sola petición y una sola transacción: si algo
            // falla no queda una persona suelta sin cuenta.
            $user = DB::transaction(function () use ($data, $roles): User {
                $person = Person::create(
                    collect($data)->only([
                        'document_type_id', 'document_number', 'first_name', 'middle_name',
                        'last_name', 'second_last_name', 'neighborhood_id',
                    ])->merge(['is_active' => true])->all(),
                );

                $user = User::create([
                    'person_id' => $person->id,
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => $data['password'], // el cast "hashed" del modelo la cifra
                    'is_active' => $data['is_active'] ?? true,
                    'email_verified_at' => now(),
                ]);
                $user->syncRoles($roles);
                LegacyRbacAuditTrail::syncUserRoles($user->id, $roles->pluck('id')->all(), Auth::id());

                app(AuditTrailLogger::class)->recordSystemEvent('role_assignment', [
                    'target_user_id' => $user->id,
                    'target_username' => $user->username,
                    'roles_before' => [],
                    'roles_after' => $roles->pluck('display_name')->values()->all(),
                ], User::class, $user->id);

                return $user;
            });

            // Respuesta mínima: la pantalla solo necesita saber que se creó
            // (antes se recargaban persona, roles y permisos: 3 consultas más).
            return response()->json([
                'success' => true,
                'message' => 'La persona y su cuenta de usuario se crearon exitosamente.',
                'data' => $user->only(['id', 'person_id', 'username', 'email', 'is_active']),
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'No fue posible crear la cuenta.'], 500);
        }
    }
}
