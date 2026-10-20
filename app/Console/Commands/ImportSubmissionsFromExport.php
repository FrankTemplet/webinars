<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Models\Webinar;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Repuebla los registros de un webinar desde un Excel exportado antes.
 *
 * Es la vía que más datos devuelve cuando se pierden registros: el export
 * lleva todas las respuestas del formulario y los UTM tal como se
 * capturaron. Completa lo que falte en los registros que ya existan y crea
 * los que no estén, sin pisar nunca un valor ya guardado.
 */
class ImportSubmissionsFromExport extends Command
{
    protected $signature = 'webinars:import-submissions
                            {webinar : Id o slug del webinar de destino}
                            {file : Ruta del .xlsx exportado}
                            {--from= : Valor de la columna Webinar en el archivo, si no coincide con el título}
                            {--dry-run : Solo muestra lo que haría}';

    protected $description = 'Repuebla las submissions de un webinar desde un export de Excel';

    /**
     * Columnas que no son respuestas del formulario.
     */
    private const CONTROL_COLUMNS = [
        'webinar', 'enviado_a_clay', 'registrado_en_zoom', 'fecha_de_creacion',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    /**
     * Encabezado del export => clave con la que se guarda la respuesta.
     */
    private const FIELD_ALIASES = [
        'nombre' => 'first_name',
        'first_name' => 'first_name',
        'apellido' => 'last_name',
        'apellidos' => 'last_name',
        'last_name' => 'last_name',
        'correo' => 'email',
        'correo_electronico' => 'email',
        'correo_electronico_corporativo' => 'email',
        'correo_corporativo' => 'email',
        'email' => 'email',
        'movil_celular' => 'phone_number',
        'celular' => 'phone_number',
        'telefono' => 'phone_number',
        'phone' => 'phone_number',
        'phone_number' => 'phone_number',
        'empresa' => 'company',
        'compania' => 'company',
        'company' => 'company',
        'sector' => 'sector',
        'industria' => 'sector',
        'puesto' => 'job_title',
        'cargo' => 'job_title',
        'job_title' => 'job_title',
        'pais' => 'country',
        'country' => 'country',
        'numero_de_empleados' => 'employee_range',
        'employee_range' => 'employee_range',
    ];

    public function handle(): int
    {
        $webinar = Webinar::query()
            ->where('id', $this->argument('webinar'))
            ->orWhere('slug', $this->argument('webinar'))
            ->first();

        if (! $webinar) {
            $this->error('No encontré ese webinar.');

            return self::FAILURE;
        }

        $file = $this->argument('file');

        if (! is_readable($file)) {
            $this->error("No puedo leer {$file}.");

            return self::FAILURE;
        }

        [$headers, $rows] = $this->read($file);
        $wanted = Str::lower(trim($this->option('from') ?: $webinar->title));

        $rows = array_values(array_filter(
            $rows,
            fn (array $row) => Str::lower(trim((string) ($row['webinar'] ?? ''))) === $wanted,
        ));

        if (empty($rows)) {
            $this->error("El archivo no trae filas con Webinar = «{$wanted}».");
            $this->line('Valores disponibles: '.implode(' | ', $this->webinarValues($file)));

            return self::FAILURE;
        }

        $this->info('Filas en el archivo para ese webinar: '.count($rows));
        $this->line('Columnas de respuesta: '.implode(', ', $this->answerColumns($headers)).PHP_EOL);

        $created = 0;
        $completed = 0;
        $untouched = 0;

        foreach ($rows as $row) {
            $data = $this->answers($headers, $row);
            $email = Str::lower(trim((string) ($data['email'] ?? '')));

            if ($email === '') {
                $this->line('  ?  fila sin correo, se omite');

                continue;
            }

            $existing = $this->findByEmail($webinar, $email);

            if (! $existing) {
                $this->line("  +  {$email}  (nuevo, con todos sus campos)");
                $created++;

                if (! $this->option('dry-run')) {
                    $this->create($webinar, $data, $row);
                }

                continue;
            }

            $changes = $this->changesFor($existing, $data, $row);

            if (empty($changes)) {
                $untouched++;

                continue;
            }

            $this->line("  ~  {$email}  completa: ".implode(', ', array_keys($changes)));
            $completed++;

            if (! $this->option('dry-run')) {
                $existing->forceFill($changes)->save();
            }
        }

        $this->newLine();
        $this->info(($this->option('dry-run') ? 'Simulación. ' : '')
            ."Creados: {$created}. Completados: {$completed}. Sin cambios: {$untouched}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    private function read(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $raw = $reader->load($file)->getActiveSheet()->toArray();

        $headers = array_map($this->normalize(...), array_shift($raw));

        $rows = [];

        foreach ($raw as $line) {
            $row = [];

            foreach ($headers as $i => $header) {
                $row[$header] = $line[$i] ?? null;
            }

            $rows[] = $row;
        }

        return [$headers, $rows];
    }

    /**
     * Str::snake parte las mayúsculas seguidas («UTM Source» acabaría como
     * u_t_m_source), así que se normaliza a mano.
     */
    private function normalize(mixed $header): string
    {
        return Str::of((string) $header)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
    }

    /**
     * @return array<int, string>
     */
    private function webinarValues(string $file): array
    {
        [, $rows] = $this->read($file);

        return collect($rows)->pluck('webinar')->filter()->unique()->values()->all();
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function answerColumns(array $headers): array
    {
        return array_values(array_diff($headers, self::CONTROL_COLUMNS));
    }

    /**
     * Respuestas del formulario, con la clave del repo cuando se reconoce el
     * encabezado y el propio encabezado cuando no.
     *
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function answers(array $headers, array $row): array
    {
        $answers = [];

        foreach ($this->answerColumns($headers) as $header) {
            $value = trim((string) ($row[$header] ?? ''));

            if ($value === '') {
                continue;
            }

            $answers[self::FIELD_ALIASES[$header] ?? $header] = $value;
        }

        return $answers;
    }

    private function findByEmail(Webinar $webinar, string $email): ?Submission
    {
        return Submission::withoutGlobalScopes()
            ->where('webinar_id', $webinar->id)
            ->get()
            ->first(fn (Submission $s) => Str::lower(trim((string) ($s->data['email'] ?? ''))) === $email);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $row
     */
    private function create(Webinar $webinar, array $data, array $row): void
    {
        $submission = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => $data,
        ] + $this->utms($row));

        $submission->forceFill($this->timestamps($row))->save();
    }

    /**
     * Qué le falta a un registro que ya existe. Nunca pisa lo que ya tiene.
     *
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function changesFor(Submission $existing, array $data, array $row): array
    {
        $changes = [];
        $merged = $existing->data ?? [];
        $added = false;

        foreach ($data as $key => $value) {
            if (trim((string) ($merged[$key] ?? '')) === '') {
                $merged[$key] = $value;
                $added = true;
            }
        }

        if ($added) {
            $changes['data'] = $merged;
        }

        // Los UTM del export son los que capturó el formulario, así que
        // reemplazan a cualquiera que se hubiera reconstruido después.
        $utms = array_filter($this->utms($row), fn ($value) => filled($value));

        if ($utms && ($existing->utms_reconstructed_at || blank($existing->utm_source))) {
            $changes += $utms;
            $changes['utms_reconstructed_at'] = null;
        }

        // La fecha del export es la original del registro. Se respeta la que
        // ya tiene salvo que difiera de verdad: los registros recuperados de
        // Zoom traen la suya con uno o dos segundos de desfase y esos no hay
        // que tocarlos.
        foreach ($this->timestamps($row) as $column => $value) {
            $current = $existing->{$column};

            if (blank($current) || $current->diffInMinutes($value, absolute: true) > 5) {
                $changes[$column] = $value;
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    private function utms(array $row): array
    {
        return collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
            ->mapWithKeys(fn (string $key) => [$key => trim((string) ($row[$key] ?? '')) ?: null])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, Carbon>
     */
    private function timestamps(array $row): array
    {
        $timestamps = [];

        foreach (['fecha_de_creacion' => 'created_at', 'registrado_en_zoom' => 'registered_in_zoom_at'] as $column => $attribute) {
            $value = trim((string) ($row[$column] ?? ''));

            if ($value !== '') {
                $timestamps[$attribute] = Carbon::parse($value);
            }
        }

        return $timestamps;
    }
}
