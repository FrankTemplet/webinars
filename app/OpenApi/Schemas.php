<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Definiciones de los objetos que devuelve la API, solo para la documentación
 * Swagger. Esta clase no se instancia en runtime.
 */
#[OA\Schema(
    schema: 'Submission',
    title: 'Submission',
    description: 'Registro capturado en la landing de un webinar.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1421),
        new OA\Property(property: 'webinar', ref: '#/components/schemas/WebinarRef'),
        new OA\Property(
            property: 'data',
            type: 'object',
            description: 'Campos dinámicos definidos en el form_schema del webinar.',
            example: ['name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@empresa.com', 'phone' => '+525512345678', 'company' => 'Empresa SA'],
            additionalProperties: true,
        ),
        new OA\Property(property: 'utm', properties: [
            new OA\Property(property: 'source', type: 'string', nullable: true, example: 'paid'),
            new OA\Property(property: 'medium', type: 'string', nullable: true, example: 'facebook'),
            new OA\Property(property: 'campaign', type: 'string', nullable: true),
            new OA\Property(property: 'term', type: 'string', nullable: true),
            new OA\Property(property: 'content', type: 'string', nullable: true),
        ], type: 'object'),
        new OA\Property(property: 'sent_to_clay_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'registered_in_zoom_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Attendee',
    title: 'Attendee',
    description: 'Asistente real al webinar, sincronizado desde los reportes de Zoom.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 88),
        new OA\Property(property: 'webinar', ref: '#/components/schemas/WebinarRef'),
        new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Ana Pérez'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'ana@empresa.com'),
        new OA\Property(property: 'join_time', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'leave_time', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'duration', type: 'integer', description: 'Duración en segundos', example: 2700),
        new OA\Property(property: 'duration_minutes', type: 'integer', example: 45),
        new OA\Property(property: 'zoom_participant_id', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'WebinarRef',
    title: 'WebinarRef',
    description: 'Referencia al webinar y su cliente.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'title', type: 'string', example: 'LibertyNet SASE'),
        new OA\Property(property: 'slug', type: 'string', example: 'libertynet-sase'),
        new OA\Property(
            property: 'campaign_salesforce_field',
            type: 'string',
            nullable: true,
            description: 'Campo `campaign` del webinar. Es el identificador con el que se enlaza la campaña en Salesforce.',
            example: '7013h000000abcAAA',
        ),
        new OA\Property(property: 'client', ref: '#/components/schemas/ClientRef'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ClientRef',
    title: 'ClientRef',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'name', type: 'string', example: 'LibertyNet'),
        new OA\Property(property: 'slug', type: 'string', example: 'libertynet'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'WebinarStats',
    title: 'WebinarStats',
    description: 'Métricas agregadas de un webinar.',
    properties: [
        new OA\Property(property: 'webinar', ref: '#/components/schemas/WebinarRef'),
        new OA\Property(property: 'registrations', type: 'integer', description: 'Total de submissions', example: 320),
        new OA\Property(property: 'unique_registrations', type: 'integer', description: 'Correos distintos, internos incluidos', example: 759),
        new OA\Property(property: 'unique_registrations_external', type: 'integer', description: 'Correos distintos excluyendo dominios internos. Es el denominador de show_up_rate y cost_per_lead.', example: 707),
        new OA\Property(property: 'unique_registrations_internal', type: 'integer', description: 'Correos distintos de nuestra gente y la del cliente', example: 52),
        new OA\Property(property: 'paid_leads', type: 'integer', description: 'Submissions con utm_source = paid', example: 180),
        new OA\Property(property: 'organic_registrations', type: 'integer', description: 'Submissions sin ningún UTM', example: 118),
        new OA\Property(property: 'registered_in_zoom', type: 'integer', example: 310),
        new OA\Property(property: 'sent_to_clay', type: 'integer', example: 290),
        new OA\Property(property: 'attendees', type: 'integer', description: 'Sesiones de asistencia registradas en Zoom', example: 142),
        new OA\Property(property: 'unique_attendees', type: 'integer', description: 'Correos distintos, internos incluidos', example: 225),
        new OA\Property(property: 'unique_attendees_external', type: 'integer', description: 'Audiencia real: excluye organizadores y ponentes', example: 187),
        new OA\Property(property: 'unique_attendees_internal', type: 'integer', description: 'Organizadores, ponentes y gente del cliente que se conectó', example: 38),
        new OA\Property(property: 'show_up_rate', type: 'number', format: 'float', description: 'unique_attendees_external / unique_registrations_external * 100. Se calcula sobre externos a propósito: incluir organizadores infla la audiencia.', example: 26.45),
        new OA\Property(property: 'avg_attendance_minutes', type: 'number', format: 'float', example: 38.5),
        new OA\Property(property: 'ad_spend', type: 'number', format: 'float', description: 'Inversión sincronizada desde Meta Ads', example: 1250.40),
        new OA\Property(property: 'cost_per_lead', type: 'number', format: 'float', description: 'ad_spend / unique_registrations_external', example: 3.91),
        new OA\Property(property: 'last_ad_spend_sync_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'internal_domains', type: 'array', items: new OA\Items(type: 'string'), description: 'Patrones tratados como internos para este webinar', example: ['templet', 'cwc', 'libertynet.com']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StatsTotals',
    title: 'StatsTotals',
    description: 'Agregado de todos los webinars que cumplen el filtro.',
    properties: [
        new OA\Property(property: 'webinars', type: 'integer', example: 4),
        new OA\Property(property: 'registrations', type: 'integer', example: 1240),
        new OA\Property(property: 'unique_registrations', type: 'integer', example: 1180),
        new OA\Property(property: 'unique_registrations_external', type: 'integer', example: 1120),
        new OA\Property(property: 'unique_registrations_internal', type: 'integer', example: 60),
        new OA\Property(property: 'paid_leads', type: 'integer', example: 640),
        new OA\Property(property: 'organic_registrations', type: 'integer', example: 500),
        new OA\Property(property: 'attendees', type: 'integer', example: 520),
        new OA\Property(property: 'unique_attendees', type: 'integer', example: 505),
        new OA\Property(property: 'unique_attendees_external', type: 'integer', example: 462),
        new OA\Property(property: 'unique_attendees_internal', type: 'integer', example: 43),
        new OA\Property(property: 'show_up_rate', type: 'number', format: 'float', description: 'Calculado sobre externos', example: 41.25),
        new OA\Property(property: 'ad_spend', type: 'number', format: 'float', example: 5400.00),
        new OA\Property(property: 'cost_per_lead', type: 'number', format: 'float', example: 4.35),
    ],
    type: 'object',
)]
final class Schemas {}
