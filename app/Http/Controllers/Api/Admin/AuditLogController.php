<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Bitácora del sistema, contada para personas.
 *
 * La tabla audit_logs guarda cada cambio como "acción + clase + id". Aquí se
 * convierte en una frase ("Aprobó a Ana Pérez en la revisión de planchas"),
 * se ocultan por defecto los pasos internos (los cargos y bloques que el
 * sistema crea solo al abrir una elección) y en el detalle solo se muestran
 * campos que alguien entiende, con sus valores traducidos.
 */
class AuditLogController extends Controller
{
    /** Etiqueta corta de la acción (para acciones sin frase propia). */
    private const ACTION_LABELS = [
        'created' => 'Registro nuevo',
        'updated' => 'Modificación',
        'deleted' => 'Eliminación',
        'login' => 'Inicio de sesión',
        'logout' => 'Cierre de sesión',
        'review_decision' => 'Decisión sobre un acta',
        'role_assignment' => 'Cambio de rol',
        'password_reset' => 'Cambio de contraseña',
        'permission_assignment' => 'Cambio de permisos',
        'role_status_change' => 'Rol activado o desactivado',
        'database_backup' => 'Copia de seguridad',
        'notify.acta_received' => 'Acta recibida',
        'notify.plancha_captured' => 'Plancha registrada',
    ];

    /** Filtros "Qué pasó": cada opción agrupa una o varias acciones técnicas. */
    private const GROUPS = [
        'created' => ['label' => 'Registros nuevos', 'actions' => ['created']],
        'updated' => ['label' => 'Modificaciones', 'actions' => ['updated']],
        'deleted' => ['label' => 'Eliminaciones', 'actions' => ['deleted']],
        'access' => ['label' => 'Ingresos y salidas', 'actions' => ['login', 'logout']],
        'actas' => ['label' => 'Actas (recibidas y decisiones)', 'actions' => ['notify.acta_received', 'review_decision']],
        'planchas' => ['label' => 'Planchas registradas', 'actions' => ['notify.plancha_captured']],
        'security' => ['label' => 'Roles, contraseñas y copias de seguridad', 'actions' => ['role_assignment', 'permission_assignment', 'role_status_change', 'password_reset', 'database_backup']],
    ];

    /** Nombre de cada tipo de registro, con su artículo, para armar frases. */
    private const ENTITIES = [
        'App\\Models\\User' => ['label' => 'Cuenta de usuario', 'phrase' => 'la cuenta de usuario'],
        'App\\Models\\Person' => ['label' => 'Persona', 'phrase' => 'a la persona'],
        'App\\Models\\Role' => ['label' => 'Rol', 'phrase' => 'el rol'],
        'App\\Models\\Permission' => ['label' => 'Permiso', 'phrase' => 'el permiso'],
        'App\\Models\\ScrutinyRecord' => ['label' => 'Acta de escrutinio', 'phrase' => 'el acta'],
        'App\\Models\\ScrutinyReview' => ['label' => 'Revisión de acta', 'phrase' => 'una revisión de acta'],
        'App\\Models\\CandidateDraft' => ['label' => 'Candidato en revisión', 'phrase' => 'al candidato en revisión'],
        'App\\Models\\Candidate' => ['label' => 'Candidato oficial', 'phrase' => 'al candidato oficial', 'internal' => true],
        'App\\Models\\Election' => ['label' => 'Elección', 'phrase' => 'la elección'],
        'App\\Models\\Neighborhood' => ['label' => 'Barrio', 'phrase' => 'el barrio'],
        'App\\Models\\Commune' => ['label' => 'Comuna', 'phrase' => 'la comuna'],
        'App\\Models\\Block' => ['label' => 'Bloque', 'phrase' => 'el bloque'],
        'App\\Models\\Position' => ['label' => 'Cargo', 'phrase' => 'el cargo'],
        'App\\Models\\DocumentType' => ['label' => 'Tipo de documento', 'phrase' => 'el tipo de documento'],
        // Internos: el sistema los crea o cambia solo, como parte de otra acción.
        'App\\Models\\Slate' => ['label' => 'Plancha', 'phrase' => 'la plancha', 'internal' => true],
        'App\\Models\\SlateBlock' => ['label' => 'Bloque de una plancha', 'phrase' => 'un bloque de plancha', 'internal' => true],
        'App\\Models\\ElectionBlock' => ['label' => 'Bloque de la elección', 'phrase' => 'un bloque de la elección', 'internal' => true],
        'App\\Models\\ElectionBlockPosition' => ['label' => 'Cargo de la elección', 'phrase' => 'un cargo de la elección', 'internal' => true],
        'App\\Models\\PollingTable' => ['label' => 'Mesa de votación', 'phrase' => 'la mesa de votación', 'internal' => true],
        'App\\Models\\CandidateDraftFile' => ['label' => 'Foto de una plancha', 'phrase' => 'una foto de plancha', 'internal' => true],
        'App\\Models\\ScrutinyRecordFile' => ['label' => 'Foto de un acta', 'phrase' => 'una foto de acta', 'internal' => true],
        'App\\Models\\ScrutinyExtraction' => ['label' => 'Extracción de datos', 'phrase' => 'una extracción de datos', 'internal' => true],
        'App\\Models\\ScrutinyBlockResult' => ['label' => 'Votos de un bloque', 'phrase' => 'los votos de un bloque', 'internal' => true],
        'App\\Models\\ScrutinyElectedPerson' => ['label' => 'Persona electa', 'phrase' => 'una persona electa', 'internal' => true],
        'App\\Models\\UserRole' => ['label' => 'Asignación de rol', 'phrase' => 'una asignación de rol', 'internal' => true],
        'App\\Models\\RolePermission' => ['label' => 'Asignación de permiso', 'phrase' => 'una asignación de permiso', 'internal' => true],
        PersonalAccessToken::class => ['label' => 'Sesión', 'phrase' => 'una sesión', 'internal' => true],
    ];

