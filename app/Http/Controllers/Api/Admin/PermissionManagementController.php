<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;

class PermissionManagementController extends Controller
{
    /**
     * Permisos activos, enriquecidos con los metadatos de PermissionCatalog
     * (módulo, si habilita una pantalla, dependencias y advertencias) y
     * ordenados como el catálogo. Los permisos se declaran en código: este
     * endpoint es de solo lectura.
     */
    public function index(): JsonResponse
    {
        $catalog = PermissionCatalog::all();
        $order = array_flip(array_keys($catalog));

        $permissions = Permission::where('is_active', true)
            ->get()
            ->map(function (Permission $permission) use ($catalog) {
                $meta = $catalog[$permission->name] ?? null;
                $module = $meta['module'] ?? (explode('.', $permission->name)[0] ?: 'general');

                return $permission->toArray() + [
                    'module' => $module,
                    'module_label' => $meta['module_label'] ?? ucfirst($module),
                    'screen' => $meta['screen'] ?? false,
                    'depends_on' => $meta['depends_on'] ?? [],
                    'scope_note' => $meta['scope_note'] ?? null,
                    'in_catalog' => $meta !== null,
                ];
            })
            ->sortBy(fn (array $permission) => [$order[$permission['name']] ?? PHP_INT_MAX, $permission['name']])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}
