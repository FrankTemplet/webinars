<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'slug', 'logo', 'internal_email_domains', 'meta_ad_account_id', 'meta_access_token'];

    protected $casts = [
        'internal_email_domains' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope('id'));
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function webinars(): HasMany
    {
        return $this->hasMany(Webinar::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function socialMedia(): HasMany
    {
        return $this->hasMany(SocialMediaLink::class);
    }
}
