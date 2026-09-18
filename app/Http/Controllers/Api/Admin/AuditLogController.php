<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuditLogController extends Controller
{
    /**
     * Traducción de la acción técnica (columna `action`) a un verbo legible.
     */
    private const ACTION_LABELS = [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'login' => 'Inicio de sesión',
        'logout' => 'Cierre de sesión',
        'review_decision' => 'Decisión de revisión',
        'role_assignment' => 'Asignación de rol',
        'permission_assignment' => 'Asignación de permiso',
    ];

    /**
     * Traducción del nombre de clase (columna `auditable_type`) a un nombre de entidad legible.
     */
    private const ENTITY_LABELS = [
        'App\\Models\\User' => 'Usuario',
        'App\\Models\\Person' => 'Persona',
        'App\\Models\\Role' => 'Rol',
        'App\\Models\\Permission' => 'Permiso',
        'App\\Models\\UserRole' => 'Asignación de rol',
        'App\\Models\\RolePermission' => 'Asignación de permiso',
        'App\\Models\\ScrutinyRecord' => 'Acta de escrutinio',
        'App\\Models\\ScrutinyRecordFile' => 'Archivo de acta',
        'App\\Models\\ScrutinyExtraction' => 'Extracción OCR',
        'App\\Models\\ScrutinyReview' => 'Revisión de acta',
        'App\\Models\\ScrutinyBlockResult' => 'Resultado de bloque',
        'App\\Models\\ScrutinyElectedPerson' => 'Persona electa',
        'App\\Models\\CandidateDraft' => 'Borrador de candidato',
        'App\\Models\\CandidateDraftFile' => 'Evidencia de candidato',
        'App\\Models\\Candidate' => 'Candidato',
        'App\\Models\\Slate' => 'Plancha',
        'App\\Models\\SlateBlock' => 'Bloque de plancha',
        'App\\Models\\ConsolidationRun' => 'Consolidación',
        'App\\Models\\ConsolidatedBlockResult' => 'Resultado consolidado',
        'App\\Models\\SeatAllocation' => 'Asignación de curules',
        'App\\Models\\Election' => 'Elección',
        'App\\Models\\ElectionBlock' => 'Bloque electoral',
        'App\\Models\\ElectionBlockPosition' => 'Cargo del bloque',
        'App\\Models\\Block' => 'Bloque',
        'App\\Models\\Position' => 'Cargo',
        'App\\Models\\PollingTable' => 'Mesa de votación',
        'App\\Models\\Neighborhood' => 'Barrio',
        'App\\Models\\Commune' => 'Comuna',
        'App\\Models\\City' => 'Ciudad',
        'App\\Models\\State' => 'Departamento',
        'App\\Models\\DocumentType' => 'Tipo de documento',
    ];

    /**
     * Nombres de campo comunes, traducidos para el detalle de cambios. Lo que no esté aquí
     * cae a una versión "humanizada" automática del nombre de columna (ver humanizeField()).
     */
    private const FIELD_LABELS = [
        'username' => 'Usuario',
        'email' => 'Correo',
        'is_active' => 'Estado activo',
        'first_name' => 'Primer nombre',
        'middle_name' => 'Segundo nombre',
        'last_name' => 'Primer apellido',
        'second_last_name' => 'Segundo apellido',
        'document_number' => 'Número de documento',
        'document_type_id' => 'Tipo de documento',
        'neighborhood_id' => 'Barrio',
        'person_id' => 'Persona',
        'role_id' => 'Rol',
        'permission_id' => 'Permiso',
        'name' => 'Nombre técnico',
        'display_name' => 'Nombre visible',
        'description' => 'Descripción',
        'guard_name' => 'Guardia de acceso',
    ];

    /**
     * Columnas que nunca aportan valor en el detalle de "qué cambió" (ruido de auditoría interna).
     */
    private const IGNORED_DIFF_KEYS = ['id', 'created_at', 'updated_at', 'email_verified_at'];

    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->with([
                'user.person:id,first_name,last_name',
            ]);

        if ($request->filled('action')) {
            $query->where('action', 'ilike', '%'.$request->string('action')->toString().'%');
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type')->toString());
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', (int) $request->input('auditable_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->date('to_date'));
        }

        $perPage = max(5, min(100, (int) $request->integer('per_page', 25)));

        $logs = $query
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (AuditLog $log): array {
                $userName = trim(($log->user?->person?->first_name ?? '').' '.($log->user?->person?->last_name ?? ''));

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'action_label' => self::ACTION_LABELS[$log->action] ?? ucfirst($log->action),
                    'auditable_type' => $log->auditable_type,
                    'entity_label' => self::describeEntity($log->action, $log->auditable_type),
                    'auditable_id' => $log->auditable_id,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'changes' => self::describeChanges($log->action, $log->auditable_type, $log->old_values, $log->new_values, $log->metadata),
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'created_at_human' => $log->created_at?->diffForHumans(),
                    'user' => [
                        'id' => $log->user?->id,
                        'username' => $log->user?->username,
                        'name' => $userName !== '' ? $userName : ($log->user?->username ?? 'Sin usuario'),
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'records' => $logs,
            ],
        ]);
    }

    /**
     * Describe la entidad afectada en lenguaje natural. Los tokens de acceso de Sanctum no son
     * una "entidad" para un usuario del sistema: son el efecto colateral de iniciar/mantener
     * sesión, así que se describen como tal en vez de mostrar la ruta de la clase PHP.
     */
    private static function describeEntity(string $action, ?string $type): string
    {
        if ($type === PersonalAccessToken::class) {
            return match ($action) {
                'created' => 'Ingreso a la aplicación',
                'deleted' => 'Cierre de sesión (token revocado)',
                default => 'Actividad de sesión',
            };
        }

        if ($type === null) {
            return self::ACTION_LABELS[$action] ?? 'Evento del sistema';
        }

        return self::ENTITY_LABELS[$type] ?? class_basename($type);
    }

    /**
     * Convierte name_de_campo en "Name de campo" para columnas que no están en FIELD_LABELS.
     */
    private static function humanizeField(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Arma la lista de "qué cambió" para el detalle expandible: valores nuevos en una creación,
     * valores previos en una eliminación, o solo los campos que realmente cambiaron en una
     * actualización (columnas de auditoría interna como id/timestamps quedan fuera por ruido).
     *
     * @return array<int, array{field: string, label: string, from?: mixed, to?: mixed}>
     */
    private static function describeChanges(string $action, ?string $type, ?array $oldValues, ?array $newValues, ?array $metadata = null): array
    {
        // El detalle de campo por campo de un token de acceso (abilities, last_used_at...) es
        // ruido interno de Sanctum, no información útil para revisar la actividad de un usuario.
        if ($type === PersonalAccessToken::class) {
            return [];
        }

        if ($action === 'role_assignment') {
            return self::describeListDiff('Roles', $metadata['roles_before'] ?? [], $metadata['roles_after'] ?? []);
        }

        if ($action === 'permission_assignment') {
            return self::describeListDiff('Permisos', $metadata['permissions_before'] ?? [], $metadata['permissions_after'] ?? []);
        }

        $oldValues ??= [];
        $newValues ??= [];

        $changes = [];

        if ($action === 'created') {
            foreach ($newValues as $field => $value) {
                if (in_array($field, self::IGNORED_DIFF_KEYS, true)) {
                    continue;
                }
                $changes[] = ['field' => $field, 'label' => self::humanizeField($field), 'to' => $value];
            }

            return $changes;
        }

        if ($action === 'deleted') {
            foreach ($oldValues as $field => $value) {
                if (in_array($field, self::IGNORED_DIFF_KEYS, true)) {
                    continue;
                }
                $changes[] = ['field' => $field, 'label' => self::humanizeField($field), 'from' => $value];
            }

            return $changes;
        }

        $fields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($fields as $field) {
            if (in_array($field, self::IGNORED_DIFF_KEYS, true)) {
                continue;
            }

            $from = $oldValues[$field] ?? null;
            $to = $newValues[$field] ?? null;

            if ($from === $to) {
                continue;
            }

            $changes[] = ['field' => $field, 'label' => self::humanizeField($field), 'from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * Describe el cambio de una lista de nombres (roles de un usuario, permisos de un rol)
     * como una sola fila "antes → después" en el detalle expandible.
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
