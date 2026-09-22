<?php

namespace App\Console\Commands;

use App\Jobs\SyncAttendeesJob;
use Illuminate\Console\Command;

class SyncAttendees extends Command
{
    protected $signature = 'webinars:sync-attendees {--webinar= : ID del webinar a sincronizar}';

    protected $description = 'Sincroniza los asistentes de Zoom hacia la tabla attendees';

    public function handle(): int
    {
        $webinarId = $this->option('webinar');

        dispatch_sync(new SyncAttendeesJob($webinarId ? (int) $webinarId : null));

        $this->info('Asistentes sincronizados.');

        return self::SUCCESS;
    }
}
