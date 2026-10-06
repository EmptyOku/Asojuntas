<?php

namespace App\Support;

use App\Models;
use App\Models\User;
use App\Services\ElectoralAccessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Descripción de cada tabla que se administra desde el Panel.
 *
 * El Panel es UN motor genérico (PanelController + PanelView): no hay una
 * pantalla ni un controlador por tabla. Lo que cambia de una tabla a otra
 * está aquí: sus campos, validaciones, relaciones y restricciones. Agregar
 * una tabla al Panel es agregar una entrada.
 *
 * Las claves coinciden con PermissionCatalog: la tabla `communes` se protege
 * con communes.view / create / update / delete.
 *
 * Por tabla:
 *  - ops: operaciones que el Panel ofrece además de "ver". Nunca más de las
 *    que da PermissionCatalog.
 *  - elsewhere: operaciones que existen pero se hacen en otra pantalla,
 *    porque llevan reglas que un formulario genérico rompería (crear una
 *    elección arma sus bloques, cargos y mesa; crear un usuario pide rol y
 *    contraseña, con la regla anti-escalada).
 *  - unique: combinaciones que no se pueden repetir.
 *  - used_by: tablas hijas. Mientras tengan filas activas, la fila no se elimina.
 *  - guard: regla extra antes de editar o eliminar; devuelve el motivo del
 *    rechazo o null.
 *
 * Por campo:
 *  - type: text | textarea | email | number | decimal | boolean | date |
 *    select (llave foránea elegible) | ref (llave foránea solo de lectura).
 *  - edit: true (crear y editar), 'create' (solo al crear) o false (solo lectura).
 *  - list: se muestra como columna.  search: entra en la búsqueda.
 *  - values: traducción de valores guardados en código (pending -> Pendiente).
 */
