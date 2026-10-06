<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso para descargar la copia de seguridad de la base de datos desde el
 * Panel. La copia lleva datos personales y contraseñas cifradas, así que solo
 * lo recibe Super Admin; a otro rol hay que dárselo a propósito.
 */
return new class extends Migration
{
    private const NAME = 'database.backup';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->upsert(
            [[
                'name' => self::NAME,
                'guard_name' => 'web',
                'display_name' => 'Descargar copia de seguridad',
                'description' => 'Descargar una copia completa de la base de datos',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name', 'guard_name'],
            ['display_name', 'description', 'is_active', 'updated_at'],
        );

        $permissionId = DB::table('permissions')->where('name', self::NAME)->where('guard_name', 'web')->value('id');
        $superAdminId = DB::table('roles')->where('name', 'super_admin')->value('id');

        if ($superAdminId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $superAdminId, 'permission_id' => $permissionId]);

            if (Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $superAdminId,
                    'permission_id' => $permissionId,
                    'assigned_at' => $now,
                    'assigned_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::NAME)->value('id');
        if (! $permissionId) {
            return;
        }

        foreach (['role_has_permissions', 'model_has_permissions', 'role_permissions'] as $pivot) {
            if (Schema::hasTable($pivot)) {
                DB::table($pivot)->where('permission_id', $permissionId)->delete();
            }
        }
        DB::table('permissions')->where('id', $permissionId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
