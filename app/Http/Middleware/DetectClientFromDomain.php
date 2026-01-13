<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

class DetectClientFromDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        
        // Primero, intentar obtener el host original de un header personalizado
        // (usado cuando el .htaccess hace redirect en lugar de proxy)
        $originalHost = $request->header('X-Original-Host');
        if ($originalHost) {
            $host = $originalHost;
            Log::info("Using X-Original-Host header", ['host' => $host]);
        }
        
        // Si no hay header, intentar obtener de query string
        // (usado cuando .htaccess pasa ?original_host=escala.templet.io)
        if (!$originalHost && $request->has('original_host')) {
            $host = $request->get('original_host');
            Log::info("Using original_host query parameter", ['host' => $host]);
        }
        
        // Extraer subdominio (escala.templet.io → escala)
        $parts = explode('.', $host);
        $subdomain = $parts[0];
        
        // Ignorar subdominios comunes de la app principal
        $ignoredSubdomains = ['webinars', 'www', 'localhost'];
        
        if (!in_array($subdomain, $ignoredSubdomains)) {
            // Buscar cliente por slug
            $client = Client::where('slug', $subdomain)->first();
            
            if ($client) {
                // Inyectar cliente en el request para uso en controllers
                $request->attributes->set('client', $client);
                $request->merge(['client_slug' => $client->slug]);
                
                Log::info("Client detected from subdomain", [
                    'subdomain' => $subdomain,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'original_host' => $originalHost ?? 'N/A'
                ]);
            }
        }
        
        return $next($request);
    }
}
