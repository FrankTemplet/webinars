<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'data' => 'array',
        'sent_to_clay_at' => 'datetime',
    ];

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }
}
