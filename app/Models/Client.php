<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'slug', 'logo', 'meta_ad_account_id', 'meta_access_token'];

    public function webinars(): HasMany
    {
        return $this->hasMany(Webinar::class);
    }

    public function socialMedia(): HasMany
    {
        return $this->hasMany(SocialMediaLink::class);
    }
}
