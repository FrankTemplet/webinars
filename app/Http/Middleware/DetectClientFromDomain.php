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
                    'client_name' => $client->name
                ]);
            }
        }
        
        return $next($request);
    }
}
