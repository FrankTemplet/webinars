<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id', 'name', 'email', 'phone',
        'experience_rating', 'use_case', 'stage', 'wants_review', 'guests', 'extra_answers',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    protected $casts = [
        'experience_rating' => 'integer',
        'wants_review' => 'boolean',
        'guests' => 'array',
        'extra_answers' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope(null, 'survey'));
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
