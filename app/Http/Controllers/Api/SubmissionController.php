<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SubmissionResource;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SubmissionController extends ApiController
{
    #[OA\Get(
        path: '/api/v1/submissions',
        operationId: 'listSubmissions',
        summary: 'Lista los registros (submissions) de los webinars',
        description: 'Devuelve los registros capturados en las landings de webinars, con filtros por cliente, webinar, campaña de Salesforce, fechas y UTM.',
        tags: ['Submissions'],
        security: [['ApiKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'query', description: 'Slug o ID del cliente', required: false, schema: new OA\Schema(type: 'string', example: 'libertynet')),
            new OA\Parameter(name: 'webinar_id', in: 'query', description: 'ID del webinar', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'webinar_slug', in: 'query', description: 'Slug del webinar', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'campaign', in: 'query', description: 'Campaign Salesforce Field del webinar (campo `campaign`). Usado para enlazar con la campaña en Salesforce.', required: false, schema: new OA\Schema(type: 'string', example: '7013h000000abcAAA')),
            new OA\Parameter(name: 'from', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', description: 'Fecha final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'utm_source', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'utm_campaign', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Busca dentro del JSON `data` (nombre, email, etc.)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'registered_in_zoom', in: 'query', description: 'true = solo los registrados en Zoom, false = solo los no registrados', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Resultados por página (1-200, default 50)', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de submissions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Submission')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'API key faltante o inválida'),
            new OA\Response(response: 403, description: 'La API key no tiene acceso a ese cliente'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Submission::withoutGlobalScopes()
            ->with(['webinar.client'])
            ->latest('created_at');

        $this->applyWebinarFilters($request, $query);

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
            if ($value = $request->query($utm)) {
                $query->where($utm, $value);
            }
        }

        if ($search = $request->query('search')) {
            $query->where('data', 'like', '%'.$search.'%');
        }

        if ($request->filled('registered_in_zoom')) {
            $request->boolean('registered_in_zoom')
                ? $query->whereNotNull('registered_in_zoom_at')
                : $query->whereNull('registered_in_zoom_at');
        }

        return SubmissionResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    #[OA\Get(
        path: '/api/v1/submissions/{id}',
        operationId: 'showSubmission',
        summary: 'Detalle de un registro',
        tags: ['Submissions'],
        security: [['ApiKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Submission', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Submission')]
            )),
            new OA\Response(response: 404, description: 'No encontrado'),
        ],
    )]
    public function show(Request $request, int $id): SubmissionResource
    {
        $query = Submission::withoutGlobalScopes()->with(['webinar.client'])->whereKey($id);

        if ($clientId = $this->resolveClientId($request)) {
            $query->whereHas('webinar', fn (Builder $q) => $q->where('client_id', $clientId));
        }

        return new SubmissionResource($query->firstOrFail());
    }
}
