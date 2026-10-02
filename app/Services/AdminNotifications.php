<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Avisos para el administrador (la campanita del encabezado).
 *
 * Cada aviso es una fila de audit_logs con una acción propia, escrita en el
 * momento en que ocurre y con todo lo que se muestra ya guardado en metadata
 * (barrio, mesa, quién lo hizo). Así leer una página es UNA consulta, sin
 * joins, y contar los no vistos es un COUNT sobre el índice (action, id).
 *
 * "Visto" es un solo número por usuario (users.notifications_seen_id): todo
 * aviso con id mayor está sin ver. Abrir la campana actualiza ese número.
 */
class AdminNotifications
{
    public const ACTA_RECEIVED = 'notify.acta_received';
    public const PLANCHA_CAPTURED = 'notify.plancha_captured';

    /** Acción => permiso necesario para verla. */
    public const EVENTS = [
        self::ACTA_RECEIVED => 'records.review',
        self::PLANCHA_CAPTURED => 'slates.view',
    ];

    public function __construct(private readonly ElectoralAccessGuard $guard) {}

    /** Registra un aviso. Nunca interrumpe la acción del usuario si falla. */
    public function record(string $action, array $metadata, ?string $auditableType = null, int|string|null $auditableId = null): void
    {
        try {
            $user = Auth::user();

            AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'metadata' => array_filter([...$metadata, 'actor' => $user?->username]),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Acciones que este usuario puede ver, según sus permisos. */
    public function visibleActions(User $user): array
    {
        return array_keys(array_filter(
            self::EVENTS,
            fn (string $permission) => $this->guard->hasPermission($user, $permission),
        ));
    }

    /** Avisos de otros usuarios (lo que uno mismo hizo no se notifica). */
    private function query(User $user)
    {
        return AuditLog::query()
            ->whereIn('action', $this->visibleActions($user))
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '<>', $user->id));
    }

    public function unreadCount(User $user): int
    {
        if (! $this->visibleActions($user)) {
            return 0;
        }

        // Tope de 99: la campana muestra "99+" y el conteo nunca recorre toda la bitácora.
        return $this->query($user)
            ->where('id', '>', (int) $user->notifications_seen_id)
            ->limit(100)
            ->pluck('id')
            ->count();
    }

    /**
     * Una página de avisos, del más reciente al más antiguo. simplePaginate:
     * sin COUNT(*) total, solo "hay más / no hay más".
     */
    public function page(User $user, int $perPage)
    {
        if (! $this->visibleActions($user)) {
            return null;
        }

        $seenId = (int) $user->notifications_seen_id;

        return $this->query($user)
            ->orderByDesc('id')
            ->simplePaginate($perPage, ['id', 'action', 'auditable_id', 'metadata', 'created_at'])
            ->through(fn (AuditLog $log) => $this->present($log, $seenId));
    }

    public function markSeen(User $user, int $upToId): void
    {
        $latest = (int) AuditLog::query()->whereIn('action', array_keys(self::EVENTS))->max('id');
        $upToId = min($upToId > 0 ? $upToId : $latest, $latest);

        // Solo avanza: una pestaña vieja no puede "des-ver" avisos.
        if ($upToId > (int) $user->notifications_seen_id) {
            $user->forceFill(['notifications_seen_id' => $upToId])->saveQuietly();
        }
    }

    private function present(AuditLog $log, int $seenId): array
    {
        $meta = is_array($log->metadata) ? $log->metadata : [];
        $actor = $meta['actor'] ?? 'Alguien';
        $neighborhood = $meta['neighborhood'] ?? 'un barrio';

        [$title, $detail] = match ($log->action) {
            self::ACTA_RECEIVED => [
                'Llegó un acta nueva',
                trim("{$neighborhood}".(! empty($meta['polling_table']) ? " · {$meta['polling_table']}" : '')),
            ],
            self::PLANCHA_CAPTURED => [
                'Se registró una plancha',
                $neighborhood.(! empty($meta['candidates']) ? " · {$meta['candidates']} candidatos" : ''),
            ],
            default => ['Aviso', ''],
        };

        return [
            'id' => $log->id,
            'type' => $log->action === self::ACTA_RECEIVED ? 'acta' : 'plancha',
            'title' => $title,
            'detail' => $detail,
            'actor' => $actor,
            'unread' => $log->id > $seenId,
            'created_at' => $log->created_at?->toIso8601String(),
            // A dónde lleva el clic.
            'record_id' => $log->action === self::ACTA_RECEIVED ? $log->auditable_id : null,
            'batch' => $meta['batch'] ?? null,
            'election_id' => $meta['election_id'] ?? null,
            'neighborhood' => $meta['neighborhood'] ?? null,
        ];
    }
}
