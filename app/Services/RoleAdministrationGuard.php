<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Evita que el sistema se quede sin nadie capaz de administrar roles.
 *
 * preserve() aplica el cambio (editar o desactivar un rol, cambiar los roles de
 * un usuario, desactivar un usuario) dentro de una transacción: si antes había
 * al menos un usuario activo con roles.manage y después no queda ninguno, lanza
 * una ValidationException (422) y la transacción se revierte.
 */
class RoleAdministrationGuard
{
    public const MANAGE_PERMISSION = 'roles.manage';

    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     *
     * @throws ValidationException
     */
    public function preserve(Closure $change): mixed
    {
        return DB::transaction(function () use ($change) {
            $hadManagers = $this->managersRemain();

            $result = $change();

            if ($hadManagers && ! $this->managersRemain()) {
                throw ValidationException::withMessages([
                    'roles' => 'El cambio dejaría el sistema sin ningún usuario activo que pueda administrar roles (permiso "roles.manage"). Asigna ese permiso a otro usuario antes de hacerlo.',
                ]);
            }

            return $result;
        });
    }

    public function managersRemain(): bool
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($roles) => $roles
                    ->where('is_active', true)
                    ->whereHas('permissions', fn ($permissions) => $permissions->where('name', self::MANAGE_PERMISSION)))
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->where('name', self::MANAGE_PERMISSION));
            })
            ->exists();
    }
}
