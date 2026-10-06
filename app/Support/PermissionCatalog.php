<?php

namespace App\Support;

/**
 * Catálogo único de permisos del sistema.
 *
 * Los permisos se otorgan POR TABLA Y OPERACIÓN: cada tabla que alguien
 * administra tiene "ver", "crear", "editar" y "eliminar" (`personas.crear` se
 * llama `persons.create`). Lo que no es un CRUD —aprobar un acta, oficializar
 * una plancha— son unas pocas ACCIONES ESPECIALES, para poder separar a quien
 * digita de quien aprueba.
 *
 * Los permisos los declara el código (una ruta tiene que revisarlos para que
 * signifiquen algo); los roles los arma el usuario desde la interfaz
 * combinándolos. Por eso la lista vive aquí y no se crea desde la pantalla:
 * PermissionSeeder la sincroniza con la tabla `permissions`.
 *
 * Quedan fuera a propósito:
 *  - tablas pivote (model_has_roles, role_has_permissions, user_roles…): se
 *    manejan con "asignar rol" y "editar rol";
 *  - tablas internas de Laravel (sessions, cache, jobs, migrations, tokens);
 *  - tablas de detalle que el sistema llena solo (bloques de plancha, cargos
 *    de la elección, fotos, extracciones): heredan el permiso de su tabla padre.
 *
 * Metadatos de cada permiso:
 *  - screen: habilita una pantalla del menú.
 *  - depends_on: permisos que deben acompañarlo (crear exige ver).
 *  - scope_note: advertencia cuando cambia qué datos ve el usuario.
 */
final class PermissionCatalog
{
    public const VIEW = 'view';
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    /** Operaciones CRUD, en el orden de las columnas de la matriz de roles. */
    public const CRUD_ACTIONS = [
        self::VIEW => 'Ver',
        self::CREATE => 'Crear',
        self::UPDATE => 'Editar',
        self::DELETE => 'Eliminar',
    ];

    private const GROUPS = [
        'territory' => 'Territorio',
        'catalogs' => 'Catálogos',
        'access' => 'Personas y acceso',
        'election' => 'Elección',
        'scrutiny' => 'Escrutinio',
        'control' => 'Control',
    ];

    /**
     * Tablas con permisos propios. `actions` lista solo las operaciones que
     * tienen sentido: donde falta una, el permiso no existe (nadie puede
     * borrar la bitácora, por ejemplo).
     *
     * @var array<string, array{group: string, label: string, singular: string, actions: list<string>, screen?: list<string>, why?: string, standalone?: list<string>, scope_note?: array<string, string>}>
     */
    private const RESOURCES = [
        // --- Territorio ---
        'states' => ['group' => 'territory', 'label' => 'Departamentos', 'singular' => 'departamento', 'actions' => ['view'],
            'why' => 'Dato fijo: el sistema es solo de Girardot.'],
        'cities' => ['group' => 'territory', 'label' => 'Ciudades', 'singular' => 'ciudad', 'actions' => ['view'],
            'why' => 'Dato fijo: el sistema es solo de Girardot.'],
        'communes' => ['group' => 'territory', 'label' => 'Comunas', 'singular' => 'comuna', 'actions' => ['view', 'create', 'update', 'delete']],
        'neighborhoods' => ['group' => 'territory', 'label' => 'Barrios', 'singular' => 'barrio', 'actions' => ['view', 'create', 'update', 'delete'], 'screen' => ['view']],

        // --- Catálogos ---
        'document_types' => ['group' => 'catalogs', 'label' => 'Tipos de documento', 'singular' => 'tipo de documento', 'actions' => ['view', 'create', 'update', 'delete']],
        'blocks' => ['group' => 'catalogs', 'label' => 'Bloques', 'singular' => 'bloque', 'actions' => ['view', 'create', 'update', 'delete']],
        'positions' => ['group' => 'catalogs', 'label' => 'Cargos', 'singular' => 'cargo', 'actions' => ['view', 'create', 'update', 'delete']],

        // --- Personas y acceso ---
        'persons' => ['group' => 'access', 'label' => 'Personas', 'singular' => 'persona', 'actions' => ['view', 'create', 'update', 'delete'], 'screen' => ['view']],
        'users' => ['group' => 'access', 'label' => 'Usuarios', 'singular' => 'usuario', 'actions' => ['view', 'create', 'update', 'delete'], 'screen' => ['view']],
        'roles' => ['group' => 'access', 'label' => 'Roles', 'singular' => 'rol', 'actions' => ['view', 'create', 'update', 'delete'], 'screen' => ['view']],
        'permissions' => ['group' => 'access', 'label' => 'Permisos', 'singular' => 'permiso', 'actions' => ['view'],
            'why' => 'Los define el código: un permiso creado desde la pantalla no protegería nada.'],

        // --- Elección ---
        'elections' => ['group' => 'election', 'label' => 'Elecciones', 'singular' => 'elección', 'actions' => ['view', 'create', 'update', 'delete']],
        'polling_tables' => ['group' => 'election', 'label' => 'Mesas de votación', 'singular' => 'mesa de votación', 'actions' => ['view', 'create', 'update', 'delete']],
        'slates' => ['group' => 'election', 'label' => 'Planchas', 'singular' => 'plancha', 'actions' => ['view', 'create', 'update', 'delete']],
        'candidate_drafts' => ['group' => 'election', 'label' => 'Candidatos en revisión', 'singular' => 'candidato en revisión', 'actions' => ['view', 'create', 'update', 'delete'], 'screen' => ['view', 'create']],
        'candidates' => ['group' => 'election', 'label' => 'Candidatos oficiales', 'singular' => 'candidato oficial', 'actions' => ['view', 'update', 'delete'], 'screen' => ['view'],
            'why' => 'No se crean a mano: nacen al oficializar una plancha.'],

        // --- Escrutinio ---
        'scrutiny_records' => ['group' => 'scrutiny', 'label' => 'Actas de escrutinio', 'singular' => 'acta', 'actions' => ['view', 'create', 'update'], 'screen' => ['view', 'create'],
            'why' => 'No se eliminan: un acta se rechaza, para que quede el rastro.',
            // El jurado sube su acta ("crear") sin poder revisar las de todos los
            // barrios ("ver"): aquí crear NO arrastra ver.
            'standalone' => ['create'],
            'scope_note' => ['view' => 'Quien lo tiene ve actas y planchas de todos los barrios, no solo del suyo.']],
        'scrutiny_block_results' => ['group' => 'scrutiny', 'label' => 'Votos por bloque', 'singular' => 'resultado', 'actions' => ['view', 'update'],
            'why' => 'Los crea la extracción del acta; se corrigen, no se crean ni se borran.'],
        'scrutiny_reviews' => ['group' => 'scrutiny', 'label' => 'Revisiones de actas', 'singular' => 'revisión', 'actions' => ['view'],
            'why' => 'Es el historial de decisiones: no se edita.'],

        // --- Control ---
        'audit_logs' => ['group' => 'control', 'label' => 'Bitácora', 'singular' => 'registro de bitácora', 'actions' => ['view'], 'screen' => ['view'],
            'why' => 'Es el historial del sistema: si se pudiera editar perdería su valor.'],
    ];

