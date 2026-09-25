<?php

namespace App\Http\Middleware;

use App\Services\ElectoralAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPermission
{
    public function __construct(private readonly ElectoralAccessGuard $guard) {}

    /**
     * Acepta uno o varios permisos (`api.permission:a,b`): basta con tener cualquiera.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        if (! $this->guard->hasAnyPermission($user, $permissions)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado para esta acción',
                'required_permission' => $permissions[0] ?? null,
                'required_permissions' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}