    /** Campos que una persona entiende, con su nombre. Lo que no esté aquí no se muestra. */
    private const FIELD_LABELS = [
        'username' => 'Usuario',
        'email' => 'Correo',
        'phone' => 'Celular',
        'address' => 'Dirección',
        'birth_date' => 'Fecha de nacimiento',
        'is_active' => 'Estado',
        'first_name' => 'Primer nombre',
        'middle_name' => 'Segundo nombre',
        'last_name' => 'Primer apellido',
        'second_last_name' => 'Segundo apellido',
        'document_number' => 'Número de documento',
        'name' => 'Nombre',
        'display_name' => 'Nombre',
        'code' => 'Código',
        'description' => 'Descripción',
        'review_status' => 'Revisión',
        'is_processed' => 'Oficializado',
        'is_substitute' => 'Es suplente',
        'status' => 'Estado',
        'record_number' => 'Número del acta',
        'record_date' => 'Fecha del acta',
        'election_date' => 'Fecha de la elección',
        'period_year' => 'Año del periodo',
        'votes' => 'Votos',
        'decision' => 'Decisión',
        'comments' => 'Motivo u observación',
        'latitude' => 'Ubicación (latitud)',
        'longitude' => 'Ubicación (longitud)',
        'capacity' => 'Capacidad',
        'location' => 'Lugar',
    ];

    /** Valores guardados en inglés o en código, dichos en español. */
    private const VALUE_LABELS = [
        'review_status' => ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'],
        'decision' => ['approved' => 'Aprobada', 'rejected' => 'Rechazada', 'reviewed' => 'Revisada'],
        'status' => [
            'draft' => 'Borrador', 'pending' => 'Pendiente', 'pending_review' => 'En revisión', 'reviewed' => 'Revisada',
            'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'consolidated' => 'Consolidada',
        ],
        'is_active' => [true => 'Activo', false => 'Inactivo'],
    ];

    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with(['user.person:id,first_name,last_name']);

        // Por defecto se ocultan los pasos internos del sistema.
        if (! $request->boolean('include_internal')) {
            $internal = array_keys(array_filter(self::ENTITIES, fn (array $entity) => $entity['internal'] ?? false));
            $query->where(fn ($q) => $q->whereNull('auditable_type')->orWhereNotIn('auditable_type', $internal));

            // Filas antiguas "usuario actualizado" que solo anotaban la hora del ingreso
            // (el inicio de sesión ya tiene su propia fila).
            $query->whereNot(fn ($q) => $q
                ->where('action', 'updated')
                ->where('auditable_type', 'App\\Models\\User')
                ->whereColumn('user_id', 'auditable_id')
                ->whereExists(fn ($login) => $login->selectRaw('1')
                    ->from('audit_logs as login')
                    ->where('login.action', 'login')
                    ->whereColumn('login.user_id', 'audit_logs.user_id')
                    ->whereColumn('login.created_at', 'audit_logs.created_at')));
        }

