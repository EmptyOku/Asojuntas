<?php

namespace App\Support;

/**
 * Catálogo único de permisos del sistema.
 *
 * Los permisos los declara el código (una ruta o un middleware tiene que
 * revisarlos para que signifiquen algo); los roles los arma el usuario desde
 * la UI combinando estos permisos. Por eso la lista vive aquí y no se crea
 * desde la interfaz: PermissionSeeder la sincroniza con la tabla `permissions`.
 *
 * - screen: el permiso habilita una pantalla del menú.
 * - depends_on: permisos que deben acompañarlo para que sea útil.
 * - scope_note: advertencia cuando el permiso cambia qué datos ve el usuario.
 */
final class PermissionCatalog
{
    /** @var array<string, array{label: string, permissions: array<string, array<string, mixed>>}> */
    private const MODULES = [
        'dashboard' => [
            'label' => 'Dashboard',
            'permissions' => [
                'dashboard.view' => ['display_name' => 'View dashboard', 'description' => 'Ver el dashboard', 'screen' => true],
            ],
        ],
        'geography' => [
            'label' => 'Geografía electoral',
            'permissions' => [
                'geography.view' => ['display_name' => 'View geography', 'description' => 'Ver geografía electoral', 'screen' => true],
                'geography.manage' => ['display_name' => 'Manage geography', 'description' => 'Crear, editar y ubicar barrios', 'depends_on' => ['geography.view']],
            ],
        ],
        'map' => [
            'label' => 'Mapa interactivo',
            'permissions' => [
                'map.view' => ['display_name' => 'View map', 'description' => 'Ver el mapa interactivo', 'screen' => true],
            ],
        ],
        'candidates' => [
            'label' => 'Directorio JAC',
            'permissions' => [
                'candidates.view' => ['display_name' => 'View JAC directory', 'description' => 'Ver el directorio JAC y resultados por barrio', 'screen' => true],
            ],
        ],
        'elections' => [
            'label' => 'Elecciones',
            'permissions' => [
                'elections.view' => ['display_name' => 'View elections', 'description' => 'Ver elecciones'],
                'elections.create' => ['display_name' => 'Create elections', 'description' => 'Crear elecciones', 'depends_on' => ['elections.view']],
                'elections.update' => ['display_name' => 'Update elections', 'description' => 'Editar y cerrar elecciones', 'depends_on' => ['elections.view']],
            ],
        ],
        'records' => [
            'label' => 'Actas',
            'permissions' => [
                'records.upload' => ['display_name' => 'Upload records', 'description' => 'Subir actas (captura de jurado)', 'screen' => true],
                'records.review' => [
                    'display_name' => 'Review records',
                    'description' => 'Revisar actas (auditoría de actas)',
                    'screen' => true,
                    'scope_note' => 'Quien lo tiene ve actas y planchas de todos los barrios, no solo del suyo.',
                ],
                'records.approve' => ['display_name' => 'Approve records', 'description' => 'Aprobar actas', 'depends_on' => ['records.review']],
                'ocr.process' => ['display_name' => 'Process OCR', 'description' => 'Reprocesar OCR de candidatos', 'depends_on' => ['records.review']],
            ],
        ],
        'slates' => [
            'label' => 'Planchas',
            'permissions' => [
                'slates.view' => ['display_name' => 'View slates', 'description' => 'Ver planchas por barrio (solo lectura)', 'screen' => true],
                'slates.capture' => ['display_name' => 'Capture slates', 'description' => 'Capturar planchas (secretaría)', 'screen' => true],
                'slates.review' => ['display_name' => 'Review slates', 'description' => 'Revisar, aprobar y rechazar planchas', 'screen' => true],
                'slates.promote' => ['display_name' => 'Promote slates', 'description' => 'Oficializar planchas aprobadas', 'depends_on' => ['slates.review']],
            ],
        ],
        'audit' => [
            'label' => 'Bitácora',
            'permissions' => [
                'audit.view' => ['display_name' => 'View audit log', 'description' => 'Ver la bitácora del sistema', 'screen' => true],
            ],
        ],
        'reports' => [
            'label' => 'Reportes',
            'permissions' => [
                'reports.view' => ['display_name' => 'View reports', 'description' => 'Ver reportes'],
            ],
        ],
        'users' => [
            'label' => 'Usuarios',
            'permissions' => [
                'users.view' => ['display_name' => 'View users', 'description' => 'Ver usuarios y personas', 'screen' => true],
                'users.create' => ['display_name' => 'Create users', 'description' => 'Crear usuarios y personas', 'depends_on' => ['users.view']],
                'users.update' => ['display_name' => 'Update users', 'description' => 'Editar usuarios y personas', 'depends_on' => ['users.view']],
                'users.delete' => ['display_name' => 'Delete users', 'description' => 'Eliminar usuarios', 'depends_on' => ['users.view']],
            ],
        ],
        'roles' => [
            'label' => 'Roles y permisos',
            'permissions' => [
                'roles.view' => ['display_name' => 'View roles', 'description' => 'Ver roles', 'screen' => true],
                'roles.manage' => ['display_name' => 'Manage roles', 'description' => 'Crear y editar roles', 'depends_on' => ['roles.view']],
                'roles.assign' => ['display_name' => 'Assign roles', 'description' => 'Asignar roles a usuarios', 'depends_on' => ['roles.view', 'users.view']],
            ],
        ],
    ];

    /**
     * Lista plana: nombre => metadatos (incluye module y module_label).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $flat = [];

        foreach (self::MODULES as $module => $definition) {
            foreach ($definition['permissions'] as $name => $meta) {
                $flat[$name] = $meta + [
                    'module' => $module,
                    'module_label' => $definition['label'],
                    'screen' => false,
                    'depends_on' => [],
                    'scope_note' => null,
                ];
            }
        }

        return $flat;
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /** @return array<string, mixed>|null */
    public static function find(string $name): ?array
    {
        return self::all()[$name] ?? null;
    }

    /**
     * Agrega (en cascada) los permisos de los que dependen los dados: p. ej.
     * slates.promote trae slates.review. Los nombres fuera del catálogo se
     * conservan tal cual.
     *
     * @param  iterable<string>  $names
     * @return list<string>
     */
    public static function withDependencies(iterable $names): array
    {
        $catalog = self::all();
        $result = [];
        $pending = collect($names)->values()->all();

        while ($pending !== []) {
            $name = array_shift($pending);
            if (isset($result[$name])) {
                continue;
            }

            $result[$name] = true;
            array_push($pending, ...($catalog[$name]['depends_on'] ?? []));
        }

        return array_keys($result);
    }
}
