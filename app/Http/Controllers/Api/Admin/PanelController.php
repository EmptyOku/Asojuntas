<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ElectoralAccessGuard;
use App\Services\RoleAdministrationGuard;
use App\Support\CrudCatalog;
use App\Support\PermissionCatalog;
use App\Support\PersonData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Panel de administración de datos: un CRUD genérico para cada tabla descrita
 * en CrudCatalog.
 *
 * Los permisos son los de PermissionCatalog, por tabla y operación: para
 * listar `communes` hace falta communes.view; para crear, communes.create.
 * Como la tabla llega en la URL, el permiso se comprueba aquí (no con el
 * middleware de la ruta, que solo sabe de permisos fijos).
 *
 * "Eliminar" es un borrado suave: la fila queda en la papelera y se puede
 * restaurar. No se elimina lo que está en uso por otras tablas.
 */
class PanelController extends Controller
{
    public function __construct(private readonly ElectoralAccessGuard $guard) {}

    /** Tablas que este usuario puede ver, con lo que puede hacer en cada una. */
    public function resources(Request $request): JsonResponse
    {
        $user = $request->user();
        $resources = [];

        foreach (PermissionCatalog::resources() as $meta) {
            $definition = CrudCatalog::find($meta['key']);
            if (! $definition || ! $this->guard->hasPermission($user, "{$meta['key']}.view")) {
                continue;
            }

            $resources[] = [
                'key' => $meta['key'],
                'label' => $meta['label'],
                'singular' => $meta['singular'],
                'group' => $meta['group'],
                'group_label' => $meta['group_label'],
                'why' => $meta['why'],
                'can' => [
                    'create' => $this->allows($user, $meta['key'], 'create'),
                    'update' => $this->allows($user, $meta['key'], 'update'),
                    'delete' => $this->allows($user, $meta['key'], 'delete'),
                ],
                // Operaciones que se hacen en otra pantalla (con el permiso que el usuario tiene o no).
                'elsewhere' => collect($definition['elsewhere'] ?? [])
                    ->map(fn (array $note, string $op) => $note + ['op' => $op, 'allowed' => $this->guard->hasPermission($user, "{$meta['key']}.{$op}")])
                    ->values(),
                'soft_deletes' => $this->softDeletes($definition['model']),
                'fields' => array_map(fn (array $field) => [
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'list' => $field['list'] ?? false,
                    'edit' => $field['edit'] ?? false,
                    'required' => in_array('required', $field['rules'] ?? [], true),
                    'hint' => $field['hint'] ?? null,
                    'default' => $field['default'] ?? null,
                    'values' => $field['values'] ?? null,
                    'has_options' => isset($field['options']),
                ], $definition['fields']),
            ];
        }

        return response()->json(['success' => true, 'data' => $resources]);
    }

