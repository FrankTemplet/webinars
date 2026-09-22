<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'webinar_id',
        'data',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'sent_to_clay_at',
        'registered_in_zoom_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_to_clay_at' => 'datetime',
        'registered_in_zoom_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope(null, 'webinar'));
    }

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }
}
