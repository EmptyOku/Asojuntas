<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserRole;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class UserRoleController extends Controller
{
    /**
     * Bitácora de Accesos: Lista el historial de qué roles se le dieron a qué usuarios.
     */
    public function index(Request $request): View
    {
        // Auditoría: Eager Loading profundo para resolver los nombres reales.
        $query = UserRole::with(['user.person', 'role', 'assignedByUser.person']);

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Ordenamos por los más recientes para ver las últimas concesiones de poder
        $assignments = $query->latest('assigned_at')->paginate(30)->withQueryString();
        $roles = Role::orderBy('display_name')->get();

        return view('admin.user_roles.index', compact('assignments', 'roles'));
    }

    /**
     * Revocación Quirúrgica: Quita un rol específico a un usuario.
     */
    public function destroy(UserRole $userRole): RedirectResponse
    {
        // Escudo de Auto-Bloqueo: Un administrador no puede quitarse su propio rol
        // desde esta pantalla rápida para evitar dejar el sistema sin administradores.
        if ($userRole->user_id === Auth::id()) {
            return back()->with('error', 'Seguridad de Sistema: Operación bloqueada. No puede revocar sus propios roles de acceso. Si necesita cambiar su perfil, pida a otro administrador que lo haga.');
        }

        $userName = $userRole->user->username;
        $roleName = $userRole->role->display_name;

        // Eliminamos el privilegio
        $userRole->delete();

        return back()->with('success', "Auditoría: El rol de '{$roleName}' ha sido revocado para el usuario '{$userName}'.");
    }
}
