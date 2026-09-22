<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Templet Webinars API',
    description: 'API de solo lectura para consultar submissions, asistentes y estadísticas de los webinars. Autenticación por API key en el header `X-Api-Key`.',
)]
#[OA\Server(url: '/', description: 'Servidor actual')]
#[OA\SecurityScheme(
    securityScheme: 'ApiKeyAuth',
    type: 'apiKey',
    name: 'X-Api-Key',
    in: 'header',
    description: 'API key generada con `php artisan api-key:create`.',
)]
abstract class ApiController extends Controller
{
    /**
     * Cliente al que está restringida la API key en uso, si aplica.
     */
    protected function apiKeyClientId(Request $request): ?int
    {
        $apiKey = $request->attributes->get('api_key');

        return $apiKey instanceof ApiKey ? $apiKey->client_id : null;
    }

    /**
     * Resuelve el filtro de cliente: el parámetro `client` (slug o id) acotado
     * siempre por el cliente de la API key. Devuelve null si no hay filtro.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function resolveClientId(Request $request): ?int
    {
        $keyClientId = $this->apiKeyClientId($request);
        $param = $request->query('client');

        if (! $param) {
            return $keyClientId;
        }

        $client = Client::withoutGlobalScopes()
            ->where(fn (Builder $q) => $q->where('slug', $param)->orWhere('id', $param))
            ->first();

        abort_if(! $client, 404, "Cliente '{$param}' no encontrado.");

        // Una llave restringida no puede consultar otros clientes.
        abort_if(
            $keyClientId !== null && $keyClientId !== $client->id,
            403,
            'Esta API key no tiene acceso a ese cliente.'
        );

        return $client->id;
    }

    /**
     * Filtros compartidos que acotan por cliente / webinar / campaña.
     */
    protected function applyWebinarFilters(Request $request, Builder $query): void
    {
        $clientId = $this->resolveClientId($request);
        $webinarId = $request->query('webinar_id');
        $webinarSlug = $request->query('webinar_slug');
        $campaign = $request->query('campaign');

        if ($clientId || $webinarSlug || $campaign) {
            $query->whereHas('webinar', function (Builder $q) use ($clientId, $webinarSlug, $campaign) {
                $q->withoutGlobalScopes();

                if ($clientId) {
                    $q->where('client_id', $clientId);
                }

                if ($webinarSlug) {
                    $q->where('slug', $webinarSlug);
                }

                if ($campaign) {
                    $q->where('campaign', $campaign);
                }
            });
        }

        if ($webinarId) {
            $query->where('webinar_id', $webinarId);
        }
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 50), 1), 200);
    }
}
