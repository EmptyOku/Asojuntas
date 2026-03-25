<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    /**
     * Lista los roles con conteo de usuarios y permisos vinculados.
     */
    public function index(Request $request): View
    {
        // Auditoría: withCount para métricas rápidas de seguridad
        $query = Role::withCount(['users', 'permissions']);

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('display_name', 'ilike', "%{$request->search}%");
        }

        $roles = $query->orderBy('name')->paginate(15);

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Formulario de creación con listado de permisos disponibles.
     */
    public function create(): View
    {
        $permissions = Permission::where('is_active', true)->orderBy('display_name')->get();
        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Almacena el rol y sincroniza sus permisos en una transacción.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100|unique:roles,name',
            'display_name' => 'required|string|max:150',
            'permissions'  => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        DB::transaction(function () use ($validated, $request) {
            $role = Role::create($validated);

            if (!empty($validated['permissions'])) {
                // Sincronizamos los permisos añadiendo los datos de auditoría al pivote
                $role->permissions()->attach($validated['permissions'], [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id()
                ]);
            }
        });

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol creado y permisos asignados correctamente.');
    }

    /**
     * Muestra el detalle del rol y qué permisos tiene.
     */
    public function show(Role $role): View
    {
        $role->load(['permissions', 'users.person']);
        return view('admin.roles.show', compact('role'));
    }

    /**
     * Actualiza el rol y sus permisos (Sincronización).
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100|unique:roles,name,' . $role->id,
            'display_name' => 'required|string|max:150',
            'permissions'  => 'nullable|array',
        ]);

        $validated['is_active'] = $request->has('is_active');

        DB::transaction(function () use ($role, $validated) {
            $role->update($validated);

            // sync() elimina los que no estén en el array y añade los nuevos.
            // Para mantener la auditoría en los NUEVOS, usamos syncWithPivotValues o un loop.
            $pivotData = [];
            foreach ($validated['permissions'] ?? [] as $id) {
                $pivotData[$id] = [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id()
                ];
            }
            $role->permissions()->sync($pivotData);
        });

        return redirect()->route('admin.roles.index')->with('success', 'Rol actualizado.');
    }

    /**
     * Elimina el rol solo si no tiene usuarios asignados.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'Auditoría: No se puede eliminar un rol que tiene usuarios vinculados. Reasigne a los usuarios primero.');
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->detach(); // Limpiamos la tabla pivote primero
            $role->delete();
        });

        return redirect()->route('admin.roles.index')->with('success', 'Rol eliminado.');
    }
}
