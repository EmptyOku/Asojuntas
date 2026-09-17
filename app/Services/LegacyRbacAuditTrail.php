<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Espeja en las tablas legacy `user_roles` / `role_permissions` (con columnas
 * assigned_at/assigned_by) lo que Spatie ya guardó en model_has_roles /
 * role_has_permissions, para que esas tablas sigan siendo una bitácora real
 * de quién asignó qué y cuándo, en vez de quedar congeladas en el último seed.
 */
class LegacyRbacAuditTrail
{
    public static function syncUserRoles(int $userId, iterable $roleIds, ?int $actorId): void
    {
        $roleIds = collect($roleIds)->unique()->values();
        $now = now();

        DB::transaction(function () use ($userId, $roleIds, $actorId, $now): void {
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->whereNotIn('role_id', $roleIds)
                ->delete();

            if ($roleIds->isEmpty()) {
                return;
            }

            DB::table('user_roles')->upsert(
                $roleIds->map(fn (int $roleId) => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned_at' => $now,
                    'assigned_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['user_id', 'role_id'],
                ['assigned_at', 'assigned_by', 'updated_at']
            );
        });
    }

    public static function syncRolePermissions(int $roleId, iterable $permissionIds, ?int $actorId): void
    {
        $permissionIds = collect($permissionIds)->unique()->values();
        $now = now();

        DB::transaction(function () use ($roleId, $permissionIds, $actorId, $now): void {
            DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->whereNotIn('permission_id', $permissionIds)
                ->delete();

            if ($permissionIds->isEmpty()) {
                return;
            }

            DB::table('role_permissions')->upsert(
                $permissionIds->map(fn (int $permissionId) => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'assigned_at' => $now,
                    'assigned_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['role_id', 'permission_id'],
                ['assigned_at', 'assigned_by', 'updated_at']
            );
        });
    }
}