final class CrudCatalog
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::$cache ??= self::definitions();
    }

    /** @return array<string, mixed>|null */
    public static function find(string $resource): ?array
    {
        return self::all()[$resource] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    private static function definitions(): array
    {
        $active = ['name' => 'is_active', 'label' => 'Activo', 'type' => 'boolean', 'rules' => ['boolean'], 'list' => true, 'edit' => true, 'default' => true];
        $personName = ['first_name', 'middle_name', 'last_name', 'second_last_name'];
        $status = ['draft' => 'Borrador', 'pending' => 'Pendiente', 'pending_review' => 'En revisión', 'reviewed' => 'Revisada', 'approved' => 'Aprobada', 'rejected' => 'Rechazada', 'consolidated' => 'Consolidada'];

        return [
            // ------------------------------------------------------------ Territorio
            'states' => [
                'model' => Models\State::class,
                'ops' => [],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'list' => true, 'search' => true],
                ],
            ],
            'cities' => [
                'model' => Models\City::class,
                'ops' => [],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'state_id', 'label' => 'Departamento', 'type' => 'ref', 'ref' => ['table' => 'states', 'label' => ['name']], 'list' => true],
                ],
            ],
            'communes' => [
                'model' => Models\Commune::class,
                'ops' => ['create', 'update', 'delete'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'rules' => ['required', 'string', 'max:30'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Ej.: COM08'],
                    ['name' => 'city_id', 'label' => 'Ciudad', 'type' => 'select', 'options' => ['table' => 'cities', 'label' => ['name']], 'rules' => ['required', 'integer', 'exists:cities,id'], 'list' => true, 'edit' => true],
                ],
                'unique' => [['city_id', 'code'], ['city_id', 'name']],
                'used_by' => [['table' => 'neighborhoods', 'column' => 'commune_id', 'label' => 'barrio(s)']],
            ],
            'neighborhoods' => [
                'model' => Models\Neighborhood::class,
                'ops' => ['create', 'update', 'delete'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'rules' => ['required', 'string', 'max:60'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Ej.: COM01-CENTRO'],
                    ['name' => 'commune_id', 'label' => 'Comuna', 'type' => 'select', 'options' => ['table' => 'communes', 'label' => ['name']], 'rules' => ['required', 'integer', 'exists:communes,id'], 'list' => true, 'edit' => true],
                    ['name' => 'type', 'label' => 'Tipo', 'type' => 'select', 'values' => ['barrio' => 'Barrio', 'urbanizacion' => 'Urbanización', 'vereda' => 'Vereda', 'sector' => 'Sector', 'jvc' => 'Junta de vivienda', 'ciudadela' => 'Ciudadela'], 'rules' => ['required', 'in:barrio,urbanizacion,vereda,sector,jvc,ciudadela'], 'list' => true, 'edit' => true, 'default' => 'barrio'],
                    ['name' => 'is_verified', 'label' => 'Verificado', 'type' => 'boolean', 'rules' => ['boolean'], 'list' => true, 'edit' => true, 'default' => false],
                    ['name' => 'notes', 'label' => 'Notas', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000'], 'edit' => true],
                ],
                'unique' => [['commune_id', 'code'], ['commune_id', 'name']],
                'used_by' => [
                    ['table' => 'elections', 'column' => 'neighborhood_id', 'label' => 'elección(es)'],
                    ['table' => 'persons', 'column' => 'neighborhood_id', 'label' => 'persona(s)'],
                ],
            ],

            // ------------------------------------------------------------ Catálogos
            'document_types' => [
                'model' => Models\DocumentType::class,
                'ops' => ['create', 'update', 'delete'],
                'fields' => [
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'rules' => ['required', 'string', 'max:20'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Ej.: CC'],
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'description', 'label' => 'Descripción', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500'], 'edit' => true],
                    $active,
                ],
                'unique' => [['code'], ['name']],
                'used_by' => [['table' => 'persons', 'column' => 'document_type_id', 'label' => 'persona(s)']],
            ],
            'blocks' => [
                'model' => Models\Block::class,
                'ops' => ['create', 'update', 'delete'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'rules' => ['required', 'string', 'max:30'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Ej.: DIR'],
                    ['name' => 'description', 'label' => 'Descripción', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500'], 'edit' => true],
                    $active,
                ],
                'unique' => [['code']],
                'used_by' => [['table' => 'positions', 'column' => 'block_id', 'label' => 'cargo(s)']],
            ],
            'positions' => [
                'model' => Models\Position::class,
                'ops' => ['create', 'update', 'delete'],
                'order' => ['block_id', 'order_number'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'rules' => ['required', 'string', 'max:40'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Ej.: DIR_PRES'],
                    ['name' => 'block_id', 'label' => 'Bloque', 'type' => 'select', 'options' => ['table' => 'blocks', 'label' => ['name']], 'rules' => ['required', 'integer', 'exists:blocks,id'], 'list' => true, 'edit' => true],
                    ['name' => 'order_number', 'label' => 'Orden', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1', 'max:99'], 'list' => true, 'edit' => true, 'hint' => 'Orden de jerarquía dentro del bloque (1 = primero).'],
                    ['name' => 'description', 'label' => 'Descripción', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500'], 'edit' => true],
                    $active,
                ],
                'unique' => [['block_id', 'code'], ['block_id', 'name']],
                'used_by' => [['table' => 'election_block_positions', 'column' => 'position_id', 'label' => 'elección(es) que lo usan']],
            ],

            // ------------------------------------------------------------ Personas y acceso
            'persons' => [
                'model' => Models\Person::class,
                'ops' => ['create', 'update', 'delete'],
                'order' => ['first_name', 'last_name'],
                'fields' => [
                    ['name' => 'document_type_id', 'label' => 'Tipo de documento', 'type' => 'select', 'options' => ['table' => 'document_types', 'label' => ['code']], 'rules' => ['required', 'integer', 'exists:document_types,id'], 'list' => true, 'edit' => true],
                    ['name' => 'document_number', 'label' => 'Número de documento', 'type' => 'text', 'rules' => ['required', 'string', 'max:30'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Sin puntos ni espacios.'],
                    ['name' => 'first_name', 'label' => 'Primer nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'middle_name', 'label' => 'Segundo nombre', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'edit' => true],
                    ['name' => 'last_name', 'label' => 'Primer apellido', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'second_last_name', 'label' => 'Segundo apellido', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'edit' => true],
                    ['name' => 'phone', 'label' => 'Celular', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20'], 'edit' => true],
                    ['name' => 'email', 'label' => 'Correo', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:150'], 'search' => true, 'edit' => true],
                    ['name' => 'neighborhood_id', 'label' => 'Barrio', 'type' => 'select', 'options' => ['table' => 'neighborhoods', 'label' => ['name']], 'rules' => ['nullable', 'integer', 'exists:neighborhoods,id'], 'list' => true, 'edit' => true],
                    $active,
                ],
                'unique' => [['document_type_id', 'document_number']],
                'used_by' => [
                    ['table' => 'users', 'column' => 'person_id', 'label' => 'cuenta(s) de usuario'],
                    ['table' => 'candidates', 'column' => 'person_id', 'label' => 'candidatura(s) oficial(es)'],
                ],
            ],
            'users' => [
                'model' => Models\User::class,
                'ops' => ['delete'],
                'order' => ['username'],
                'elsewhere' => [
                    'create' => ['text' => 'Las cuentas se crean en "Usuarios y accesos", que pide el rol, la contraseña y la persona.', 'route' => 'admin-roles'],
                    'update' => ['text' => 'Los datos, el rol y la contraseña de una cuenta se cambian en "Usuarios y accesos".', 'route' => 'admin-roles'],
                ],
                'fields' => [
                    ['name' => 'username', 'label' => 'Usuario', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'email', 'label' => 'Correo', 'type' => 'email', 'list' => true, 'search' => true],
                    ['name' => 'person_id', 'label' => 'Persona', 'type' => 'ref', 'ref' => ['table' => 'persons', 'label' => $personName], 'list' => true],
                    ['name' => 'is_active', 'label' => 'Activo', 'type' => 'boolean', 'list' => true],
                    ['name' => 'last_login_at', 'label' => 'Último ingreso', 'type' => 'date', 'list' => true],
                ],
                'guard' => function (string $op, Model $user, User $actor): ?string {
                    if ($op !== 'delete') {
                        return null;
                    }
                    if ((int) $user->getKey() === (int) $actor->getKey()) {
                        return 'No puedes eliminar tu propia cuenta.';
                    }
                    // Regla anti-escalada: no se administra una cuenta con más permisos que la propia.
                    app(ElectoralAccessGuard::class)->assertCanManageUser($actor, $user);

                    return null;
                },
            ],
            'roles' => [
                'model' => Models\Role::class,
                'ops' => ['delete'],
                'order' => ['display_name'],
                'elsewhere' => [
                    'create' => ['text' => 'Los roles se crean en "Usuarios y accesos → Roles y permisos", con la matriz de permisos.', 'route' => 'admin-roles'],
                    'update' => ['text' => 'Los permisos de un rol se editan en "Usuarios y accesos → Roles y permisos".', 'route' => 'admin-roles'],
                ],
                'fields' => [
                    ['name' => 'display_name', 'label' => 'Nombre', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'name', 'label' => 'Nombre técnico', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'description', 'label' => 'Descripción', 'type' => 'text', 'list' => true],
                    ['name' => 'is_active', 'label' => 'Activo', 'type' => 'boolean', 'list' => true],
                ],
                'used_by' => [['table' => 'model_has_roles', 'column' => 'role_id', 'label' => 'usuario(s) que lo tienen', 'soft' => false]],
                'guard' => fn (string $op, Model $role, User $actor): ?string => $op === 'delete' && $role->name === 'super_admin'
                    ? 'El rol Super Admin es del sistema y no se puede eliminar.'
                    : null,
            ],
            'permissions' => [
                'model' => Models\Permission::class,
                'ops' => [],
                'order' => ['name'],
                'fields' => [
                    ['name' => 'display_name', 'label' => 'Permiso', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'name', 'label' => 'Nombre técnico', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'description', 'label' => 'Descripción', 'type' => 'text', 'list' => true, 'search' => true],
                ],
            ],

            // ------------------------------------------------------------ Elección
            'elections' => [
                'model' => Models\Election::class,
                'ops' => ['update', 'delete'],
                'elsewhere' => [
                    'create' => ['text' => 'Las elecciones se abren en "Geografía Electoral": ahí se crean con sus bloques, cargos y mesa.', 'route' => 'admin-geography'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:200'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'list' => true, 'search' => true],
                    ['name' => 'neighborhood_id', 'label' => 'Barrio', 'type' => 'ref', 'ref' => ['table' => 'neighborhoods', 'label' => ['name']], 'list' => true],
                    ['name' => 'election_date', 'label' => 'Fecha', 'type' => 'date', 'rules' => ['required', 'date'], 'list' => true, 'edit' => true],
                    ['name' => 'period_year', 'label' => 'Año del periodo', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'edit' => true],
                    ['name' => 'description', 'label' => 'Descripción', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500'], 'edit' => true],
                    ['name' => 'is_active', 'label' => 'Activa', 'type' => 'boolean', 'list' => true],
                ],
                'used_by' => [
                    ['table' => 'scrutiny_records', 'column' => 'election_id', 'label' => 'acta(s)'],
                    ['table' => 'candidates', 'column' => 'election_id', 'label' => 'candidato(s) oficial(es)'],
                    ['table' => 'candidate_drafts', 'column' => 'election_id', 'label' => 'candidato(s) en revisión'],
                ],
            ],
            'polling_tables' => [
                'model' => Models\PollingTable::class,
                'ops' => ['create', 'update', 'delete'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'rules' => ['required', 'string', 'max:30'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Ej.: MESA-002'],
                    ['name' => 'election_id', 'label' => 'Elección', 'type' => 'select', 'options' => ['table' => 'elections', 'label' => ['name']], 'rules' => ['required', 'integer', 'exists:elections,id'], 'list' => true, 'edit' => 'create'],
                    ['name' => 'location', 'label' => 'Lugar', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:200'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'capacity', 'label' => 'Capacidad', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1', 'max:100000'], 'edit' => true],
                    $active,
                ],
                'unique' => [['election_id', 'code'], ['election_id', 'name']],
                'used_by' => [['table' => 'scrutiny_records', 'column' => 'polling_table_id', 'label' => 'acta(s)']],
            ],
            'slates' => [
                'model' => Models\Slate::class,
                'ops' => ['create', 'update', 'delete'],
                'order' => ['election_id', 'code'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'list' => true, 'search' => true, 'edit' => true, 'hint' => 'Ej.: Plancha 4'],
                    ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'rules' => ['required', 'string', 'regex:/^P[0-9]+$/'], 'list' => true, 'search' => true, 'edit' => 'create', 'hint' => 'P seguido del número: P4. Es el número del tarjetón.'],
                    ['name' => 'election_id', 'label' => 'Elección', 'type' => 'select', 'options' => ['table' => 'elections', 'label' => ['name']], 'rules' => ['required', 'integer', 'exists:elections,id'], 'list' => true, 'edit' => 'create'],
                    ['name' => 'description', 'label' => 'Descripción', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500'], 'edit' => true],
                    $active,
                ],
                'unique' => [['election_id', 'code'], ['election_id', 'name']],
                'used_by' => [['table' => 'candidate_drafts', 'column' => 'slate_id', 'label' => 'candidato(s) en revisión']],
                'guard' => function (string $op, Model $slate, User $actor): ?string {
                    if ($op !== 'delete') {
                        return null;
                    }
                    $official = DB::table('candidates')
                        ->join('slate_blocks', 'slate_blocks.id', '=', 'candidates.slate_block_id')
                        ->where('slate_blocks.slate_id', $slate->getKey())
                        ->whereNull('candidates.deleted_at')
                        ->count();

                    return $official > 0 ? "No se puede eliminar: la plancha tiene {$official} candidato(s) oficial(es)." : null;
                },
            ],
            'candidate_drafts' => [
                'model' => Models\CandidateDraft::class,
                'ops' => ['update', 'delete'],
                'order' => ['id', 'desc'],
                'elsewhere' => [
                    'create' => ['text' => 'Los candidatos se registran escaneando la plancha en "Escanear Planchas".', 'route' => 'secretary-capture'],
                ],
                'fields' => [
                    ['name' => 'first_name', 'label' => 'Primer nombre', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'middle_name', 'label' => 'Segundo nombre', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'edit' => true],
                    ['name' => 'last_name', 'label' => 'Primer apellido', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'second_last_name', 'label' => 'Segundo apellido', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'edit' => true],
                    ['name' => 'document_number', 'label' => 'Documento', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:30'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'phone', 'label' => 'Celular', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20'], 'edit' => true],
                    ['name' => 'email', 'label' => 'Correo', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:150'], 'edit' => true],
                    ['name' => 'election_id', 'label' => 'Elección', 'type' => 'ref', 'ref' => ['table' => 'elections', 'label' => ['name']], 'list' => true],
                    ['name' => 'review_status', 'label' => 'Revisión', 'type' => 'text', 'values' => ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'], 'list' => true],
                    ['name' => 'is_processed', 'label' => 'Oficial', 'type' => 'boolean', 'list' => true],
                ],
                'guard' => fn (string $op, Model $draft, User $actor): ?string => $draft->is_processed
                    ? 'Este candidato ya es oficial: no se modifica ni se elimina desde aquí. Adminístralo en "Candidatos oficiales".'
                    : null,
            ],
            'candidates' => [
                'model' => Models\Candidate::class,
                'ops' => ['update', 'delete'],
                'order' => ['id', 'desc'],
                'fields' => [
                    ['name' => 'person_id', 'label' => 'Persona', 'type' => 'ref', 'ref' => ['table' => 'persons', 'label' => $personName], 'list' => true],
                    ['name' => 'election_id', 'label' => 'Elección', 'type' => 'ref', 'ref' => ['table' => 'elections', 'label' => ['name']], 'list' => true],
                    ['name' => 'is_substitute', 'label' => 'Es suplente', 'type' => 'boolean', 'rules' => ['boolean'], 'list' => true, 'edit' => true],
                    $active,
                ],
            ],

            // ------------------------------------------------------------ Escrutinio
            'scrutiny_records' => [
                'model' => Models\ScrutinyRecord::class,
                'ops' => ['update'],
                'order' => ['id', 'desc'],
                'fields' => [
                    ['name' => 'record_number', 'label' => 'Número del acta', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:60'], 'list' => true, 'search' => true, 'edit' => true],
                    ['name' => 'election_id', 'label' => 'Elección', 'type' => 'ref', 'ref' => ['table' => 'elections', 'label' => ['name']], 'list' => true],
                    ['name' => 'record_date', 'label' => 'Fecha', 'type' => 'date', 'rules' => ['nullable', 'date'], 'list' => true, 'edit' => true],
                    ['name' => 'status', 'label' => 'Estado', 'type' => 'text', 'values' => $status, 'list' => true],
                    ['name' => 'observations', 'label' => 'Observaciones', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000'], 'edit' => true],
                ],
                'unique' => [['election_id', 'record_number']],
            ],
            'scrutiny_block_results' => [
                'model' => Models\ScrutinyBlockResult::class,
                'ops' => ['update'],
                'order' => ['id', 'desc'],
                'fields' => [
                    ['name' => 'scrutiny_record_id', 'label' => 'Acta', 'type' => 'ref', 'ref' => ['table' => 'scrutiny_records', 'label' => ['record_number']], 'list' => true],
                    ['name' => 'election_id', 'label' => 'Elección', 'type' => 'ref', 'ref' => ['table' => 'elections', 'label' => ['name']], 'list' => true],
                    ['name' => 'votes', 'label' => 'Votos', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:100000'], 'list' => true, 'edit' => true],
                    ['name' => 'status', 'label' => 'Estado', 'type' => 'text', 'values' => $status, 'list' => true],
                ],
            ],
            'scrutiny_reviews' => [
                'model' => Models\ScrutinyReview::class,
                'ops' => [],
                'order' => ['id', 'desc'],
                'fields' => [
                    ['name' => 'scrutiny_record_id', 'label' => 'Acta', 'type' => 'ref', 'ref' => ['table' => 'scrutiny_records', 'label' => ['record_number']], 'list' => true],
                    ['name' => 'decision', 'label' => 'Decisión', 'type' => 'text', 'values' => ['approved' => 'Aprobada', 'rejected' => 'Rechazada', 'reviewed' => 'Revisada'], 'list' => true],
                    ['name' => 'reviewed_by_user_id', 'label' => 'Quién decidió', 'type' => 'ref', 'ref' => ['table' => 'users', 'label' => ['username']], 'list' => true],
                    ['name' => 'reviewed_at', 'label' => 'Cuándo', 'type' => 'date', 'list' => true],
                    ['name' => 'comments', 'label' => 'Motivo u observación', 'type' => 'text', 'list' => true, 'search' => true],
                ],
            ],
        ];
    }
}
