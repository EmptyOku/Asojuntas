<?php

namespace App\Http\Middleware;

use App\Services\ElectoralAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPermission
{
    public function __construct(private readonly ElectoralAccessGuard $guard) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        if (! $this->guard->hasPermission($user, $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado para esta acción',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}