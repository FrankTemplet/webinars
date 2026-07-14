<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Webinar;
use App\Models\Submission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebinarController extends Controller
{
    /**
     * Resuelve cliente y webinar desde el middleware (producción) o los
     * parámetros de ruta (desarrollo local). Devuelve [$client, $webinar].
     */
    private function resolveClientAndWebinar(Request $request, $clientSlug, $webinarSlug): array
    {
        // Si solo hay un parámetro, es el webinarSlug (ruta de producción)
        if ($webinarSlug === null) {
            $webinarSlug = $clientSlug;
            $clientSlug = null;
        }

        // Obtener cliente del middleware (inyectado en request attributes)
        $client = $request->attributes->get('client');

        // Si no hay cliente detectado, intentar obtener del parámetro de ruta (desarrollo local)
        if (!$client && $clientSlug) {
            $client = Client::with('socialMedia')->where('slug', $clientSlug)->firstOrFail();
        }

        // Si aún no hay cliente, error 404
        if (!$client) {
            abort(404, 'Cliente no encontrado. Verifica que el subdominio sea correcto o usa la ruta /client/{client}/webinars/{slug}');
        }

        // Cargar relaciones si no están cargadas
        if (!$client->relationLoaded('socialMedia')) {
            $client->load('socialMedia');
        }

        $webinar = Webinar::where('client_id', $client->id)
            ->where('slug', $webinarSlug)
            ->firstOrFail();

        return [$client, $webinar];
    }

    /**
     * Genera la URL de una ruta del webinar, usando la variante local
     * cuando el cliente no fue detectado por subdominio.
     */
    private function webinarRoute(Request $request, string $name, Client $client, Webinar $webinar): string
    {
        if ($request->attributes->get('client')) {
            return route($name, ['slug' => $webinar->slug]);
        }

        return route($name . '.local', ['client' => $client->slug, 'slug' => $webinar->slug]);
    }

    public function show(Request $request, $clientSlug = null, $webinarSlug = null)
    {
        [$client, $webinar] = $this->resolveClientAndWebinar($request, $clientSlug, $webinarSlug);

        return Inertia::render('Webinar/Show', [
            'client' => $client,
            'webinar' => $webinar,
        ]);
    }

    public function thankYou(Request $request, $clientSlug = null, $webinarSlug = null)
    {
        [$client, $webinar] = $this->resolveClientAndWebinar($request, $clientSlug, $webinarSlug);

        $webinarUrl = $this->webinarRoute($request, 'webinar.show', $client, $webinar);

        // Solo accesible tras registrarse; si no, de vuelta al webinar
        if (!$webinar->thank_you_enabled || !$request->session()->get("webinar_registered_{$webinar->id}")) {
            return redirect($webinarUrl);
        }

        return Inertia::render('Webinar/ThankYou', [
            'client' => $client,
            'webinar' => $webinar->only([
                'slug', 'title', 'meta_title', 'meta_description', 'header_logo',
                'thank_you_title', 'thank_you_message', 'thank_you_image',
                'thank_you_cta_text', 'thank_you_cta_url',
            ]),
            'webinarUrl' => $webinarUrl,
        ]);
    }

    public function store(Request $request, $clientSlug = null, $webinarSlug = null)
    {
        [$client, $webinar] = $this->resolveClientAndWebinar($request, $clientSlug, $webinarSlug);

        // Validate based on form_schema
        $rules = [];
        if ($webinar->form_schema) {
            foreach ($webinar->form_schema as $field) {
                $fieldRules = [];

                if ($field['required'] ?? false) {
                    $fieldRules[] = 'required';
                } else {
                    $fieldRules[] = 'nullable';
                }

                if (($field['type'] ?? '') === 'email') {
                    $fieldRules[] = 'email';
                }

                if (($field['type'] ?? '') === 'tel') {
                    // With intl-tel-input, we accept international formats.
                    // Just ensure it's a reasonable length.
                    $fieldRules[] = 'min:8';
                }

                if (!empty($fieldRules)) {
                    $rules[$field['name']] = $fieldRules;
                }
            }
        }

        $data = $request->validate($rules);

        $utmData = $request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);

        $submissionData = $request->except(['_token', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);

        // Clean phone number if present
        foreach ($submissionData as $key => $value) {
            if (str_contains(strtolower($key), 'phone') || str_contains(strtolower($key), 'tel')) {
                $submissionData[$key] = preg_replace('/[^\d+]/', '', $value);
            }
        }

        $submission = Submission::create(array_merge([
            'webinar_id' => $webinar->id,
            'data' => $submissionData,
        ], $utmData));

        // Register in Zoom if applicable
        if ($webinar->zoom_webinar_id) {
            app(\App\Services\ZoomService::class)->registerRegistrant(
                $webinar->zoom_webinar_id,
                $submissionData
            );
        }

        // Send Meta Conversions API event if configured
        $eventId = $request->input('event_id');
        if ($eventId && $webinar->tracking_scripts) {
            $metaService = app(\App\Services\MetaConversionsService::class);

            foreach ($webinar->tracking_scripts as $script) {
                if (
                    ($script['platform'] ?? '') === 'facebook' &&
                    ($script['enabled'] ?? false) &&
                    !empty($script['pixel_id']) &&
                    !empty($script['access_token'])
                ) {
                    $metaService->sendCompleteRegistration(
                        $script['pixel_id'],
                        $script['access_token'],
                        $eventId,
                        $submissionData,
                        $request->headers->get('referer'),
                        $request->ip(),
                        $request->userAgent()
                    );
                }
            }
        }

        // Send lead to Clay for enrichment if configured
        if (!empty($webinar->clay_webhook_url)) {
            $clayService = app(\App\Services\ClayService::class);
            $leadData = $clayService->prepareLeadData(
                $submissionData,
                $utmData,
                $webinar->title,
                $client->name
            );
            $sent = $clayService->sendLead($webinar->clay_webhook_url, $leadData);

            // Marcar como enviado a Clay si fue exitoso
            if ($sent) {
                $submission->update(['sent_to_clay_at' => now()]);
            }
        }

        if ($webinar->thank_you_enabled) {
            // Permite el acceso a la página de gracias solo tras registrarse
            $request->session()->put("webinar_registered_{$webinar->id}", true);

            return redirect($this->webinarRoute($request, 'webinar.thankyou', $client, $webinar));
        }

        return back()->with('success', 'Gracias! Sus datos han sido ingresados con éxito.');
    }
}
