<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendee;
use App\Models\Submission;
use App\Models\Webinar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StatsController extends ApiController
{
    /**
     * El nombre del campo de email varía entre formularios; se toma el primero
     * que exista dentro del JSON `data`.
     */
    private const EMAIL_EXPR = "COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(data, '\$.email')),
        JSON_UNQUOTE(JSON_EXTRACT(data, '\$.correo')),
        JSON_UNQUOTE(JSON_EXTRACT(data, '\$.Email')),
        JSON_UNQUOTE(JSON_EXTRACT(data, '\$.email_address'))
    )";

    #[OA\Get(
        path: '/api/v1/stats',
        operationId: 'getStats',
        summary: 'Estadísticas agregadas por webinar',
        description: 'Métricas por webinar: registros, leads pagados vs orgánicos, asistencia real de Zoom, show-up rate, inversión en Meta Ads y CPL. Incluye un bloque `totals` con el agregado del filtro aplicado.',
        tags: ['Stats'],
        security: [['ApiKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'query', description: 'Slug o ID del cliente', required: false, schema: new OA\Schema(type: 'string', example: 'libertynet')),
            new OA\Parameter(name: 'webinar_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'webinar_slug', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'campaign', in: 'query', description: 'Campaign Salesforce Field del webinar', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from', in: 'query', description: 'Fecha inicial (YYYY-MM-DD) aplicada a los registros', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', description: 'Fecha final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estadísticas por webinar más totales',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/WebinarStats')),
                        new OA\Property(property: 'totals', ref: '#/components/schemas/StatsTotals'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'API key faltante o inválida'),
            new OA\Response(response: 403, description: 'La API key no tiene acceso a ese cliente'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $webinars = $this->webinarQuery($request)->with('client')->get();

        $rows = $webinars->map(function (Webinar $webinar) use ($from, $to) {
            $submissions = Submission::withoutGlobalScopes()->where('webinar_id', $webinar->id);

            if ($from) {
                $submissions->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $submissions->whereDate('created_at', '<=', $to);
            }

            $registrations = (clone $submissions)->count();
            $uniqueEmails = (clone $submissions)
                ->selectRaw(self::EMAIL_EXPR.' as email_value')
                ->whereRaw(self::EMAIL_EXPR.' is not null and '.self::EMAIL_EXPR." <> ''")
                ->distinct()
                ->count(\Illuminate\Support\Facades\DB::raw(self::EMAIL_EXPR));
            $paidLeads = (clone $submissions)->where('utm_source', 'paid')->count();
            $organic = (clone $submissions)
                ->whereNull('utm_source')
                ->whereNull('utm_medium')
                ->whereNull('utm_campaign')
                ->whereNull('utm_term')
                ->whereNull('utm_content')
                ->count();
            $registeredInZoom = (clone $submissions)->whereNotNull('registered_in_zoom_at')->count();
            $sentToClay = (clone $submissions)->whereNotNull('sent_to_clay_at')->count();

            $attendees = Attendee::withoutGlobalScopes()->where('webinar_id', $webinar->id);
            $attendeesTotal = (clone $attendees)->count();
            $attendeesUnique = (clone $attendees)->distinct()->count('email');
            $avgDuration = (float) ((clone $attendees)->avg('duration') ?? 0);

            $adSpend = (float) ($webinar->ad_spend ?? 0);

            return [
                'webinar' => [
                    'id' => $webinar->id,
                    'title' => $webinar->title,
                    'slug' => $webinar->slug,
                    'campaign_salesforce_field' => $webinar->campaign,
                    'meta_campaign_id' => $webinar->meta_campaign_id,
                    'zoom_webinar_id' => $webinar->zoom_webinar_id,
                    'client' => [
                        'id' => $webinar->client?->id,
                        'name' => $webinar->client?->name,
                        'slug' => $webinar->client?->slug,
                    ],
                ],
                'registrations' => $registrations,
                'unique_registrations' => $uniqueEmails,
                'paid_leads' => $paidLeads,
                'organic_registrations' => $organic,
                'registered_in_zoom' => $registeredInZoom,
                'sent_to_clay' => $sentToClay,
                'attendees' => $attendeesTotal,
                'unique_attendees' => $attendeesUnique,
                'show_up_rate' => $registrations > 0 ? round($attendeesUnique / $registrations * 100, 2) : 0.0,
                'avg_attendance_minutes' => round($avgDuration / 60, 2),
                'ad_spend' => round($adSpend, 2),
                'cost_per_lead' => $registrations > 0 ? round($adSpend / $registrations, 2) : 0.0,
                'last_ad_spend_sync_at' => $webinar->last_ad_spend_sync_at?->toIso8601String(),
            ];
        })->values();

        $totalRegistrations = $rows->sum('registrations');
        $totalAttendees = $rows->sum('unique_attendees');
        $totalSpend = $rows->sum('ad_spend');

        return response()->json([
            'data' => $rows,
            'totals' => [
                'webinars' => $rows->count(),
                'registrations' => $totalRegistrations,
                'unique_registrations' => $rows->sum('unique_registrations'),
                'paid_leads' => $rows->sum('paid_leads'),
                'organic_registrations' => $rows->sum('organic_registrations'),
                'attendees' => $rows->sum('attendees'),
                'unique_attendees' => $totalAttendees,
                'show_up_rate' => $totalRegistrations > 0 ? round($totalAttendees / $totalRegistrations * 100, 2) : 0.0,
                'ad_spend' => round($totalSpend, 2),
                'cost_per_lead' => $totalRegistrations > 0 ? round($totalSpend / $totalRegistrations, 2) : 0.0,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/stats/utm',
        operationId: 'getUtmStats',
        summary: 'Desglose de registros por UTM',
        description: 'Cuenta de registros agrupada por el campo UTM elegido.',
        tags: ['Stats'],
        security: [['ApiKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'webinar_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'webinar_slug', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'campaign', in: 'query', description: 'Campaign Salesforce Field del webinar', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'field', in: 'query', description: 'Campo UTM a agrupar', required: false, schema: new OA\Schema(type: 'string', enum: ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'], default: 'utm_source')),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Desglose por UTM', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'field', type: 'string', example: 'utm_source'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'value', type: 'string', nullable: true, example: 'paid'),
                            new OA\Property(property: 'registrations', type: 'integer', example: 128),
                        ], type: 'object'
                    )),
                ]
            )),
        ],
    )]
    public function utm(Request $request): JsonResponse
    {
        $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $field = $request->query('field', 'utm_source');

        abort_if(! in_array($field, $allowed, true), 422, 'Campo UTM inválido.');

        $query = Submission::withoutGlobalScopes();
        $this->applyWebinarFilters($request, $query);

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $rows = $query->selectRaw("{$field} as value, count(*) as registrations")
            ->groupBy($field)
            ->orderByDesc('registrations')
            ->get()
            ->map(fn ($row) => [
                'value' => $row->value,
                'registrations' => (int) $row->registrations,
            ]);

        return response()->json(['field' => $field, 'data' => $rows]);
    }

    #[OA\Get(
        path: '/api/v1/stats/timeseries',
        operationId: 'getTimeseriesStats',
        summary: 'Serie temporal de registros',
        description: 'Registros agrupados por día, semana o mes.',
        tags: ['Stats'],
        security: [['ApiKeyAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'webinar_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'webinar_slug', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'campaign', in: 'query', description: 'Campaign Salesforce Field del webinar', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'group_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['day', 'week', 'month'], default: 'day')),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Serie temporal', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'group_by', type: 'string', example: 'day'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'period', type: 'string', example: '2026-09-22'),
                            new OA\Property(property: 'registrations', type: 'integer', example: 14),
                        ], type: 'object'
                    )),
                ]
            )),
        ],
    )]
    public function timeseries(Request $request): JsonResponse
    {
        $groupBy = $request->query('group_by', 'day');

        abort_if(! in_array($groupBy, ['day', 'week', 'month'], true), 422, 'group_by inválido.');

        $format = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%x-W%v',
            'month' => '%Y-%m',
        };

        $query = Submission::withoutGlobalScopes();
        $this->applyWebinarFilters($request, $query);

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $rows = $query->selectRaw("DATE_FORMAT(created_at, '{$format}') as period, count(*) as registrations")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'registrations' => (int) $row->registrations,
            ]);

        return response()->json(['group_by' => $groupBy, 'data' => $rows]);
    }

    /**
     * Webinars visibles según cliente / webinar / campaña.
     */
    protected function webinarQuery(Request $request): Builder
    {
        $query = Webinar::withoutGlobalScopes();

        if ($clientId = $this->resolveClientId($request)) {
            $query->where('client_id', $clientId);
        }

        if ($webinarId = $request->query('webinar_id')) {
            $query->where('id', $webinarId);
        }

        if ($webinarSlug = $request->query('webinar_slug')) {
            $query->where('slug', $webinarSlug);
        }

        if ($campaign = $request->query('campaign')) {
            $query->where('campaign', $campaign);
        }

        return $query;
    }
}
