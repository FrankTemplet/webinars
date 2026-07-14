<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webinar extends Model
{
    protected $fillable = [
        'client_id', 'title', 'subtitle', 'description', 'slug', 'zoom_webinar_id', 'hero_image', 'header_logo',
        'form_schema', 'chartable_fields', 'tracking_scripts', 'meta_title', 'meta_description', 'clay_webhook_url',
        'meta_campaign_id', 'campaign', 'ad_spend', 'last_ad_spend_sync_at',
        'thank_you_enabled', 'thank_you_title', 'thank_you_message', 'thank_you_image',
        'thank_you_cta_text', 'thank_you_cta_url',
    ];

    protected $casts = [
        'thank_you_enabled' => 'boolean',
        'form_schema' => 'array',
        'chartable_fields' => 'array',
        'tracking_scripts' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
