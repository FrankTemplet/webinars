<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Models\Webinar;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Reconstruye los UTM de registros que los perdieron, leyéndolos del access
 * log de nginx.
 *
 * Cada envío del formulario deja un POST cuyo Referer es la URL de la landing
 * con su query string intacta. Emparejando ese POST con la submission por
 * hora se recupera de dónde venía cada persona.
 *
 * Es un dato inferido: se marca con utms_reconstructed_at para no confundirlo
 * con el que sí capturó el formulario.
 */
class RebuildUtmsFromAccessLog extends Command
{
    protected $signature = 'webinars:rebuild-utms
                            {webinar : Id o slug del webinar}
                            {--path=/var/log/nginx : Carpeta de los access log}
                            {--tolerance=180 : Segundos de diferencia admitidos entre el POST y el registro}
                            {--dry-run : Solo muestra lo que haría}';

    protected $description = 'Recupera los UTM perdidos desde el access log de nginx';

    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * Dos POST más cerca que esto entre sí no se pueden distinguir con
     * seguridad, así que ninguno se atribuye.
     */
    private const AMBIGUITY_SECONDS = 15;

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

        $posts = $this->readPosts($webinar->slug);

        if (empty($posts)) {
            $this->error('No encontré ningún POST a esa landing en los logs de '.$this->option('path').'.');

            return self::FAILURE;
        }

        $this->info('POST encontrados en el log: '.count($posts).' ('.collect($posts)->filter(fn ($p) => $p['utms'])->count().' con UTM).');

        $pending = Submission::withoutGlobalScopes()
            ->where('webinar_id', $webinar->id)
            ->whereNull('utm_source')
            ->orderBy('created_at')
            ->get();

        $this->info('Registros sin UTM: '.$pending->count().PHP_EOL);

        $tolerance = (int) $this->option('tolerance');
        $matched = 0;
        $ambiguous = 0;
        $missed = 0;

        foreach ($pending as $submission) {
            $candidates = $this->candidatesFor($posts, $submission->created_at, $tolerance);

            if (empty($candidates)) {
                $missed++;
                $this->line('  ?  '.$submission->created_at->toDateTimeString().'  sin POST cercano');

                continue;
            }

            // Con dos envíos casi simultáneos no hay forma de saber cuál es cuál.
            if (count($candidates) > 1 && abs($candidates[0]['delta'] - $candidates[1]['delta']) < self::AMBIGUITY_SECONDS) {
                $ambiguous++;
                $this->line('  !  '.$submission->created_at->toDateTimeString().'  dos POST igual de cerca, no se atribuye');

                continue;
            }

            $utms = $candidates[0]['post']['utms'];

            if (empty($utms)) {
                $missed++;
                $this->line('  -  '.$submission->created_at->toDateTimeString().'  el POST no traía UTM (tráfico directo)');

                continue;
            }

            $matched++;
            $this->line('  +  '.$submission->created_at->toDateTimeString()
                .'  '.($utms['utm_source'] ?? '?').' / '.($utms['utm_term'] ?? '-')
                .'  (desfase '.$candidates[0]['delta'].'s)');

            if (! $this->option('dry-run')) {
                $submission->forceFill($utms + ['utms_reconstructed_at' => now()])->save();
            }
        }

        $this->newLine();
        $this->info(($this->option('dry-run') ? 'Simulación. ' : '')
            ."Atribuidos: {$matched}. Ambiguos: {$ambiguous}. Sin UTM o sin POST: {$missed}.");
        $this->warn('Los UTM reconstruidos quedan marcados con utms_reconstructed_at: son inferidos por hora, no capturados.');

        return self::SUCCESS;
    }

    /**
     * POST a la landing, con la hora y los UTM que traía el Referer.
     *
     * @return array<int, array{at: Carbon, utms: array<string, string>}>
     */
    private function readPosts(string $slug): array
    {
        $posts = [];

        foreach ($this->logFiles() as $file) {
            $handle = str_ends_with($file, '.gz') ? gzopen($file, 'rb') : fopen($file, 'rb');

            if (! $handle) {
                continue;
            }

            while (($line = (str_ends_with($file, '.gz') ? gzgets($handle) : fgets($handle))) !== false) {
                if (! str_contains($line, 'POST ') || ! str_contains($line, $slug)) {
                    continue;
                }

                if (! preg_match('/\[([^\]]+)\] "POST ([^" ]+)[^"]*" \d{3} \d+ "([^"]*)"/', $line, $m)) {
                    continue;
                }

                // El path puede llegar con el slug escapado (%20 y similares).
                if (! str_contains(urldecode($m[2]), $slug)) {
                    continue;
                }

                $posts[] = [
                    'at' => Carbon::createFromFormat('d/M/Y:H:i:s O', $m[1])->utc(),
                    'utms' => $this->utmsFrom($m[3]),
                ];
            }

            str_ends_with($file, '.gz') ? gzclose($handle) : fclose($handle);
        }

        usort($posts, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $posts;
    }

    /**
     * @return array<int, string>
     */
    private function logFiles(): array
    {
        return glob(rtrim($this->option('path'), '/').'/access.log*') ?: [];
    }

    /**
     * @return array<string, string>
     */
    private function utmsFrom(string $referer): array
    {
        $query = parse_url($referer, PHP_URL_QUERY);

        if (! $query) {
            return [];
        }

        parse_str($query, $params);

        return collect(self::UTM_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => $params[$key] ?? null])
            ->filter(fn ($value) => filled($value) && is_string($value))
            ->all();
    }

    /**
     * POST dentro de la tolerancia, del más cercano al más lejano.
     *
     * @param  array<int, array{at: Carbon, utms: array<string, string>}>  $posts
     * @return array<int, array{post: array, delta: int}>
     */
    private function candidatesFor(array $posts, Carbon $moment, int $tolerance): array
    {
        $candidates = [];

        foreach ($posts as $post) {
            $delta = abs($post['at']->diffInSeconds($moment, absolute: true));

            if ($delta <= $tolerance) {
                $candidates[] = ['post' => $post, 'delta' => (int) $delta];
            }
        }

        usort($candidates, fn ($a, $b) => $a['delta'] <=> $b['delta']);

        return $candidates;
    }
}
