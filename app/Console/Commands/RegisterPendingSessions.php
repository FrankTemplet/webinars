<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Models\Webinar;
use App\Services\SubmissionZoomRegistrar;
use Illuminate\Console\Command;

/**
 * Registra en Zoom a los leads de un webinar en las sesiones que eligieron y
 * donde todavía no están. No saca a nadie de ninguna sesión.
 */
class RegisterPendingSessions extends Command
{
    protected $signature = 'webinars:register-pending-sessions
                            {webinar : Id o slug del webinar}
                            {--dry-run : Solo muestra lo que haría}';

    protected $description = 'Registra en Zoom las sesiones pendientes de cada lead de un webinar';

    public function handle(SubmissionZoomRegistrar $registrar): int
    {
        $webinar = Webinar::query()
            ->where('id', $this->argument('webinar'))
            ->orWhere('slug', $this->argument('webinar'))
            ->first();

        if (! $webinar?->zoomWebinarIds()) {
            $this->error('No encontré ese webinar o no tiene Zoom.');

            return self::FAILURE;
        }

        $pending = Submission::query()
            ->where('webinar_id', $webinar->id)
            ->get()
            ->filter(fn (Submission $submission) => $submission->pendingZoomWebinarIds());

        $this->table(['Submission', 'Email', 'Sesiones por registrar'], $pending->map(fn (Submission $submission) => [
            $submission->id,
            $submission->data['email'] ?? '—',
            implode(' + ', array_map(fn ($id) => $webinar->sessionLabel($id), $submission->pendingZoomWebinarIds())),
        ]));

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$pending->count()} registros pendientes, no se envió nada a Zoom.");

            return self::SUCCESS;
        }

        $failed = $pending->reject(fn (Submission $submission) => $registrar->register($submission))->count();

        $this->info('Registrados: '.($pending->count() - $failed).". Con errores: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