    /**
     * Acciones que no son crear, editar ni borrar una fila.
     *
     * @var array<string, array{label: string, description: string, depends_on?: list<string>, screen?: bool}>
     */
    private const SPECIAL = [
        'dashboard.view' => ['label' => 'Ver el dashboard', 'description' => 'Ver el panel de indicadores', 'screen' => true],
        'map.view' => ['label' => 'Ver el mapa electoral', 'description' => 'Ver el mapa interactivo con el avance de actas', 'screen' => true],
        'results.export' => ['label' => 'Exportar resultados', 'description' => 'Descargar los resultados de un barrio en PDF o Excel', 'depends_on' => ['candidates.view']],
        'scrutiny_records.approve' => ['label' => 'Aprobar o rechazar actas', 'description' => 'Decidir si los votos de un acta cuentan en los resultados', 'depends_on' => ['scrutiny_records.view']],
        'candidate_drafts.approve' => ['label' => 'Aprobar o rechazar planchas', 'description' => 'Aprobar o rechazar los candidatos de una plancha en revisión', 'depends_on' => ['candidate_drafts.view']],
        'candidate_drafts.promote' => ['label' => 'Oficializar planchas', 'description' => 'Publicar como oficiales los candidatos aprobados', 'depends_on' => ['candidate_drafts.view', 'candidate_drafts.approve']],
        'candidate_drafts.extract' => ['label' => 'Reprocesar la extracción', 'description' => 'Volver a extraer los datos de candidatos de un documento', 'depends_on' => ['scrutiny_records.view']],
        'users.assign_role' => ['label' => 'Asignar rol a un usuario', 'description' => 'Cambiar el rol de una cuenta', 'depends_on' => ['users.view', 'roles.view']],
        'users.reset_password' => ['label' => 'Restablecer contraseñas', 'description' => 'Poner una contraseña nueva a otro usuario', 'depends_on' => ['users.view']],
    ];

