<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webinar extends Model
{
    protected $fillable = [
        'client_id', 'title', 'subtitle', 'description', 'slug', 'zoom_webinar_id', 'hero_image', 'header_logo',
        'form_schema', 'tracking_scripts', 'meta_title', 'meta_description',
    ];

    protected $casts = [
        'form_schema' => 'array',
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
