<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Submission;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RebuildUtmsFromAccessLogTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('framework/testing/nginx');

        if (! is_dir($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }

        array_map('unlink', glob($this->logPath.'/access.log*') ?: []);
    }

    private function webinar(): Webinar
    {
        $client = Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);

        return Webinar::create([
            'client_id' => $client->id,
            'title' => 'Identidad',
            'slug' => 'identidad-como-perimetro-ia',
        ]);
    }

    /**
     * Línea de access log como las que escribe nginx con el formato combined.
     */
    private function logLine(string $time, string $query, string $ip = '201.185.193.125'): string
    {
        $referer = "https://webinars.templet.io/libertynet/identidad-como-perimetro-ia{$query}";

        return "{$ip} - - [{$time}] \"POST /libertynet/identidad-como-perimetro-ia%20 HTTP/1.1\" 302 1794 \"{$referer}\" \"Mozilla/5.0\"\n";
    }

    private function writeLog(string $contents, string $name = 'access.log'): void
    {
        file_put_contents($this->logPath.'/'.$name, $contents);
    }

    private function submissionAt(Webinar $webinar, string $at): Submission
    {
        $submission = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['email' => 'lead@example.com'],
        ]);

        $submission->forceFill(['created_at' => Carbon::parse($at)])->save();

        return $submission;
    }

    private function rebuild(Webinar $webinar, array $options = [])
    {
        return $this->artisan('webinars:rebuild-utms', array_merge([
            'webinar' => $webinar->slug,
            '--path' => $this->logPath,
        ], $options));
    }

    public function test_it_recovers_the_utms_from_the_referer(): void
    {
        $webinar = $this->webinar();
        $submission = $this->submissionAt($webinar, '2026-10-20 02:34:53');

        $this->writeLog($this->logLine(
            '20/Oct/2026:02:34:52 +0000',
            '?fbclid=abc&utm_campaign=webinar&utm_content=estatico&utm_medium=meta&utm_source=cpc&utm_term=voceros_frio',
        ));

        $this->rebuild($webinar)->assertSuccessful();

        $submission->refresh();

        $this->assertSame('cpc', $submission->utm_source);
        $this->assertSame('meta', $submission->utm_medium);
        $this->assertSame('webinar', $submission->utm_campaign);
        $this->assertSame('voceros_frio', $submission->utm_term);
        $this->assertSame('estatico', $submission->utm_content);
        // Queda marcado como inferido.
        $this->assertNotNull($submission->utms_reconstructed_at);
    }

    public function test_it_reads_rotated_and_gzipped_logs(): void
    {
        $webinar = $this->webinar();
        $submission = $this->submissionAt($webinar, '2026-10-16 16:12:22');

        file_put_contents(
            $this->logPath.'/access.log.4.gz',
            gzencode($this->logLine('16/Oct/2026:16:12:21 +0000', '?utm_source=linkedin&utm_medium=social')),
        );

        $this->rebuild($webinar)->assertSuccessful();

        $this->assertSame('linkedin', $submission->refresh()->utm_source);
    }

    public function test_it_leaves_alone_a_submission_with_no_post_nearby(): void
    {
        $webinar = $this->webinar();
        $submission = $this->submissionAt($webinar, '2026-10-20 02:34:53');

        $this->writeLog($this->logLine('20/Oct/2026:09:00:00 +0000', '?utm_source=cpc'));

        $this->rebuild($webinar)->assertSuccessful();

        $this->assertNull($submission->refresh()->utm_source);
    }

    public function test_it_refuses_to_guess_between_two_simultaneous_posts(): void
    {
        $webinar = $this->webinar();
        $submission = $this->submissionAt($webinar, '2026-10-20 02:34:53');

        $this->writeLog(
            $this->logLine('20/Oct/2026:02:34:52 +0000', '?utm_source=cpc&utm_term=voceros_frio')
            .$this->logLine('20/Oct/2026:02:34:54 +0000', '?utm_source=cpc&utm_term=deepfake_frio', '191.156.151.11'),
        );

        $this->rebuild($webinar)->assertSuccessful();

        $this->assertNull($submission->refresh()->utm_source);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $webinar = $this->webinar();
        $submission = $this->submissionAt($webinar, '2026-10-20 02:34:53');

        $this->writeLog($this->logLine('20/Oct/2026:02:34:52 +0000', '?utm_source=cpc'));

        $this->rebuild($webinar, ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($submission->refresh()->utm_source);
    }

    public function test_it_does_not_touch_submissions_that_already_have_utms(): void
    {
        $webinar = $this->webinar();

        $submission = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['email' => 'lead@example.com'],
            'utm_source' => 'original',
        ]);
        $submission->forceFill(['created_at' => Carbon::parse('2026-10-20 02:34:53')])->save();

        $this->writeLog($this->logLine('20/Oct/2026:02:34:52 +0000', '?utm_source=cpc'));

        $this->rebuild($webinar)->assertSuccessful();

        $this->assertSame('original', $submission->refresh()->utm_source);
        $this->assertNull($submission->utms_reconstructed_at);
    }

    public function test_a_post_without_utms_is_reported_as_direct_traffic(): void
    {
        $webinar = $this->webinar();
        $submission = $this->submissionAt($webinar, '2026-10-20 02:34:53');

        $this->writeLog($this->logLine('20/Oct/2026:02:34:52 +0000', ''));

        $this->rebuild($webinar)->assertSuccessful();

        $this->assertNull($submission->refresh()->utm_source);
        $this->assertNull($submission->utms_reconstructed_at);
    }
}
