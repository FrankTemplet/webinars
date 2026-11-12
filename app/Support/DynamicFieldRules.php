<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DynamicFieldRules
{
    public static function forField(array $field, string $key): array
    {
        // TrimStrings recorta lo que se envía; las opciones se comparan igual de recortadas.
        $options = array_map(fn ($option) => Str::trim((string) $option), $field['options'] ?? []);
        $rules = [($field['required'] ?? false) ? 'required' : 'nullable'];
        $type = $field['type'];
        $rules[] = match ($type) {
            'multiselect', 'guests' => 'array',
            'checkbox', 'boolean' => 'boolean',
            'rating' => 'integer',
            'number' => 'numeric',
            default => 'string',
        };
        if ($type === 'rating') {
            $rules[] = 'between:1,5';
        }
        if ($type === 'email') {
            $rules[] = 'email';
        }
        if (in_array($type, ['text', 'email', 'tel'])) {
            $rules[] = 'max:255';
        }
        if ($type === 'textarea') {
            $rules[] = 'max:5000';
        }
        if (in_array($type, ['select', 'radio'])) {
            $rules[] = Rule::in($options);
        }
        $result = [$key => $rules];
        if ($type === 'multiselect') {
            $result[$key][] = 'max:'.count($options);
            if ($field['required'] ?? false) {
                $result[$key][] = 'min:1';
            }
            $result[$key.'.*'] = ['string', 'distinct', Rule::in($options)];
        }
        if ($type === 'guests') {
            $result[$key][] = 'max:'.max(1, min(10, $field['guests_count'] ?? 3));
            $required = ($field['required'] ?? false) ? 'required' : 'nullable';
            $result[$key.'.*.name'] = [$required, 'string', 'max:255'];
            $result[$key.'.*.email'] = [$required, 'email', 'max:255'];
            $result[$key.'.*.topics'] = ['nullable', 'string', 'max:255'];
        }

        return $result;
    }
}
