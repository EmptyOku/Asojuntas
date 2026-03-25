<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RolePermissionController extends Controller
{
    /**
     * Muestra el historial de asignaciones de permisos.
     * Útil para auditorías de seguridad.
     */
    public function index(Request $request): View
    {
        // Auditoría: Carga profunda para saber qué permiso se dio a qué rol y quién lo hizo.
        $query = RolePermission::with(['role', 'permission', 'assignedByUser.person']);

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        $assignments = $query->latest('assigned_at')->paginate(30);
        $roles = Role::orderBy('display_name')->get();

        return view('admin.role_permissions.index', compact('assignments', 'roles'));
    }

    /**
     * Permite revocar un permiso específico de forma individual.
     */
    public function destroy(RolePermission $rolePermission): RedirectResponse
    {
        // Regla de Negocio: No permitir que un administrador se quite a sí mismo
        // el permiso de gestionar seguridad si es el único. (Opcional pero recomendado)

        $roleName = $rolePermission->role->display_name;
        $permissionName = $rolePermission->permission->display_name;

        $rolePermission->delete();

        return back()->with('success', "Auditoría: El permiso '{$permissionName}' ha sido revocado del rol '{$roleName}'.");
    }
}
