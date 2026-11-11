<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Support\DynamicFieldRules;
use Illuminate\Http\Request;

class SurveySubmission
{
    public function store(Request $request, Survey $survey): void
    {
        $questions = $survey->questionFields();
        $contact = $survey->contact_enabled
            ? array_values(array_filter($survey->contactFields(), fn ($field) => ! ($field['hidden'] ?? false))) : [];
        $rules = [];
        foreach (['answers' => $questions, 'contact' => $contact] as $group => $fields) {
            foreach ($fields as $field) {
                $rules = array_merge($rules, DynamicFieldRules::forField($field, $group.'.'.$field['name']));
            }
        }
        $validated = $request->validate($rules);
        $answers = $this->values($questions, $validated['answers'] ?? []);
        $contactAnswers = $this->values($contact, $validated['contact'] ?? []);
        $legacy = $this->legacyColumns($questions, $answers, $contact, $contactAnswers);
        SurveyResponse::create(array_merge($legacy, [
            'survey_id' => $survey->id,
            'answers' => $answers,
            'contact_answers' => $contactAnswers,
            'schema_snapshot' => ['questions' => $questions, 'contact' => $contact],
        ], $request->validate([
            'utm_source' => 'nullable|string|max:255', 'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255', 'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
        ])));
    }

    private function values(array $fields, array $data): array
    {
        $values = [];
        foreach ($fields as $field) {
            $value = $data[$field['name']] ?? null;
            if ($field['type'] === 'guests') {
                $value = collect($value ?? [])->map(fn ($guest) => [
                    'name' => trim($guest['name'] ?? ''), 'email' => trim($guest['email'] ?? ''),
                    'topics' => trim($guest['topics'] ?? ''),
                ])->filter(fn ($guest) => $guest['name'] !== '' || $guest['email'] !== '')->values()->all();
            }
            if ($field['type'] === 'tel' && is_string($value)) {
                $value = preg_replace('/[^\d+]/', '', $value);
            }
            $values[$field['name']] = $value;
        }

        return $values;
    }

    private function legacyColumns(array $questions, array $answers, array $contact, array $contactAnswers): array
    {
        $columns = ['name' => null, 'email' => null, 'phone' => null, 'wants_review' => null];
        $types = ['experience_rating' => 'rating', 'use_case' => 'textarea', 'stage' => 'radio', 'wants_review' => 'boolean', 'guests' => 'guests'];
        foreach ($questions as $field) {
            $name = $field['name'];
            if (($types[$name] ?? null) === $field['type'] && $answers[$name] !== null) {
                $columns[$name] = $answers[$name];
            }
        }
        foreach ($contact as $field) {
            $name = $field['name'];
            $expected = ['name' => 'text', 'email' => 'email', 'phone' => 'tel'];
            if (($expected[$name] ?? null) === $field['type']) {
                $columns[$name] = $contactAnswers[$name];
            }
        }

        return $columns;
    }
}
