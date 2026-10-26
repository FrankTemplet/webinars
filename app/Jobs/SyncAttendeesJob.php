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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Descarga de Zoom los participantes de los webinars y los guarda en attendees.
 * Sin webinarId sincroniza los que tengan zoom_webinar_id y fecha reciente.
 */
class SyncAttendeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SYNC_WINDOW_DAYS = 30;

    public function __construct(public ?int $webinarId = null) {}

    public function handle(ZoomService $zoom): void
    {
        $webinars = Webinar::withoutGlobalScopes()
            ->whereNotNull('zoom_webinar_id')
            ->where('zoom_webinar_id', '!=', '')
            ->when($this->webinarId, fn ($q) => $q->where('id', $this->webinarId))
            // En la corrida programada, solo los de los últimos 30 días: los
            // anteriores ya no cambian. Sin fecha se siguen sincronizando.
            ->when(! $this->webinarId, fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('event_date')
                ->orWhereBetween('event_date', [today()->subDays(self::SYNC_WINDOW_DAYS), today()])))
            ->get();

        foreach ($webinars as $webinar) {
            $participants = $zoom->getWebinarParticipantsDetailed($webinar->zoom_webinar_id);

            // Una respuesta incompleta no debe borrar lo que ya teníamos.
            if ($participants === null) {
                Log::warning("Skipped attendee sync for webinar {$webinar->id}: Zoom's report could not be read in full.");

                continue;
            }

            DB::transaction(function () use ($webinar, $participants) {
                $keys = [];

                foreach ($participants as $participant) {
                    $keys[] = $participantId = $this->participantKey($participant);

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

                // Lo guardado queda igual al reporte: se van las filas con llaves
                // viejas o que Zoom ya no reporta. Un reporte vacío no borra nada.
                if ($keys) {
                    Attendee::withoutGlobalScopes()
                        ->where('webinar_id', $webinar->id)
                        ->whereNotIn('zoom_participant_id', $keys)
                        ->delete();
                }

                $webinar->update([
                    'attendees_synced_at' => now(),
                    'zoom_participants_count' => count($participants),
                ]);
            });

            Log::info("Synced {$webinar->id}: ".count($participants).' attendees from Zoom.');
        }
    }

    /**
     * Identificador de una sesión en el reporte de Zoom. `user_id` es único por
     * sesión y va primero: `id` es la cuenta de Zoom, se repite en cada
     * reconexión de quien entra con cuenta y viene vacío para quien no la tiene.
     * Si no hay ninguno, correo + hora de entrada.
     */
    private function participantKey(array $participant): string
    {
        foreach (['user_id', 'participant_uuid', 'id'] as $field) {
            if (filled($participant[$field] ?? null)) {
                return (string) $participant[$field];
            }
        }

        return md5(($participant['user_email'] ?? '').($participant['join_time'] ?? ''));
    }
}
