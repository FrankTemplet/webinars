<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaLink extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'url'
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
