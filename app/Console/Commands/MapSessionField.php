<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Models\Webinar;
use Illuminate\Console\Command;

/**
 * Pasa a sesiones los registros de un webinar que pedía la sesión con un
 * campo propio del formulario (un radio con "Sesión 01", "Ambas"...). Por
 * cada respuesta distinta pregunta a qué sesiones corresponde, y al final
 * quita ese campo del formulario: lo reemplazan los checkboxes de sesiones.
 */
class MapSessionField extends Command
{
    protected $signature = 'webinars:map-session-field
                            {webinar : Id o slug del webinar}
                            {field : Nombre del campo del formulario}
                            {--registered-in= : Zoom webinar donde quedaron los registros previos a las sesiones}
                            {--dry-run : Solo muestra lo que haría}';

    protected $description = 'Convierte las respuestas de un campo de sesión del formulario en sesiones';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando pregunta a qué sesiones corresponde cada respuesta: córrelo sin --no-interaction.');

            return self::FAILURE;
        }

        $webinar = Webinar::query()
            ->where('id', $this->argument('webinar'))
            ->orWhere('slug', $this->argument('webinar'))
            ->first();

        if (! $webinar?->hasSessions()) {
            $this->error('No encontré ese webinar o todavía no tiene sesiones configuradas.');

            return self::FAILURE;
        }

        // El nombre puede traer espacios al final, como "sesiones ".
        $field = collect($webinar->form_schema)->first(fn (array $f) => trim($f['name']) === trim($this->argument('field')));

        if (! $field) {
            $this->error("El formulario no tiene el campo «{$this->argument('field')}».");

            return self::FAILURE;
        }

        $registeredIn = $this->option('registered-in');
        if ($registeredIn && ! in_array($registeredIn, $webinar->zoomWebinarIds(), true)) {
            $this->error("{$registeredIn} no es una de las sesiones del webinar.");

            return self::FAILURE;
        }

        $submissions = Submission::query()->where('webinar_id', $webinar->id)->whereNull('sessions')->get();

        if (! $registeredIn && $submissions->whereNotNull('registered_in_zoom_at')->isNotEmpty()) {
            $this->error('Hay registros ya enviados a Zoom: indica con --registered-in en qué sesión quedaron.');

            return self::FAILURE;
        }

        $labels = collect($webinar->sessions)->pluck('label', 'zoom_webinar_id')->all();
        $mapping = [];

        foreach ($submissions->map(fn (Submission $s) => $s->data[$field['name']] ?? null)->filter()->unique() as $answer) {
            $chosen = $this->choice("¿A qué sesiones corresponde «{$answer}»?", array_values($labels), null, null, true);
            $mapping[$answer] = array_map('strval', array_keys(array_intersect($labels, $chosen)));
        }

        $this->table(['Respuesta', 'Sesiones', 'Registros'], collect($mapping)->map(fn (array $ids, string $answer) => [
            $answer,
            implode(' + ', array_map(fn ($id) => $labels[$id], $ids)),
            $submissions->filter(fn (Submission $s) => ($s->data[$field['name']] ?? null) === $answer)->count(),
        ])->values());

        if ($this->option('dry-run')) {
            $this->info('Dry run: no se guardó nada.');

            return self::SUCCESS;
        }

        foreach ($submissions as $submission) {
            $submission->sessions = $mapping[$submission->data[$field['name']] ?? null] ?? [];

            if ($submission->registered_in_zoom_at && empty($submission->zoom_registrations)) {
                $submission->zoom_registrations = [$registeredIn => $submission->registered_in_zoom_at->toIso8601String()];
                // Queda pendiente si eligió sesiones donde todavía no está.
                $submission->registered_in_zoom_at = $submission->pendingZoomWebinarIds() ? null : $submission->registered_in_zoom_at;
            }

            $submission->save();
        }

        $webinar->update([
            'form_schema' => collect($webinar->form_schema)->reject(fn (array $f) => $f['name'] === $field['name'])->values()->all(),
            'chartable_fields' => collect($webinar->chartable_fields ?? [])->reject(fn ($name) => $name === $field['name'])->values()->all(),
        ]);

        $this->info("Listo: {$submissions->count()} registros con sesiones y el campo «{$field['label']}» fuera del formulario.");

        return self::SUCCESS;
    }
}
