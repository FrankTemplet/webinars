<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'webinar_id',
        'data',
        'sessions',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'sent_to_clay_at',
        'registered_in_zoom_at',
        'zoom_registrations',
        'utms_reconstructed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sessions' => 'array',
        'zoom_registrations' => 'array',
        'sent_to_clay_at' => 'datetime',
        'registered_in_zoom_at' => 'datetime',
        'utms_reconstructed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope(null, 'webinar'));
    }

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }

    /**
     * Webinars de Zoom en los que debe quedar registrado: las sesiones que
     * eligió, o el único Zoom del webinar.
     *
     * @return array<int, string>
     */
    public function zoomWebinarIds(): array
    {
        $webinarIds = $this->webinar->zoomWebinarIds();

        if (! $this->webinar->hasSessions()) {
            return $webinarIds;
        }

        return array_values(array_intersect($webinarIds, $this->sessions ?? []));
    }

    /**
     * @return array<int, string>
     */
    public function pendingZoomWebinarIds(): array
    {
        // Antes de las sesiones solo se guardaba la fecha, no en qué webinar.
        if ($this->registered_in_zoom_at && empty($this->zoom_registrations)) {
            return [];
        }

        return array_values(array_diff($this->zoomWebinarIds(), array_keys($this->zoom_registrations ?? [])));
    }
}
