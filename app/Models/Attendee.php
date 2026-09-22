<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendee extends Model
{
    protected $fillable = [
        'webinar_id',
        'zoom_participant_id',
        'zoom_user_id',
        'name',
        'email',
        'join_time',
        'leave_time',
        'duration',
        'device',
        'ip_address',
        'location',
        'raw',
    ];

    protected $casts = [
        'join_time' => 'datetime',
        'leave_time' => 'datetime',
        'duration' => 'integer',
        'raw' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope(null, 'webinar'));
    }

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }
}
