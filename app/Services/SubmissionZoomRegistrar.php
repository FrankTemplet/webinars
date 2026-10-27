<?php

namespace App\Services;

use App\Models\Submission;

/**
 * Registra un lead en los webinars de Zoom que le tocan y guarda en cuáles
 * quedó, para que un fallo en una sesión se reintente sin repetir las demás.
 */
class SubmissionZoomRegistrar
{
    public function __construct(private ZoomService $zoom) {}

    /**
     * Con $again vuelve a registrar también donde ya estaba. Devuelve si
     * quedó registrado en todos.
     */
    public function register(Submission $submission, bool $again = false): bool
    {
        $registrations = $submission->zoom_registrations ?? [];
        $targets = $again ? $submission->zoomWebinarIds() : $submission->pendingZoomWebinarIds();
        $registeredAny = false;

        foreach ($targets as $zoomWebinarId) {
            if ($this->zoom->registerRegistrant($zoomWebinarId, $submission->data ?? [])) {
                $registrations[$zoomWebinarId] = now()->toIso8601String();
                $registeredAny = true;
            }
        }

        $submission->zoom_registrations = $registrations ?: null;
        $complete = $submission->pendingZoomWebinarIds() === [];
        $submission->registered_in_zoom_at = match (true) {
            ! $complete => null,
            $registeredAny => now(),
            default => $submission->registered_in_zoom_at,
        };
        $submission->save();

        return $complete;
    }
}
