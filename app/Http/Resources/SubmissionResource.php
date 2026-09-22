<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
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
            'data' => $this->data,
            'utm' => [
                'source' => $this->utm_source,
                'medium' => $this->utm_medium,
                'campaign' => $this->utm_campaign,
                'term' => $this->utm_term,
                'content' => $this->utm_content,
            ],
            'sent_to_clay_at' => $this->sent_to_clay_at?->toIso8601String(),
            'registered_in_zoom_at' => $this->registered_in_zoom_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
