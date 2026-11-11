<?php

namespace App\Support;

use App\Models\Survey;

class SurveyFields
{
    public const TYPES = [
        'text' => 'Short text', 'textarea' => 'Long text', 'email' => 'Email',
        'tel' => 'Phone', 'number' => 'Number', 'select' => 'Dropdown',
        'radio' => 'Single choice', 'multiselect' => 'Multiple choice',
        'checkbox' => 'Checkbox', 'rating' => 'Star rating (1-5)',
        'boolean' => 'Yes / No', 'guests' => 'Suggested guests',
    ];

    public static function defaultContact(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nombre completo', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Correo electrónico', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Celular', 'type' => 'tel', 'required' => false],
        ];
    }

    public static function legacyQuestions(Survey $survey): array
    {
        $fields = [
            ['name' => 'experience_rating', 'label' => $survey->question('q1_label'), 'type' => 'rating', 'required' => true],
            ['name' => 'use_case', 'label' => $survey->question('q2_label'), 'type' => 'textarea'],
            ['name' => 'stage', 'label' => $survey->question('q3_label'), 'type' => 'radio', 'options' => $survey->stageOptions()],
            ['name' => 'wants_review', 'label' => $survey->question('q4_label'), 'type' => 'boolean', 'required' => true,
                'options' => [$survey->copy('yes_label'), $survey->copy('no_label')]],
        ];
        if ($survey->guests_count > 0) {
            $fields[] = ['name' => 'guests', 'label' => $survey->question('q5_label'), 'type' => 'guests', 'guests_count' => min(10, $survey->guests_count)];
        }
        foreach ($survey->extraQuestions() as $field) {
            $field['name'] = 'extra_'.$field['name'];
            $fields[] = $field;
        }

        return $fields;
    }
}