    /** Opciones de los campos de selección (llaves foráneas) de una tabla. */
    public function options(Request $request, string $resource): JsonResponse
    {
        $definition = $this->definition($resource);
        $this->authorizeOp($request->user(), $resource, 'view');

        $options = [];
        foreach ($definition['fields'] as $field) {
            if (isset($field['options'])) {
                $options[$field['name']] = $this->labelsFor($field['options'], null, 1000)
                    ->map(fn (string $label, $id) => ['value' => $id, 'label' => $label])
                    ->values();
            }
        }

        return response()->json(['success' => true, 'data' => $options]);
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $definition = $this->definition($resource);
        $user = $request->user();
        $this->authorizeOp($user, $resource, 'view');

        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
            'trashed' => 'nullable|boolean',
        ]);

        $query = $this->query($definition);

        // La papelera es parte de "eliminar": quien no puede eliminar no la ve.
        $trashed = (bool) ($validated['trashed'] ?? false);
        if ($trashed) {
            $this->authorizeOp($user, $resource, 'delete');
            abort_unless($this->softDeletes($definition['model']), 404);
            $query->onlyTrashed();
        }

        if (! empty($validated['search'])) {
            $term = '%'.trim($validated['search']).'%';
            $columns = collect($definition['fields'])->filter(fn ($f) => $f['search'] ?? false)->pluck('name');
            $query->where(function (Builder $q) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $q->orWhereLike($column, $term);
                }
            });
        }

        [$orderColumn, $orderDirection] = $this->order($definition);
        $page = $query->orderBy($orderColumn, $orderDirection)->orderBy('id')->paginate((int) ($validated['per_page'] ?? 15));

        $labels = $this->relatedLabels($definition, $page->getCollection());

        $rows = $page->getCollection()->map(function (Model $model) use ($definition, $labels): array {
            $row = ['id' => $model->getKey(), 'deleted_at' => $this->formatDate($model->getAttribute('deleted_at'), true)];

            foreach ($definition['fields'] as $field) {
                $value = $model->getAttribute($field['name']);
                $row[$field['name']] = $this->exportValue($field, $value);

                // Texto para mostrar: el nombre del registro relacionado o el valor traducido.
                if (isset($field['options']) || isset($field['ref'])) {
                    $row['_labels'][$field['name']] = $value === null ? null : ($labels[$field['name']][$value] ?? "#{$value}");
                } elseif (isset($field['values'])) {
                    $row['_labels'][$field['name']] = $field['values'][$value] ?? $value;
                }
            }

            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
                'trashed_total' => $this->softDeletes($definition['model']) && $this->allows($user, $resource, 'delete')
                    ? $this->query($definition)->onlyTrashed()->count()
                    : 0,
            ],
        ]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $definition = $this->definition($resource);
        $this->authorizePanelOp($request->user(), $resource, $definition, 'create');

        $data = $this->validated($request, $definition, 'create');
        $this->assertUnique($definition, $data, null);

        $model = new $definition['model'];
        $model->forceFill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Registro creado.',
            'data' => ['id' => $model->getKey()],
        ], 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $definition = $this->definition($resource);
        $user = $request->user();
        $this->authorizePanelOp($user, $resource, $definition, 'update');

        $model = $this->query($definition)->findOrFail($id);
        $this->runGuard($definition, 'update', $model, $user);

        $data = $this->validated($request, $definition, 'update');
        $this->assertUnique($definition, $data + $model->only($this->uniqueColumns($definition)), $model);

        $model->forceFill($data)->save();

        return response()->json(['success' => true, 'message' => 'Cambios guardados.', 'data' => ['id' => $model->getKey()]]);
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $definition = $this->definition($resource);
        $user = $request->user();
        $this->authorizePanelOp($user, $resource, $definition, 'delete');

        $model = $this->query($definition)->findOrFail($id);
        $this->runGuard($definition, 'delete', $model, $user);
        $this->assertNotInUse($definition, $model);

        $delete = function () use ($model): void {
            // Lo eliminado también deja de estar "activo": varias consultas del
            // sistema (mapa, resultados) filtran por ese campo y no por deleted_at.
            if (array_key_exists('is_active', $model->getAttributes())) {
                $model->forceFill(['is_active' => false])->save();
            }
            $model->delete();
        };

        // Eliminar un usuario no puede dejar al sistema sin nadie que administre roles.
        if ($model instanceof User) {
            app(RoleAdministrationGuard::class)->preserve($delete);
        } else {
            DB::transaction($delete);
        }

        return response()->json(['success' => true, 'message' => 'Registro eliminado. Está en la papelera y se puede restaurar.']);
    }

    public function restore(Request $request, string $resource, int $id): JsonResponse
    {
        $definition = $this->definition($resource);
        $this->authorizePanelOp($request->user(), $resource, $definition, 'delete');
        abort_unless($this->softDeletes($definition['model']), 404);

        $model = $this->query($definition)->onlyTrashed()->findOrFail($id);

        // Mientras estuvo en la papelera pudo crearse otro con los mismos datos.
        $this->assertUnique($definition, $model->only($this->uniqueColumns($definition)), $model, restoring: true);

        $model->restore();

        $inactive = array_key_exists('is_active', $model->getAttributes()) && ! $model->is_active;

        return response()->json([
            'success' => true,
            'message' => $inactive
                ? 'Registro restaurado. Quedó inactivo: actívalo cuando corresponda.'
                : 'Registro restaurado.',
        ]);
    }

    // ------------------------------------------------------------------ permisos

    private function definition(string $resource): array
    {
        return CrudCatalog::find($resource) ?? throw new NotFoundHttpException('Esa tabla no se administra desde el Panel.');
    }

    private function authorizeOp(User $user, string $resource, string $op): void
    {
        if (! $this->guard->hasPermission($user, "{$resource}.{$op}")) {
            throw new AccessDeniedHttpException('No autorizado para esta acción');
        }
    }

    /** Permiso del usuario Y que el Panel ofrezca esa operación para la tabla. */
    private function authorizePanelOp(User $user, string $resource, array $definition, string $op): void
    {
        $this->authorizeOp($user, $resource, $op);

        if (! in_array($op, $definition['ops'], true)) {
            throw ValidationException::withMessages([
                'resource' => $definition['elsewhere'][$op]['text'] ?? 'Esta operación no está disponible para esta tabla.',
            ]);
        }
    }

    private function allows(User $user, string $resource, string $op): bool
    {
        $definition = CrudCatalog::find($resource);

        return $definition !== null
            && in_array($op, $definition['ops'], true)
            && $this->guard->hasPermission($user, "{$resource}.{$op}");
    }

    private function runGuard(array $definition, string $op, Model $model, User $actor): void
    {
        $reason = isset($definition['guard']) ? $definition['guard']($op, $model, $actor) : null;

        if ($reason !== null) {
            throw ValidationException::withMessages(['resource' => $reason]);
        }
    }

    // ------------------------------------------------------------------ consultas

    private function query(array $definition): Builder
    {
        return $definition['model']::query();
    }

    private function softDeletes(string $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    /** @return array{0: string, 1: string} */
    private function order(array $definition): array
    {
        $order = $definition['order'] ?? [collect($definition['fields'])->firstWhere('list', true)['name'] ?? 'id'];

        return [$order[0], in_array($order[1] ?? 'asc', ['asc', 'desc'], true) ? ($order[1] ?? 'asc') : 'asc'];
    }

    /**
     * Nombres de los registros relacionados de la página (una consulta por
     * campo, no una por fila).
     *
     * @return array<string, \Illuminate\Support\Collection<int|string, string>>
     */
    private function relatedLabels(array $definition, $models): array
    {
        $labels = [];

        foreach ($definition['fields'] as $field) {
            $source = $field['options'] ?? $field['ref'] ?? null;
            if ($source === null) {
                continue;
            }

            $ids = $models->pluck($field['name'])->filter()->unique()->values();
            $labels[$field['name']] = $ids->isEmpty() ? collect() : $this->labelsFor($source, $ids->all(), null);
        }

        return $labels;
    }

    /** @return \Illuminate\Support\Collection<int|string, string> id => texto */
    private function labelsFor(array $source, ?array $ids, ?int $limit)
    {
        $query = DB::table($source['table'])->select(['id', ...$source['label']]);

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        } elseif (Schema::hasColumn($source['table'], 'deleted_at')) {
            // Para elegir en un formulario no se ofrece lo que está en la papelera.
            $query->whereNull('deleted_at');
        }

        if ($limit !== null) {
            $query->orderBy($source['label'][0])->limit($limit);
        }

        return $query->get()->mapWithKeys(fn ($row) => [
            $row->id => trim(preg_replace('/\s+/', ' ', implode(' ', array_map(fn ($column) => (string) ($row->{$column} ?? ''), $source['label']))) ?? ''),
        ]);
    }

    private function exportValue(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'boolean' => $value === null ? null : (bool) $value,
            'date' => $this->formatDate($value, false),
            default => $value,
        };
    }

    private function formatDate(mixed $value, bool $withTime): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            // Solo fecha salvo que la columna guarde también la hora.
            return $withTime || $date->format('H:i:s') !== '00:00:00' ? $date->format('Y-m-d H:i') : $date->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    // ------------------------------------------------------------------ validación

    /** Campos que el formulario puede escribir en esta operación. */
    private function editableFields(array $definition, string $op): array
    {
        return array_values(array_filter($definition['fields'], fn (array $field) => match ($field['edit'] ?? false) {
            true => true,
            'create' => $op === 'create',
            default => false,
        }));
    }

    private function validated(Request $request, array $definition, string $op): array
    {
        // "1.070.622" y "1070622" son el mismo documento.
        PersonData::normalizeRequest($request);

        $fields = $this->editableFields($definition, $op);
        $rules = [];
        $names = [];

        foreach ($fields as $field) {
            $rules[$field['name']] = $field['rules'] ?? ['nullable'];
            $names[$field['name']] = mb_strtolower($field['label']);
        }

        $data = Validator::make($request->only(array_keys($rules)), $rules, [], $names)->validate();

        foreach ($fields as $field) {
            if (! array_key_exists($field['name'], $data)) {
                // Al crear, lo que no llega toma su valor por defecto (p. ej. "activo").
                if ($op === 'create' && array_key_exists('default', $field)) {
                    $data[$field['name']] = $field['default'];
                }

                continue;
            }

            if (is_string($data[$field['name']])) {
                $data[$field['name']] = trim($data[$field['name']]);
            }
            if ($data[$field['name']] === '' && in_array('nullable', $field['rules'] ?? [], true)) {
                $data[$field['name']] = null;
            }
        }

        return $data;
    }

    /** @return list<string> */
    private function uniqueColumns(array $definition): array
    {
        return array_values(array_unique(array_merge(...($definition['unique'] ?? [[]]))));
    }

    /**
     * Combinaciones que no se pueden repetir. Si la que choca está en la
     * papelera, se dice así: lo correcto es restaurarla, no crear otra.
     */
    private function assertUnique(array $definition, array $values, ?Model $current, bool $restoring = false): void
    {
        foreach ($definition['unique'] ?? [] as $columns) {
            if (array_diff($columns, array_keys($values)) !== []) {
                continue;
            }

            $query = $this->query($definition);
            if ($this->softDeletes($definition['model']) && ! $restoring) {
                $query->withTrashed();
            }
            foreach ($columns as $column) {
                $query->where($column, $values[$column]);
            }
            if ($current) {
                $query->whereKeyNot($current->getKey());
            }

            $existing = $query->first();
            if (! $existing) {
                continue;
            }

            $field = collect($definition['fields'])->firstWhere('name', end($columns));
            $label = mb_strtolower($field['label'] ?? end($columns));
            $trashed = method_exists($existing, 'trashed') && $existing->trashed();

            throw ValidationException::withMessages([
                end($columns) => match (true) {
                    $restoring => "No se puede restaurar: ya hay otro registro activo con el mismo {$label}.",
                    $trashed => "Ya existe un registro con ese {$label}, pero está en la papelera. Restáuralo en vez de crear otro.",
                    default => "Ya existe un registro con ese {$label}.",
                },
            ]);
        }
    }

    /** No se elimina lo que otras tablas todavía usan. */
    private function assertNotInUse(array $definition, Model $model): void
    {
        $uses = [];

        foreach ($definition['used_by'] ?? [] as $child) {
            $query = DB::table($child['table'])->where($child['column'], $model->getKey());
            if (($child['soft'] ?? true) && Schema::hasColumn($child['table'], 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $count = $query->count();
            if ($count > 0) {
                $uses[] = "{$count} {$child['label']}";
            }
        }

        if ($uses !== []) {
            throw ValidationException::withMessages([
                'resource' => 'No se puede eliminar porque está en uso: '.implode(', ', $uses).'. Elimina o reasigna esos registros primero.',
            ]);
        }
    }
}
