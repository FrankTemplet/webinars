<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AttendeeResource;
use App\Models\Attendee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AttendeeController extends ApiController
{
    #[OA\Get(
        path: '/api/v1/attendees',
        operationId: 'listAttendees',
        summary: 'Lista los asistentes reales (Zoom) de los webinars',
        description: 'Participantes sincronizados desde los reportes de Zoom mediante `php artisan webinars:sync-attendees`. Incluye hora de entrada/salida y duración.',
        tags: ['Attendees'],
        security: [['ApiKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'query', description: 'Slug o ID del cliente', required: false, schema: new OA\Schema(type: 'string', example: 'libertynet')),
            new OA\Parameter(name: 'webinar_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'webinar_slug', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'campaign', in: 'query', description: 'Campaign Salesforce Field del webinar', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from', in: 'query', description: 'join_time desde (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', description: 'join_time hasta (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'email', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'min_duration', in: 'query', description: 'Duración mínima en minutos', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado de asistentes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Attendee')),
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
        $query = Attendee::withoutGlobalScopes()
            ->with(['webinar.client'])
            ->orderByDesc('join_time');

        $this->applyWebinarFilters($request, $query);

        if ($from = $request->query('from')) {
            $query->whereDate('join_time', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('join_time', '<=', $to);
        }

        if ($email = $request->query('email')) {
            $query->where('email', $email);
        }

        if ($minDuration = $request->query('min_duration')) {
            $query->where('duration', '>=', (int) $minDuration * 60);
        }

        return AttendeeResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }
}
