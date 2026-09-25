<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // La lista sale de PermissionCatalog: es la única fuente de verdad de permisos.
        $rows = collect(PermissionCatalog::all())
            ->map(fn (array $meta, string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'display_name' => $meta['display_name'],
                'description' => $meta['description'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table('permissions')->upsert(
            $rows,
            ['name', 'guard_name'],
            ['display_name', 'description', 'is_active', 'updated_at']
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
