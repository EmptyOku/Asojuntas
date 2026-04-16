<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrailLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login endpoint
     */
    public function login(Request $request)
    {
        $request->validate([
            'identity' => 'required|string',
            'password' => 'required|string',
        ]);

        $identity = $request->string('identity')->toString();
        $user = User::with(['roles.permissions'])
            ->where('email', $identity)
            ->orWhere('username', $identity)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password) || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        Auth::login($user, false);

        app(AuditTrailLogger::class)->recordSystemEvent('login', [
            'identity' => $identity,
            'user_id' => $user->id,
        ]);

        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'user' => $user,
            'roles' => $user->roles->pluck('name')->values(),
            'permissions' => $permissions,
            'message' => 'Login exitoso'
        ], 200);

    }

    /**
     * Get current authenticated user
     */
    public function user(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        $user = User::with(['roles.permissions', 'person.neighborhood.commune'])->find(Auth::id());
        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'user' => $user,
            'roles' => $user->roles->pluck('name')->values(),
            'permissions' => $permissions,
        ], 200);
    }

    /**
     * Logout endpoint
     */
    public function logout(Request $request)
    {
        app(AuditTrailLogger::class)->recordSystemEvent('logout', [
            'user_id' => Auth::id(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso'
        ], 200);
    }

    /**
     * Check authentication status
     */
    public function check()
    {
        return response()->json([
            'authenticated' => Auth::check(),
            'user' => Auth::user()
        ], 200);
    }
}
