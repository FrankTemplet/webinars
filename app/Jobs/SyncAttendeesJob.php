<?php

namespace App\Jobs;

use App\Models\Attendee;
use App\Models\Webinar;
use App\Services\ZoomService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Descarga de Zoom los participantes de los webinars y los guarda en attendees.
 * Sin webinarId sincroniza todos los que tengan zoom_webinar_id.
 */
class SyncAttendeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $webinarId = null) {}

    public function handle(ZoomService $zoom): void
    {
        $webinars = Webinar::withoutGlobalScopes()
            ->whereNotNull('zoom_webinar_id')
            ->where('zoom_webinar_id', '!=', '')
            ->when($this->webinarId, fn ($q) => $q->where('id', $this->webinarId))
            ->get();

        foreach ($webinars as $webinar) {
            $participants = $zoom->getWebinarParticipantsDetailed($webinar->zoom_webinar_id);

            foreach ($participants as $participant) {
                $participantId = $participant['participant_uuid']
                    ?? $participant['id']
                    ?? $participant['user_id']
                    ?? null;

                if (! $participantId) {
                    // Sin identificador estable, se usa email + join_time para no duplicar.
                    $participantId = md5(($participant['user_email'] ?? '').($participant['join_time'] ?? ''));
                }

                Attendee::withoutGlobalScopes()->updateOrCreate(
                    [
                        'webinar_id' => $webinar->id,
                        'zoom_participant_id' => $participantId,
                    ],
                    [
                        'zoom_user_id' => $participant['user_id'] ?? null,
                        'name' => $participant['name'] ?? null,
                        'email' => $participant['user_email'] ?? null,
                        'join_time' => $participant['join_time'] ?? null,
                        'leave_time' => $participant['leave_time'] ?? null,
                        'duration' => (int) ($participant['duration'] ?? 0),
                        'device' => $participant['device'] ?? null,
                        'ip_address' => $participant['ip_address'] ?? null,
                        'location' => $participant['location'] ?? null,
                        'raw' => $participant,
                    ]
                );
            }

            Log::info("Synced {$webinar->id}: ".count($participants).' attendees from Zoom.');
        }
    }
}
