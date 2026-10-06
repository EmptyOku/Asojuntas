<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resuelve permisos y alcance territorial de un usuario.
 *
 * Todas las comprobaciones fallan en cerrado: si el barrio del usuario no se
 * puede determinar, se niega el acceso en lugar de concederlo.
 */
class ElectoralAccessGuard
{
    /** Permiso que habilita a revisar material de cualquier barrio (ver las actas). */
    public const REVIEW_PERMISSION = 'scrutiny_records.view';

    /** Permiso de captura de actas del jurado (crear actas). */
    public const JURY_PERMISSION = 'scrutiny_records.create';

    /** Permisos cuyo uso queda limitado al barrio del usuario (si no es revisor). */
    public const NEIGHBORHOOD_SCOPED_PERMISSIONS = ['scrutiny_records.create', 'candidate_drafts.create'];

    /** @var array<int, Collection<int, string>> Memo por request, evita reconsultar roles. */
    private array $permissionCache = [];

    /** @return Collection<int, string> */
    public function permissionsFor(User $user): Collection
    {
        if (! isset($this->permissionCache[$user->id])) {
            // getAllPermissions() usa el cache de Spatie (24h, ver config/permission.php)
            // en vez de consultar roles/permisos directo a la BD en cada request.
            $this->permissionCache[$user->id] = $user->getAllPermissions()
                ->pluck('name')
                ->values();
        }

        return $this->permissionCache[$user->id];
    }

    public function hasPermission(User $user, string $permission): bool
    {
        return $this->permissionsFor($user)->contains($permission);
    }

    /** @param  array<int, string>  $permissions */
    public function hasAnyPermission(User $user, array $permissions): bool
    {
        return $this->permissionsFor($user)->intersect($permissions)->isNotEmpty();
    }

    /**
     * Regla anti-escalada: nadie puede otorgar permisos que no tiene.
     *
     * Aplica al crear o editar un rol y al asignar roles: sin esto, quien
     * administra roles podía darse a sí mismo (o a otro) el rol Super Admin.
     *
     * @param  iterable<int, string>  $permissionNames
     *
     * @throws AccessDeniedHttpException
     */
    public function assertCanGrant(User $actor, iterable $permissionNames, string $action): void
    {
        $missing = collect($permissionNames)->unique()->diff($this->permissionsFor($actor))->values();

        if ($missing->isNotEmpty()) {
            throw new AccessDeniedHttpException(
                "No puedes {$action}: incluye permisos que tu cuenta no tiene ({$missing->implode(', ')})."
            );
        }
    }

    /**
     * Solo se puede administrar (editar, restablecer contraseña, desactivar o
     * cambiar roles de) un usuario cuyos permisos estén todos dentro de los
     * propios: un administrador no puede tomar la cuenta de otro con más poder.
     *
     * @throws AccessDeniedHttpException
     */
    public function assertCanManageUser(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            return;
        }

        if ($this->permissionsFor($target)->diff($this->permissionsFor($actor))->isNotEmpty()) {
            throw new AccessDeniedHttpException(
                'No puedes administrar esta cuenta: tiene permisos que tu cuenta no tiene.'
            );
        }
    }

    /** Un revisor o administrador electoral no está limitado a un solo barrio. */
    public function isReviewer(User $user): bool
    {
        return $this->hasPermission($user, self::REVIEW_PERMISSION);
    }

    /**
     * Permisos agregados de un conjunto de roles (p. ej. los elegidos en un
     * formulario antes de asignarlos).
     *
     * @param  iterable<int>  $roleIds
     * @return Collection<int, string>
     */
    public function permissionsForRoles(iterable $roleIds): Collection
    {
        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', collect($roleIds)->all()))
            ->pluck('name')
            ->unique()
            ->values();
    }

    /**
     * Sin "ver actas" (scrutiny_records.view), capturar actas o planchas solo funciona dentro del
     * barrio propio (ver canReachNeighborhood), así que esos permisos exigen barrio.
     *
     * @param  Collection<int, string>  $permissions
     */
    public function permissionsRequireNeighborhood(Collection $permissions): bool
    {
        return ! $permissions->contains(self::REVIEW_PERMISSION)
            && $permissions->intersect(self::NEIGHBORHOOD_SCOPED_PERMISSIONS)->isNotEmpty();
    }

    /**
     * Perfil de jurado: sube actas y está limitado a su barrio.
     *
     * @param  Collection<int, string>  $permissions
     */
    public function permissionsAreJury(Collection $permissions): bool
    {
        return $permissions->contains(self::JURY_PERMISSION)
            && ! $permissions->contains(self::REVIEW_PERMISSION);
    }

    /** ¿Otro usuario con perfil de jurado ya está asignado a este barrio? */
    public function neighborhoodHasJury(int $neighborhoodId, ?int $exceptUserId = null): bool
    {
        return User::query()
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->whereHas('person', fn ($query) => $query->where('neighborhood_id', $neighborhoodId))
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', self::JURY_PERMISSION))
            ->whereDoesntHave('roles.permissions', fn ($query) => $query->where('name', self::REVIEW_PERMISSION))
            ->exists();
    }

    /** Barrio asignado al usuario, o null si no se puede determinar. */
    public function neighborhoodIdFor(User $user): ?int
    {
        $neighborhoodId = $user->relationLoaded('person')
            ? $user->person?->neighborhood_id
            : $user->person()->value('neighborhood_id');

        return $neighborhoodId ? (int) $neighborhoodId : null;
    }

    public function neighborhoodIdForElection(?int $electionId): ?int
    {
        if (! $electionId) {
            return null;
        }

        $neighborhoodId = Election::query()->whereKey($electionId)->value('neighborhood_id');

        return $neighborhoodId ? (int) $neighborhoodId : null;
    }

    /**
     * Comprueba que el usuario puede operar sobre el barrio indicado.
     * Los revisores pasan siempre; el resto necesita un barrio propio que coincida.
     */
    public function canReachNeighborhood(User $user, ?int $neighborhoodId): bool
    {
        if ($this->isReviewer($user)) {
            return true;
        }

        $userNeighborhoodId = $this->neighborhoodIdFor($user);

        if ($userNeighborhoodId === null || $neighborhoodId === null) {
            return false;
        }

        return $userNeighborhoodId === $neighborhoodId;
    }

    public function assertCanReachNeighborhood(User $user, ?int $neighborhoodId, string $message): void
    {
        if (! $this->canReachNeighborhood($user, $neighborhoodId)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    public function assertCanReachElection(User $user, ?int $electionId, string $message): void
    {
        $this->assertCanReachNeighborhood(
            $user,
            $this->neighborhoodIdForElection($electionId),
            $message
        );
    }
}
