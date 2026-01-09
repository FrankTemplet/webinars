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
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }
}
