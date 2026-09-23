<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webinar' => $this->whenLoaded('webinar', fn () => [
                'id' => $this->webinar->id,
                'title' => $this->webinar->title,
                'slug' => $this->webinar->slug,
                'campaign_salesforce_field' => $this->webinar->campaign,
                'client' => $this->when($this->webinar->relationLoaded('client'), fn () => [
                    'id' => $this->webinar->client?->id,
                    'name' => $this->webinar->client?->name,
                    'slug' => $this->webinar->client?->slug,
                ]),
            ]),
            'name' => $this->name,
            'email' => $this->email,
            'join_time' => $this->join_time?->toIso8601String(),
            'leave_time' => $this->leave_time?->toIso8601String(),
            'duration' => $this->duration,
            'duration_minutes' => (int) round($this->duration / 60),
            'zoom_participant_id' => $this->zoom_participant_id,
        ];
    }
}
