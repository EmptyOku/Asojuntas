<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * La cuenta se revisa en cada petición, no solo al iniciar sesión: si un
 * administrador la desactiva, la sesión que estuviera abierta deja de servir.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu cuenta fue desactivada. Comunícate con un administrador.',
                ], 401);
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