    /**
     * Permisos del esquema anterior (por pantalla) y a qué equivalen hoy. La
     * migración traduce con esto los roles existentes para que cada rol pueda
     * hacer exactamente lo mismo que antes.
     */
    public const LEGACY = [
        'dashboard.view' => ['dashboard.view'],
        'geography.view' => ['neighborhoods.view', 'communes.view', 'cities.view', 'states.view'],
        'geography.manage' => ['neighborhoods.create', 'neighborhoods.update', 'neighborhoods.delete'],
        'map.view' => ['map.view'],
        'candidates.view' => ['candidates.view', 'results.export'],
        'elections.view' => ['elections.view', 'polling_tables.view'],
        'elections.create' => ['elections.create'],
        'elections.update' => ['elections.update'],
        'records.upload' => ['scrutiny_records.create'],
        'records.review' => ['scrutiny_records.view', 'scrutiny_records.update', 'scrutiny_records.approve', 'scrutiny_block_results.view', 'scrutiny_block_results.update', 'scrutiny_reviews.view'],
        'records.approve' => ['scrutiny_records.approve'],
        'ocr.process' => ['candidate_drafts.extract'],
        'slates.view' => ['slates.view', 'candidates.view'],
        'slates.capture' => ['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'slates.view', 'slates.create', 'candidates.view'],
        'slates.review' => ['candidate_drafts.view', 'candidate_drafts.approve'],
        'slates.promote' => ['candidate_drafts.promote'],
        'audit.view' => ['audit_logs.view'],
        'reports.view' => [],
        'users.view' => ['users.view', 'persons.view'],
        'users.create' => ['users.create', 'persons.create'],
        'users.update' => ['users.update', 'persons.update', 'users.reset_password'],
        'users.delete' => ['users.delete', 'persons.delete'],
        'roles.view' => ['roles.view', 'permissions.view'],
        'roles.manage' => ['roles.create', 'roles.update', 'roles.delete'],
        'roles.assign' => ['users.assign_role'],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * Lista plana: nombre => metadatos.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $flat = [];

        foreach (self::RESOURCES as $resource => $definition) {
            foreach ($definition['actions'] as $action) {
                $verb = self::CRUD_ACTIONS[$action];

                $flat["{$resource}.{$action}"] = [
                    'display_name' => "{$verb} ".mb_strtolower($definition['label']),
                    'description' => "{$verb} ".mb_strtolower($definition['label']),
                    'module' => $resource,
                    'module_label' => $definition['label'],
                    'group' => $definition['group'],
                    'group_label' => self::GROUPS[$definition['group']],
                    'action' => $action,
                    'special' => false,
                    'screen' => in_array($action, $definition['screen'] ?? [], true),
                    // Crear, editar o eliminar no sirve sin poder ver la tabla
                    // (salvo las operaciones marcadas como independientes).
                    'depends_on' => $action === self::VIEW || in_array($action, $definition['standalone'] ?? [], true)
                        ? []
                        : ["{$resource}.".self::VIEW],
                    'scope_note' => $definition['scope_note'][$action] ?? null,
                ];
            }
        }

        foreach (self::SPECIAL as $name => $definition) {
            $resource = explode('.', $name)[0];

            $flat[$name] = [
                'display_name' => $definition['label'],
                'description' => $definition['description'],
                'module' => 'special',
                'module_label' => 'Acciones especiales',
                'group' => 'special',
                'group_label' => 'Acciones especiales',
                'action' => 'special',
                'special' => true,
                // Tabla con la que se relaciona la acción (para ubicarla en pantalla).
                'related_resource' => isset(self::RESOURCES[$resource]) ? $resource : null,
                'screen' => $definition['screen'] ?? false,
                'depends_on' => $definition['depends_on'] ?? [],
                'scope_note' => null,
            ];
        }

        return self::$cache = $flat;
    }

    /**
     * Las tablas con sus operaciones, para pintar la matriz de roles y el Panel.
     *
     * @return list<array<string, mixed>>
     */
    public static function resources(): array
    {
        $resources = [];

        foreach (self::RESOURCES as $key => $definition) {
            $resources[] = [
                'key' => $key,
                'label' => $definition['label'],
                'singular' => $definition['singular'],
                'group' => $definition['group'],
                'group_label' => self::GROUPS[$definition['group']],
                'actions' => $definition['actions'],
                // Por qué esta tabla no tiene alguna de las cuatro operaciones.
                'why' => $definition['why'] ?? null,
            ];
        }

        return $resources;
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
     * `persons.create` trae `persons.view`. Los nombres fuera del catálogo se
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

    /**
     * Traduce permisos del esquema anterior a los actuales (con sus
     * dependencias). Un nombre que ya es del esquema nuevo se conserva.
     *
     * @param  iterable<string>  $legacyNames
     * @return list<string>
     */
    public static function translateLegacy(iterable $legacyNames): array
    {
        $catalog = self::all();
        $translated = [];

        foreach ($legacyNames as $name) {
            // Nombres que existen en los dos esquemas (users.view, elections.create…)
            // se traducen con LEGACY, que es el significado que tenían.
            foreach (self::LEGACY[$name] ?? (isset($catalog[$name]) ? [$name] : []) as $current) {
                $translated[] = $current;
            }
        }

        return self::withDependencies(array_values(array_unique($translated)));
    }

    /**
     * Nombres que solo existían en el esquema anterior. Si la base todavía
     * tiene alguno, los roles aún no se han traducido.
     *
     * @return list<string>
     */
    public static function legacyOnlyNames(): array
    {
        return array_values(array_diff(array_keys(self::LEGACY), array_keys(self::all())));
    }
}
