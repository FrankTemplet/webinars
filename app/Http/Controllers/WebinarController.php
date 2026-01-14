<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Webinar;
use App\Models\Submission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebinarController extends Controller
{
    public function show(Request $request, $clientSlug = null, $webinarSlug = null)
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

        return Inertia::render('Webinar/Show', [
            'client' => $client,
            'webinar' => $webinar,
        ]);
    }

    public function store(Request $request, $clientSlug = null, $webinarSlug = null)
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
        
        $webinar = Webinar::where('client_id', $client->id)
            ->where('slug', $webinarSlug)
            ->firstOrFail();

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

        Submission::create(array_merge([
            'webinar_id' => $webinar->id,
            'data' => $submissionData,
        ], $utmData));

        return back()->with('success', 'Gracias! Sus datos han sido ingresados con éxito.');
    }
}
