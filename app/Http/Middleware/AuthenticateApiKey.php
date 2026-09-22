<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica peticiones de la API pública por API key.
 *
 * Acepta el header `X-Api-Key: wbn_...` o `Authorization: Bearer wbn_...`.
 * La llave queda disponible en $request->attributes->get('api_key').
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->header('X-Api-Key') ?: $request->bearerToken();

        if (! $plain) {
            return response()->json([
                'message' => 'API key faltante. Usa el header X-Api-Key.',
            ], 401);
        }

        $apiKey = ApiKey::findByPlainKey($plain);

        if (! $apiKey) {
            return response()->json(['message' => 'API key inválida o revocada.'], 401);
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
