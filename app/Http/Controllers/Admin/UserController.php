<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Lista los usuarios del sistema con sus roles y estado.
     */
    public function index(Request $request): View
    {
        // Auditoría: Carga de la persona física y los roles asignados
        $query = User::with(['person.documentType', 'roles']);

        if ($request->filled('search')) {
            $query->where('username', 'ilike', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%")
                  ->orWhereHas('person', function($q) use ($request) {
                      $q->where('document_number', 'ilike', "%{$request->search}%")
                        ->orWhere('first_name', 'ilike', "%{$request->search}%")
                        ->orWhere('last_name', 'ilike', "%{$request->search}%");
                  });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        // Solo traemos personas que NO tengan un usuario ya creado y estén activas
        $people = Person::whereDoesntHave('user')->where('is_active', true)->orderBy('last_name')->get();
        $roles = Role::where('is_active', true)->orderBy('display_name')->get();

        return view('admin.users.create', compact('people', 'roles'));
    }

    /**
     * Almacena el usuario y le asigna roles dejando rastro de auditoría.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person_id' => 'required|exists:people,id|unique:users,person_id',
            'username'  => 'required|string|max:50|unique:users,username',
            'email'     => 'required|email|max:150|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'roles'     => 'required|array|min:1',
            'roles.*'   => 'exists:roles,id',
        ]);

        $validated['password']  = Hash::make($validated['password']);
        $validated['is_active'] = $request->has('is_active');

        DB::transaction(function () use ($validated, $request) {
            $user = User::create($validated);

            // Asignación de roles con registro de quién lo autorizó
            $pivotData = [];
            foreach ($validated['roles'] as $roleId) {
                $pivotData[$roleId] = [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id()
                ];
            }
            $user->roles()->sync($pivotData);
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Credenciales de acceso y roles configurados exitosamente.');
    }

    public function edit(User $user): View
    {
        $roles = Role::where('is_active', true)->orderBy('display_name')->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Actualiza el usuario. La contraseña es opcional.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        // Protección: Un usuario no puede desactivarse a sí mismo
        if ($user->id === Auth::id() && !$request->has('is_active')) {
            return back()->with('error', 'Auditoría: Bloqueo de seguridad. No puede desactivar su propia cuenta.');
        }

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username,' . $user->id,
            'email'    => 'required|email|max:150|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles'    => 'required|array|min:1',
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']); // Evitamos sobreescribir con null
        }

        $validated['is_active'] = $request->has('is_active');

        DB::transaction(function () use ($user, $validated) {
            $user->update($validated);

            $pivotData = [];
            foreach ($validated['roles'] as $roleId) {
                $pivotData[$roleId] = [
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id()
                ];
            }
            $user->roles()->sync($pivotData);
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Cuenta de usuario actualizada.');
    }

    /**
     * Intenta eliminar al usuario (Extremadamente restringido).
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Seguridad: No puede auto-eliminar su cuenta.');
        }

        // Auditoría Forense: Si el usuario tocó el escrutinio, es intocable.
        if (
            $user->createdScrutinyRecords()->exists() ||
            $user->reviewedScrutinyReviews()->exists() ||
            $user->consolidationRunsCreated()->exists() ||
            $user->auditLogs()->exists()
        ) {
            return back()->with('error', 'Auditoría: Bloqueo de Integridad. Este usuario ha registrado actas, realizado revisiones de IA o generado consolidaciones. Eliminarlo corrompería el rastro forense. Debe desactivarlo (is_active = false).');
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->delete();
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario eliminado del sistema.');
    }
}
