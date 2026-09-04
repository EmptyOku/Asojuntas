<?php

namespace App\Services;

use App\Models\Election;
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
    /** Permiso que habilita a revisar material de cualquier barrio. */
    public const REVIEW_PERMISSION = 'records.review';

    /** @var array<int, Collection<int, string>> Memo por request, evita reconsultar roles. */
    private array $permissionCache = [];

    /** @return Collection<int, string> */
    public function permissionsFor(User $user): Collection
    {
        if (! isset($this->permissionCache[$user->id])) {
            $this->permissionCache[$user->id] = $user->roles()
                ->with('permissions:id,name')
                ->get()
                ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                ->unique()
                ->values();
        }

        return $this->permissionCache[$user->id];
    }

    public function hasPermission(User $user, string $permission): bool
    {
        return $this->permissionsFor($user)->contains($permission);
    }

    /** Un revisor o administrador electoral no está limitado a un solo barrio. */
    public function isReviewer(User $user): bool
    {
        return $this->hasPermission($user, self::REVIEW_PERMISSION);
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
