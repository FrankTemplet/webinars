<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id', 'name', 'email', 'phone', 'answers', 'contact_answers', 'schema_snapshot',
        'experience_rating', 'use_case', 'stage', 'wants_review', 'guests', 'extra_answers',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    protected $casts = [
        'answers' => 'array',
        'contact_answers' => 'array',
        'schema_snapshot' => 'array',
        'experience_rating' => 'integer',
        'wants_review' => 'boolean',
        'guests' => 'array',
        'extra_answers' => 'array',
    ];

    public function legacyDynamicData(Survey $survey): array
    {
        $answers = $this->only(['experience_rating', 'use_case', 'stage', 'wants_review', 'guests']);
        foreach ($this->extra_answers ?? [] as $name => $value) {
            $answers['extra_'.$name] = $value;
        }

        return [
            'answers' => $answers,
            'contact_answers' => $this->only(['name', 'email', 'phone']),
            'schema_snapshot' => ['questions' => $survey->questionFields(), 'contact' => $survey->contactFields()],
        ];
    }

    public function answerEntries(): array
    {
        $data = $this->schema_snapshot !== null ? $this->only(['schema_snapshot', 'answers', 'contact_answers']) : $this->legacyDynamicData($this->survey);
        $entries = [];
        foreach (['contact' => 'contact_answers', 'questions' => 'answers'] as $group => $column) {
            foreach ($data['schema_snapshot'][$group] ?? [] as $field) {
                $entries[] = ['name' => $group.'.'.$field['name'], 'label' => $field['label'], 'value' => self::displayAnswer($data[$column][$field['name']] ?? null)];
            }
        }

        return $entries;
    }

    public static function displayAnswer(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => is_array($item) ? implode(' / ', $item) : (string) $item, $value));
        }

        return (string) $value;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope(null, 'survey'));
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