        if ($request->filled('group') && isset(self::GROUPS[$request->string('group')->toString()])) {
            $query->whereIn('action', self::GROUPS[$request->string('group')->toString()]['actions']);
        }

        if ($request->filled('action')) {
            $query->whereLike('action', '%'.$request->string('action')->toString().'%');
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type')->toString());
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', (int) $request->input('auditable_id'));
        }

        if ($request->filled('user')) {
            $term = '%'.$request->string('user')->toString().'%';
            $query->whereHas('user', fn ($q) => $q->whereLike('username', $term));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->date('to_date'));
        }

        $perPage = max(5, min(100, (int) $request->integer('per_page', 25)));
        $page = $query->latest('id')->paginate($perPage)->withQueryString();

        // Los candidatos oficiales solo guardan el id de la persona: sus nombres se traen de una vez.
        $personIds = $page->getCollection()
            ->where('auditable_type', 'App\\Models\\Candidate')
            ->map(fn (AuditLog $log) => $log->new_values['person_id'] ?? $log->old_values['person_id'] ?? null)
            ->filter()->unique()->values();
        $personNames = $personIds->isEmpty() ? collect() : DB::table('persons')
            ->whereIn('id', $personIds)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'second_last_name'])
            ->mapWithKeys(fn ($person) => [$person->id => self::fullName((array) $person)]);

        $logs = $page->through(function (AuditLog $log) use ($personNames): array {
            $userName = trim(($log->user?->person?->first_name ?? '').' '.($log->user?->person?->last_name ?? ''));
            $changes = self::describeChanges($log);
            [$summary, $category] = self::describe($log, $changes, $personNames);
            $createdAt = $log->created_at ? Carbon::parse($log->created_at)->locale('es') : null;

            return [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => self::ACTION_LABELS[$log->action] ?? ucfirst($log->action),
                'summary' => $summary,
                // create | update | delete | access | decision | security | notice
                'category' => $category,
                'entity_label' => self::entityLabel($log),
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'ip_address' => $log->ip_address,
                'changes' => $changes,
                'created_at' => $log->created_at?->toDateTimeString(),
                'created_at_human' => $createdAt?->diffForHumans(),
                'created_at_exact' => $createdAt?->isoFormat('D [de] MMMM [de] YYYY, h:mm a'),
                'user' => [
                    'id' => $log->user?->id,
                    'username' => $log->user?->username,
                    'name' => $userName !== '' ? $userName : ($log->user?->username ?? 'El sistema'),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'records' => $logs,
                // Opciones de los filtros, ya en lenguaje de usuario.
                'filters' => [
                    'groups' => collect(self::GROUPS)->map(fn (array $group, string $key) => ['value' => $key, 'label' => $group['label']])->values(),
                    'entities' => collect(self::ENTITIES)
                        ->reject(fn (array $entity) => $entity['internal'] ?? false)
                        ->map(fn (array $entity, string $type) => ['value' => $type, 'label' => $entity['label']])
                        ->sortBy('label')->values(),
                ],
            ],
        ]);
    }

    private static function entityLabel(AuditLog $log): string
    {
        if ($log->auditable_type === null) {
            return self::ACTION_LABELS[$log->action] ?? 'Evento del sistema';
        }

        return self::ENTITIES[$log->auditable_type]['label'] ?? class_basename($log->auditable_type);
    }

    /**
     * La frase que cuenta qué pasó, y su categoría (para el color y el ícono).
     *
     * @return array{0: string, 1: string}
     */
    private static function describe(AuditLog $log, array $changes, $personNames): array
    {
        $meta = is_array($log->metadata) ? $log->metadata : [];
        $new = is_array($log->new_values) ? $log->new_values : [];
        $old = is_array($log->old_values) ? $log->old_values : [];
        $type = $log->auditable_type;

        // --- Eventos con nombre propio ---
        switch ($log->action) {
            case 'login':
                return ['Inició sesión', 'access'];
            case 'logout':
                return ['Cerró sesión', 'access'];
            case 'notify.acta_received':
                return ['Subió un acta de '.($meta['neighborhood'] ?? 'un barrio').(! empty($meta['polling_table']) ? " ({$meta['polling_table']})" : ''), 'notice'];
            case 'notify.plancha_captured':
                return ['Registró una plancha en '.($meta['neighborhood'] ?? 'un barrio').(! empty($meta['candidates']) ? " con {$meta['candidates']} candidatos" : ''), 'notice'];
            case 'review_decision':
                $verb = ['approved' => 'Aprobó', 'rejected' => 'Rechazó', 'reviewed' => 'Revisó'][$meta['decision'] ?? ''] ?? 'Decidió sobre';

                return [$verb.' el acta n.º '.($meta['scrutiny_record_id'] ?? '?'), 'decision'];
            case 'database_backup':
                return ['Descargó una copia de seguridad de la base de datos', 'security'];
            case 'role_assignment':
                return ['Cambió el rol de '.($meta['target_username'] ?? 'un usuario'), 'security'];
            case 'password_reset':
                return ['Restableció la contraseña de '.($meta['target_username'] ?? 'un usuario'), 'security'];
            case 'permission_assignment':
                return ['Cambió los permisos del rol '.($meta['role_display_name'] ?? $meta['role_name'] ?? ''), 'security'];
            case 'role_status_change':
                $state = array_key_exists('is_active_after', $meta) ? ($meta['is_active_after'] ? 'Activó' : 'Desactivó') : 'Cambió el estado de';

                return [$state.' el rol '.($meta['role_display_name'] ?? $meta['role_name'] ?? ''), 'security'];
        }

        if ($type === PersonalAccessToken::class) {
            return [$log->action === 'deleted' ? 'Cerró sesión' : 'Ingresó a la aplicación', 'access'];
        }

        $values = $new ?: $old;
        $name = self::recordName($type, $values, $personNames);
        $phrase = self::ENTITIES[$type]['phrase'] ?? ('un registro de '.mb_strtolower(class_basename((string) $type)));
        $subject = trim($phrase.' '.$name);

        // --- Registros nuevos ---
        if ($log->action === 'created') {
            return [match ($type) {
                'App\\Models\\Candidate' => 'Oficializó como candidato a '.($name ?: 'una persona'),
                'App\\Models\\CandidateDraft' => 'Agregó a '.($name ?: 'un candidato').' a una plancha en revisión',
                'App\\Models\\Election' => 'Abrió la elección '.$name,
                'App\\Models\\User' => 'Creó la cuenta de usuario '.$name,
                'App\\Models\\ScrutinyRecord' => 'Empezó a cargar el acta '.$name,
                'App\\Models\\Person' => 'Registró a la persona '.$name,
                default => 'Creó '.$subject,
            }, 'create'];
        }

        if ($log->action === 'deleted') {
            return ['Eliminó '.$subject, 'delete'];
        }

        // --- Modificaciones: se nombra lo que de verdad pasó ---
        $changed = fn (string $field) => array_key_exists($field, $new) && ($old[$field] ?? null) != $new[$field];

        if ($type === 'App\\Models\\CandidateDraft') {
            if ($changed('is_processed') && $new['is_processed']) {
                return ['Oficializó a '.$name, 'decision'];
            }
            if ($changed('review_status')) {
                return [match ($new['review_status']) {
                    'approved' => 'Aprobó a '.$name.' en la revisión de planchas',
                    'rejected' => 'Rechazó a '.$name.' en la revisión de planchas',
                    default => 'Devolvió a '.$name.' a pendiente de revisión',
                }, 'decision'];
            }

            return [self::withFields('Corrigió los datos de '.$name.' en una plancha', $changes), 'update'];
        }

        if ($changed('is_active')) {
            $on = (bool) $new['is_active'];

            return [match ($type) {
                'App\\Models\\Election' => ($on ? 'Reabrió' : 'Cerró').' la elección '.$name,
                default => ($on ? 'Activó ' : 'Desactivó ').$subject,
            }, 'update'];
        }

        if ($changes === []) {
            // Solo cambiaron datos internos (por ejemplo, la hora del último ingreso).
            return [$type === 'App\\Models\\User' ? 'Ingresó al sistema' : 'Actualización automática de '.$subject, 'access'];
        }

        return [self::withFields('Modificó '.$subject, $changes), 'update'];
    }

    /** "Modificó a la persona Ana Pérez: Celular, Correo". */
    private static function withFields(string $sentence, array $changes): string
    {
        $labels = array_values(array_unique(array_column($changes, 'label')));
        if ($labels === []) {
            return $sentence;
        }

        $shown = implode(', ', array_slice($labels, 0, 3));
        $extra = count($labels) - 3;

        return $sentence.': '.$shown.($extra > 0 ? " y {$extra} más" : '');
    }

    /** Nombre con que una persona reconoce el registro (no su id). */
    private static function recordName(?string $type, array $values, $personNames): string
    {
        return match ($type) {
            'App\\Models\\Person', 'App\\Models\\CandidateDraft' => self::fullName($values),
            'App\\Models\\Candidate' => (string) ($personNames[$values['person_id'] ?? 0] ?? ''),
            'App\\Models\\User' => (string) ($values['username'] ?? ''),
            'App\\Models\\ScrutinyRecord' => (string) ($values['record_number'] ?? ''),
            'App\\Models\\Role', 'App\\Models\\Permission' => (string) ($values['display_name'] ?? $values['name'] ?? ''),
            default => (string) ($values['name'] ?? ''),
        };
    }

    private static function fullName(array $values): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $values['first_name'] ?? null,
            $values['middle_name'] ?? null,
            $values['last_name'] ?? null,
            $values['second_last_name'] ?? null,
        ]))) ?? '');
    }

    /**
     * Detalle "qué cambió": solo campos con nombre humano y valores traducidos.
     * Ids, llaves internas, contraseñas y datos técnicos no se muestran.
     *
     * @return array<int, array{field: string, label: string, from?: string, to?: string}>
     */
    private static function describeChanges(AuditLog $log): array
    {
        $meta = is_array($log->metadata) ? $log->metadata : [];

        if ($log->auditable_type === PersonalAccessToken::class) {
            return [];
        }

        if ($log->action === 'role_assignment') {
            return self::describeListDiff('Rol', $meta['roles_before'] ?? [], $meta['roles_after'] ?? []);
        }

        if ($log->action === 'permission_assignment') {
            return self::describeListDiff('Permisos', $meta['permissions_before'] ?? [], $meta['permissions_after'] ?? []);
        }

        if ($log->action === 'role_status_change' && isset($meta['affected_users_count'])) {
            return [['field' => 'affected_users_count', 'label' => 'Usuarios afectados', 'to' => (string) $meta['affected_users_count']]];
        }

        $old = is_array($log->old_values) ? $log->old_values : [];
        $new = is_array($log->new_values) ? $log->new_values : [];
        $changes = [];

        foreach (self::FIELD_LABELS as $field => $label) {
            $hasOld = array_key_exists($field, $old);
            $hasNew = array_key_exists($field, $new);
            if (! $hasOld && ! $hasNew) {
                continue;
            }

            $from = self::formatValue($field, $old[$field] ?? null);
            $to = self::formatValue($field, $new[$field] ?? null);

            if ($log->action === 'created') {
                if ($to !== null) {
                    $changes[] = ['field' => $field, 'label' => $label, 'to' => $to];
                }
            } elseif ($log->action === 'deleted') {
                if ($from !== null) {
                    $changes[] = ['field' => $field, 'label' => $label, 'from' => $from];
                }
            } elseif ($from !== $to) {
                $changes[] = ['field' => $field, 'label' => $label, 'from' => $from ?? 'vacío', 'to' => $to ?? 'vacío'];
            }
        }

        return $changes;
    }

    /** Valor listo para leer; null si no hay nada que mostrar. */
    private static function formatValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (isset(self::VALUE_LABELS[$field])) {
            $key = $field === 'is_active' ? (bool) $value : $value;
            if (isset(self::VALUE_LABELS[$field][$key])) {
                return self::VALUE_LABELS[$field][$key];
            }
        }

        if (is_bool($value) || in_array($field, ['is_processed', 'is_substitute'], true)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_array($value)) {
            return null;
        }

        // Fechas guardadas en formato técnico (2026-10-02T15:20:00+00:00).
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2})?/', $value) === 1) {
            try {
                $date = Carbon::parse($value)->locale('es');

                return str_contains($value, ':') ? $date->isoFormat('D [de] MMMM [de] YYYY, h:mm a') : $date->isoFormat('D [de] MMMM [de] YYYY');
            } catch (\Throwable) {
                return $value;
            }
        }

        return mb_strimwidth((string) $value, 0, 120, '…');
    }

    /**
     * Cambio de una lista (rol de un usuario, permisos de un rol) como "antes → después".
     *
     * @return array<int, array{field: string, label: string, from: string, to: string}>
     */
    private static function describeListDiff(string $label, array $before, array $after): array
    {
        $sortedBefore = $before;
        $sortedAfter = $after;
        sort($sortedBefore);
        sort($sortedAfter);

        if ($sortedBefore === $sortedAfter) {
            return [];
        }

        return [[
            'field' => strtolower($label),
            'label' => $label,
            'from' => $before !== [] ? implode(', ', $before) : 'Ninguno',
            'to' => $after !== [] ? implode(', ', $after) : 'Ninguno',
        ]];
    }
}
