<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Agrega los permisos de pantalla y de planchas, y se los da a los roles que
 * hoy ya llegan a esas pantallas, para que ningún rol existente (incluidos los
 * creados desde la UI) pierda acceso con el cambio:
 *
 * - Panel admin (antes: users.view) -> dashboard.view.
 * - Geografía, mapa, directorio y planchas por barrio (antes: users.view + elections.view).
 * - Editar geografía (antes: users.view + elections.update) -> geography.manage.
 * - Secretaría de planchas (antes: records.upload; en la práctica solo la usaban
 *   roles que además revisan actas) -> slates.*. Los roles solo con records.upload
 *   (jurados) quedan fuera de la secretaría, que es la separación buscada.
 *
 * La lista queda fija aquí a propósito: es la foto del cambio. La fuente viva de
 * permisos es App\Support\PermissionCatalog (vía PermissionSeeder).
 */
return new class extends Migration
{
    private const NEW_PERMISSIONS = [
        'dashboard.view' => ['View dashboard', 'Ver el dashboard'],
        'geography.view' => ['View geography', 'Ver geografía electoral'],
        'geography.manage' => ['Manage geography', 'Crear, editar y ubicar barrios'],
        'map.view' => ['View map', 'Ver el mapa interactivo'],
        'candidates.view' => ['View JAC directory', 'Ver el directorio JAC y resultados por barrio'],
        'ocr.process' => ['Process OCR', 'Reprocesar OCR de candidatos'],
        'slates.view' => ['View slates', 'Ver planchas por barrio (solo lectura)'],
        'slates.capture' => ['Capture slates', 'Capturar planchas (secretaría)'],
        'slates.review' => ['Review slates', 'Revisar, aprobar y rechazar planchas'],
        'slates.promote' => ['Promote slates', 'Oficializar planchas aprobadas'],
    ];

    /** Permiso nuevo => listas de permisos previos; basta con cumplir una lista completa. */
    private const GRANTS = [
        'dashboard.view' => [['users.view']],
        'geography.view' => [['users.view', 'elections.view']],
        'map.view' => [['users.view', 'elections.view']],
        'candidates.view' => [['users.view', 'elections.view']],
        'geography.manage' => [['users.view', 'elections.update']],
        'slates.view' => [['users.view', 'elections.view'], ['records.upload', 'records.review']],
        'slates.capture' => [['records.upload', 'records.review']],
        'slates.review' => [['records.upload', 'records.review']],
        'slates.promote' => [['records.upload', 'records.review']],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::NEW_PERMISSIONS as $name => [$displayName, $description]) {
            $exists = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'display_name' => $displayName,
                    'description' => $description,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');

        // Permisos actuales por rol: role_id => [nombre, ...]
        $rolePermissions = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->get(['role_has_permissions.role_id', 'permissions.name'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $rows = [];
        foreach ($rolePermissions as $roleId => $current) {
            foreach (self::GRANTS as $newPermission => $alternatives) {
                $qualifies = collect($alternatives)
                    ->contains(fn (array $required) => array_diff($required, $current) === []);

                if ($qualifies && ! in_array($newPermission, $current, true)) {
                    $rows[] = ['permission_id' => $permissionIds[$newPermission], 'role_id' => $roleId];
                }
            }
        }

        if ($rows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($rows);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys(self::NEW_PERMISSIONS))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
