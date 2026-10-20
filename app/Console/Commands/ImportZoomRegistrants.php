<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Models\Webinar;
use App\Services\ZoomService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Reconstruye los registros de un webinar a partir de los registrantes que
 * guarda Zoom.
 *
 * Zoom solo recibe nombre, apellido y correo (ver ZoomService::registerRegistrant),
 * así que el resto de los campos del formulario no se puede recuperar por
 * aquí: quedan vacíos.
 */
class ImportZoomRegistrants extends Command
{
    protected $signature = 'webinars:import-zoom-registrants
                            {webinar : Id o slug del webinar}
                            {--dry-run : Solo muestra lo que haría}';

    protected $description = 'Recrea las submissions de un webinar desde los registrantes de Zoom';

    /**
     * Nombres habituales de cada campo en los formularios del repo.
     */
    private const FIELD_ALIASES = [
        'first_name' => ['first_name', 'nombre', 'name'],
        'last_name' => ['last_name', 'apellido', 'apellidos'],
        'email' => ['email', 'correo', 'correo_corporativo'],
    ];

    public function handle(ZoomService $zoom): int
    {
        $webinar = Webinar::query()
            ->where('id', $this->argument('webinar'))
            ->orWhere('slug', $this->argument('webinar'))
            ->first();

        if (! $webinar) {
            $this->error('No encontré ese webinar.');

            return self::FAILURE;
        }

        if (! $webinar->zoom_webinar_id) {
            $this->error("El webinar «{$webinar->title}» no tiene zoom_webinar_id.");

            return self::FAILURE;
        }

        $registrants = $zoom->listRegistrants($webinar->zoom_webinar_id);

        if (empty($registrants)) {
            $this->warn('Zoom no devolvió registrantes.');

            return self::SUCCESS;
        }

        $this->info('Zoom devolvió '.count($registrants)." registrantes para «{$webinar->title}».");

        $keys = $this->resolveFieldNames($webinar);
        $existing = Submission::withoutGlobalScopes()
            ->where('webinar_id', $webinar->id)
            ->pluck('data')
            ->map(fn ($data) => strtolower(trim((string) ($data[$keys['email']] ?? ''))))
            ->filter()
            ->all();

        $created = 0;
        $skipped = 0;

        foreach ($registrants as $registrant) {
            $email = strtolower(trim((string) ($registrant['email'] ?? '')));

            if ($email === '' || in_array($email, $existing, true)) {
                $skipped++;

                continue;
            }

            $this->line("  + {$registrant['first_name']} {$registrant['last_name']} <{$email}>");

            if (! $this->option('dry-run')) {
                $registeredAt = Carbon::parse($registrant['create_time'] ?? now());

                $submission = Submission::create([
                    'webinar_id' => $webinar->id,
                    'data' => [
                        $keys['first_name'] => $registrant['first_name'] ?? '',
                        $keys['last_name'] => $registrant['last_name'] ?? '',
                        $keys['email'] => $registrant['email'],
                    ],
                    'registered_in_zoom_at' => $registeredAt,
                ]);

                // La fecha del registro es la de Zoom, no la de la importación.
                $submission->forceFill(['created_at' => $registeredAt])->save();
            }

            $existing[] = $email;
            $created++;
        }

        $this->newLine();
        $this->info($this->option('dry-run')
            ? "Simulación: se crearían {$created} registros ({$skipped} ya estaban o sin correo)."
            : "Creados {$created} registros ({$skipped} ya estaban o sin correo).");

        $this->warn('Zoom solo guarda nombre, apellido y correo: los demás campos del formulario quedan vacíos.');

        return self::SUCCESS;
    }

    /**
     * Nombre real de cada campo en el formulario de este webinar, para que
     * los datos importados encajen con los que ya existen.
     *
     * @return array<string, string>
     */
    private function resolveFieldNames(Webinar $webinar): array
    {
        $names = collect($webinar->form_schema ?? [])->pluck('name')->filter()->all();
        $resolved = [];

        foreach (self::FIELD_ALIASES as $canonical => $aliases) {
            $resolved[$canonical] = collect($aliases)->first(
                fn (string $alias) => in_array($alias, $names, true),
            ) ?? $canonical;
        }

        return $resolved;
    }
}
