<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Survey;
use App\Models\Webinar;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Renderiza las páginas públicas para el iframe de preview del panel.
 *
 * Nunca escribe nada: el registro puede no existir todavía (alta nueva) y
 * los cambios del formulario llegan al iframe por postMessage, no por aquí.
 */
class PagePreviewController extends Controller
{
    public function webinar(Request $request, ?Webinar $webinar = null)
    {
        $client = $this->resolveClient($request, $webinar?->client);
        $webinar ??= $this->sampleWebinar();
        $webinar->setRelation('client', $client);

        return Inertia::render('Webinar/Show', [
            'client' => $client->toPublicArray(),
            'webinar' => $webinar->toPublicArray(forEditor: true),
            'submitUrl' => '',
            'preview' => true,
        ]);
    }

    public function survey(Request $request, ?Survey $survey = null)
    {
        $client = $this->resolveClient($request, $survey?->client);
        $survey ??= $this->sampleSurvey();
        $survey->setRelation('client', $client);

        return Inertia::render('Survey/Show', [
            'client' => $client->toPublicArray(),
            'survey' => $survey->toPublicArray(forEditor: true),
            'submitUrl' => '',
            'preview' => true,
        ]);
    }

    /**
     * Página de ejemplo para ver el estilo de un cliente sin abrir ninguno
     * de sus webinars.
     */
    public function client(Client $client)
    {
        $webinar = $this->sampleWebinar();
        $webinar->setRelation('client', $client);

        return Inertia::render('Webinar/Show', [
            'client' => $client->toPublicArray(),
            'webinar' => $webinar->toPublicArray(forEditor: true),
            'submitUrl' => '',
            'preview' => true,
        ]);
    }

    /**
     * El cliente sale del registro cuando existe y del formulario cuando se
     * está dando de alta. Sin cliente elegido se usa uno vacío, para que el
     * preview muestre la plantilla por defecto en vez de un error.
     */
    private function resolveClient(Request $request, ?Client $client): Client
    {
        if ($client) {
            return $client;
        }

        if ($id = $request->integer('client')) {
            return Client::find($id) ?? new Client(['name' => 'Cliente', 'slug' => 'cliente']);
        }

        return new Client(['name' => 'Cliente', 'slug' => 'cliente']);
    }

    private function sampleWebinar(): Webinar
    {
        return new Webinar([
            'title' => 'Título del webinar',
            'show_title' => true,
            'subtitle' => 'Subtítulo de la sesión',
            'description' => '<p>Aquí va la descripción del webinar. Escribe arriba para verla cambiar.</p>',
            'form_schema' => [
                ['type' => 'text', 'name' => 'first_name', 'label' => 'Nombre', 'required' => true],
                ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                ['type' => 'text', 'name' => 'company', 'label' => 'Compañía', 'required' => false],
            ],
        ]);
    }

    private function sampleSurvey(): Survey
    {
        return new Survey([
            'intro' => '<h2>Tu opinión es muy importante para nosotros</h2><p>Escribe arriba para ver cómo queda la encuesta.</p>',
            'is_open' => true,
            'contact_enabled' => true,
            'guests_count' => 1,
        ]);
    }
}
