<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SurveyController extends Controller
{
    /**
     * Resuelve cliente y encuesta desde el middleware (producción) o los
     * parámetros de ruta (desarrollo local). Devuelve [$client, $survey].
     */
    private function resolveClientAndSurvey(Request $request, $clientSlug, $surveySlug): array
    {
        // Si solo hay un parámetro, es el surveySlug (ruta de producción)
        if ($surveySlug === null) {
            $surveySlug = $clientSlug;
            $clientSlug = null;
        }

        $client = $request->attributes->get('client');

        if (! $client && $clientSlug) {
            $client = Client::with('socialMedia')->where('slug', $clientSlug)->firstOrFail();
        }

        if (! $client) {
            abort(404, 'Cliente no encontrado. Verifica que el subdominio sea correcto o usa la ruta /{client}/surveys/{slug}');
        }

        if (! $client->relationLoaded('socialMedia')) {
            $client->load('socialMedia');
        }

        $survey = Survey::where('client_id', $client->id)
            ->where('slug', $surveySlug)
            ->firstOrFail();

        return [$client, $survey];
    }

    private function surveyRoute(Request $request, string $name, Client $client, Survey $survey): string
    {
        if ($request->attributes->get('client')) {
            return route($name, ['slug' => $survey->slug]);
        }

        return route($name.'.local', ['client' => $client->slug, 'slug' => $survey->slug]);
    }

    public function show(Request $request, $clientSlug = null, $surveySlug = null)
    {
        [$client, $survey] = $this->resolveClientAndSurvey($request, $clientSlug, $surveySlug);

        return Inertia::render('Survey/Show', [
            'client' => $client,
            'survey' => $survey->toPublicArray(),
            'submitUrl' => $this->surveyRoute($request, 'survey.store', $client, $survey),
        ]);
    }

    public function store(Request $request, $clientSlug = null, $surveySlug = null)
    {
        [$client, $survey] = $this->resolveClientAndSurvey($request, $clientSlug, $surveySlug);

        if (! $survey->is_open) {
            return back()->withErrors(['survey' => 'Esta encuesta ya no acepta respuestas.']);
        }

        // Las cinco preguntas de la plantilla son fijas; el bloque de contacto
        // y las preguntas adicionales dependen de la configuración.
        $rules = [
            'experience_rating' => ['required', 'integer', 'between:1,5'],
            'use_case' => ['nullable', 'string', 'max:5000'],
            'stage' => ['nullable', 'string', Rule::in($survey->stageOptions())],
            'wants_review' => ['required', 'boolean'],
            'guests' => ['nullable', 'array', 'max:10'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.topics' => ['nullable', 'string', 'max:255'],
        ];

        if ($survey->contact_enabled) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'min:8', 'max:30'];
        }

        $extraQuestions = $survey->extraQuestions();

        foreach ($extraQuestions as $question) {
            $key = "extra.{$question['name']}";
            $fieldRules = [$question['required'] ? 'required' : 'nullable'];

            $fieldRules[] = match ($question['type']) {
                'number' => 'numeric',
                'rating' => 'integer',
                'checkbox' => 'boolean',
                default => 'string',
            };

            if ($question['type'] === 'rating') {
                $fieldRules[] = 'between:1,5';
            }

            if (in_array($question['type'], ['select', 'radio'], true) && ! empty($question['options'])) {
                $fieldRules[] = Rule::in($question['options']);
            }

            if (in_array($question['type'], ['text', 'textarea'], true)) {
                $fieldRules[] = $question['type'] === 'textarea' ? 'max:5000' : 'max:255';
            }

            $rules[$key] = $fieldRules;
        }

        $data = $request->validate($rules);

        // Se descartan las filas de invitados que quedaron vacías.
        $guests = collect($data['guests'] ?? [])
            ->map(fn ($guest) => [
                'name' => trim($guest['name'] ?? ''),
                'email' => trim($guest['email'] ?? ''),
                'topics' => trim($guest['topics'] ?? ''),
            ])
            ->filter(fn ($guest) => $guest['name'] !== '' || $guest['email'] !== '')
            ->values()
            ->all();

        // Solo se guardan las respuestas de preguntas que siguen configuradas.
        $extraAnswers = collect($extraQuestions)
            ->mapWithKeys(fn ($question) => [
                $question['name'] => $data['extra'][$question['name']] ?? null,
            ])
            ->all();

        SurveyResponse::create(array_merge(
            Arr::except($data, ['extra']),
            [
                'survey_id' => $survey->id,
                'name' => $survey->contact_enabled ? $data['name'] : null,
                'email' => $survey->contact_enabled ? $data['email'] : null,
                'phone' => ! empty($data['phone']) ? preg_replace('/[^\d+]/', '', $data['phone']) : null,
                'guests' => $guests,
                'extra_answers' => $extraAnswers,
            ],
            $request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']),
        ));

        return back()->with('success', 'Gracias! Tu respuesta fue enviada correctamente.');
    }
}
