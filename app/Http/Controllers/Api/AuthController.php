<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrailLogger;
use App\Services\ElectoralAccessGuard;
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
        $user = User::with(['roles', 'person.neighborhood.commune']) // mismo usuario que /user: el SPA no debe depender de recargar
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

        // Evita fijación de sesión: el identificador previo al login deja de ser válido.
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        app(AuditTrailLogger::class)->recordSystemEvent('login', [
            'identity' => $identity,
            'user_id' => $user->id,
        ]);

        $permissions = $this->permissionsFor($user);

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $user->createToken('api-login')->plainTextToken,
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

        $user = User::with(['roles', 'person.neighborhood.commune'])->find(Auth::id());
        $permissions = $this->permissionsFor($user);

        return response()->json([
            'success' => true,
            'user' => $user,
            'roles' => $user->roles->pluck('name')->values(),
            'permissions' => $permissions,
        ], 200);
    }

    /**
     * Mismo cálculo que usa el middleware api.permission (roles + permisos
     * directos), para que el menú del SPA y el backend nunca discrepen.
     */
    private function permissionsFor(User $user)
    {
        return app(ElectoralAccessGuard::class)->permissionsFor($user);
    }

    /**
     * Logout endpoint
     */
    public function logout(Request $request)
    {
        $accessToken = $request->user()?->currentAccessToken();

        app(AuditTrailLogger::class)->recordSystemEvent('logout', [
            'user_id' => Auth::id(),
        ]);

        Auth::logout();
        $accessToken?->delete();
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
