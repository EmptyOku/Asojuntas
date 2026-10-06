<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos pasan de ser "por pantalla" a ser "por tabla y operación"
 * (persons.create, neighborhoods.delete…) más unas pocas acciones especiales
 * (aprobar actas, oficializar planchas…). Ver App\Support\PermissionCatalog.
 *
 * Cada rol existente —incluidos los creados desde la interfaz— se traduce al
 * esquema nuevo con PermissionCatalog::LEGACY, de modo que pueda hacer
 * exactamente lo mismo que antes. Super Admin recibe además los permisos
 * nuevos que no existían (catálogos, mesas, eliminar elecciones…).
 *
 * Los permisos viejos que ya no existen se eliminan al final.
 */
return new class extends Migration
{
    public function up(): void
    {
        $catalog = PermissionCatalog::all();
        $now = now();

        // Permisos que cada rol (y cada usuario, si tuviera directos) tenía ANTES.
        $before = fn (string $pivot, string $owner) => DB::table($pivot)
            ->join('permissions', 'permissions.id', '=', "{$pivot}.permission_id")
            ->get(["{$pivot}.{$owner} as owner_id", 'permissions.name'])
            ->groupBy('owner_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $rolesBefore = $before('role_has_permissions', 'role_id');
        $usersBefore = $before('model_has_permissions', 'model_id');

        // Solo se traduce si la base aún tiene permisos del esquema anterior. Así
        // la migración no hace nada raro si se ejecuta sobre una base ya traducida
        // (varios nombres, como users.view, existen en los dos esquemas).
        $needsTranslation = DB::table('permissions')->whereIn('name', PermissionCatalog::legacyOnlyNames())->exists();

        // 1. Los permisos del catálogo nuevo existen todos.
        DB::table('permissions')->upsert(
            collect($catalog)->map(fn (array $meta, string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'display_name' => $meta['display_name'],
                'description' => $meta['description'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['name', 'guard_name'],
            ['display_name', 'description', 'is_active', 'updated_at'],
        );

        $ids = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');
        $superAdminId = DB::table('roles')->where('name', 'super_admin')->value('id');

        // 2. Cada rol queda con la traducción de lo que tenía.
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            if ((int) $roleId !== (int) $superAdminId && ! $needsTranslation) {
                continue;
            }

            $names = (int) $roleId === (int) $superAdminId
                ? array_keys($catalog)
                : PermissionCatalog::translateLegacy($rolesBefore[$roleId] ?? []);

            $permissionIds = collect($names)->map(fn (string $name) => $ids[$name] ?? null)->filter()->unique()->values();

            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('role_has_permissions')->insert(
                $permissionIds->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])->all()
            );

            // Copia de auditoría que mantiene el sistema (tabla role_permissions).
            if (Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->where('role_id', $roleId)->delete();
                DB::table('role_permissions')->insert($permissionIds->map(fn ($id) => [
                    'role_id' => $roleId,
                    'permission_id' => $id,
                    'assigned_at' => $now,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        }

        // 3. Permisos dados directamente a un usuario (hoy no se usan, pero se respetan).
        foreach ($usersBefore as $userId => $names) {
            $rows = DB::table('model_has_permissions')->where('model_id', $userId)->first();
            if (! $rows || ! $needsTranslation) {
                continue;
            }

            DB::table('model_has_permissions')->where('model_id', $userId)->delete();
            DB::table('model_has_permissions')->insert(
                collect(PermissionCatalog::translateLegacy($names))
                    ->map(fn (string $name) => $ids[$name] ?? null)->filter()->unique()
                    ->map(fn ($id) => ['permission_id' => $id, 'model_type' => $rows->model_type, 'model_id' => $userId])
                    ->values()->all()
            );
        }

        // 4. Lo que ya no está en el catálogo se retira.
        $obsolete = DB::table('permissions')->whereNotIn('name', array_keys($catalog))->pluck('id');
        if ($obsolete->isNotEmpty()) {
            foreach (['role_has_permissions', 'model_has_permissions', 'role_permissions'] as $pivot) {
                if (Schema::hasTable($pivot)) {
                    DB::table($pivot)->whereIn('permission_id', $obsolete)->delete();
                }
            }
            DB::table('permissions')->whereIn('id', $obsolete)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sin vuelta atrás automática: el esquema anterior agrupaba varias tablas
        // en un mismo permiso y esa agrupación no se puede reconstruir desde este.
    }
};
