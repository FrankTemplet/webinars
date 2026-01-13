<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'slug', 'logo'];

    public function webinars(): HasMany
    {
        return $this->hasMany(Webinar::class);
    }

    public function socialMedia(): HasMany
    {
        return $this->hasMany(SocialMediaLink::class);
    }
}
