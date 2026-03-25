<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;

class PermissionController extends Controller
{
    /**
     * Lista todos los permisos definidos en el sistema.
     */
    public function index(Request $request): View
    {
        // Auditoría: withCount para saber cuántos roles tienen este permiso
        // sin cargar todos los roles a memoria.
        $query = Permission::withCount('roles');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('display_name', 'ilike', "%{$request->search}%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $permissions = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.permissions.index', compact('permissions'));
    }

    /**
     * Formulario para crear un nuevo permiso técnico.
     */
    public function create(): View
    {
        return view('admin.permissions.create');
    }

    /**
     * Almacena el permiso con validación de nombre técnico (slug).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // El 'name' es el slug técnico (ej: 'edit-votes')
            'name'         => 'required|string|max:100|unique:permissions,name',
            'display_name' => 'required|string|max:150',
            'description'  => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Permission::create($validated);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Nuevo permiso de sistema registrado.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Permission $permission): View
    {
        return view('admin.permissions.edit', compact('permission'));
    }

    /**
     * Actualiza el permiso protegiendo el nombre único.
     */
    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100|unique:permissions,name,' . $permission->id,
            'display_name' => 'required|string|max:150',
            'description'  => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $permission->update($validated);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permiso actualizado correctamente.');
    }

    /**
     * Intenta eliminar el permiso (Acción crítica).
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        // Capa Preventiva: Si el permiso está asignado a roles, NO se puede borrar.
        if ($permission->roles()->exists()) {
            return back()->with('error', 'Auditoría: Bloqueo de seguridad. Este permiso está asignado a ' . $permission->roles_count . ' roles activos. Desactívelo en lugar de borrarlo.');
        }

        try {
            $permission->delete();
            return redirect()->route('admin.permissions.index')
                ->with('success', 'Permiso eliminado del sistema.');

        } catch (QueryException $e) {
            return back()->with('error', 'Error técnico: Integridad de datos activa en la base de datos.');
        }
    }
}
