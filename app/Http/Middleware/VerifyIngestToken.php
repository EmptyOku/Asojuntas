<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIngestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('services.extractor.ingest_token', '');

        if ($configuredToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token de ingestión no configurado en el servidor.',
            ], 503);
        }

        $providedToken = (string) ($request->header('X-Ingest-Token') ?: $request->bearerToken());

        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Token de ingestión inválido.',
            ], 401);
        }

        return $next($request);
    }
}
