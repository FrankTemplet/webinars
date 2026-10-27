<?php

namespace Tests\Feature;

use App\Jobs\SyncAttendeesJob;
use App\Models\Attendee;
use App\Models\Client;
use App\Models\Submission;
use App\Models\Webinar;
use App\Services\SubmissionZoomRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebinarSessionsTest extends TestCase
{
    use RefreshDatabase;

    private const DAY_25 = '86036019470';

    private const DAY_26 = '85795097074';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-27 12:00:00');
    }

    private function webinar(array $attributes = []): Webinar
    {
        $client = Client::create(['name' => 'AWS', 'slug' => 'aws']);

        return Webinar::create(array_merge([
            'client_id' => $client->id,
            'title' => 'FastTrack GEN AI',
            'slug' => 'fasttrack-gen-ai',
            'form_schema' => [
                ['type' => 'text', 'name' => 'first_name', 'label' => 'Nombre', 'required' => true],
                ['type' => 'text', 'name' => 'last_name', 'label' => 'Apellido', 'required' => true],
                ['type' => 'email', 'name' => 'email', 'label' => 'Correo', 'required' => true],
            ],
            'multiple_sessions' => true,
            'sessions_label' => '¿A qué sesiones quieres asistir?',
            'sessions' => [
                ['zoom_webinar_id' => self::DAY_25, 'label' => 'Sesión 01 | 25 de noviembre', 'date' => '2026-11-25'],
                ['zoom_webinar_id' => self::DAY_26, 'label' => 'Sesión 02 | 26 de noviembre', 'date' => '2026-11-26'],
            ],
        ], $attributes));
    }

    /**
     * Los webinars de $failingOnce fallan la primera vez que se registra
     * alguien y después responden bien.
     */
    private function fakeZoom(array $failingOnce = []): void
    {
        $responses = [
            'zoom.us/oauth/token' => Http::response(['access_token' => 'token']),
        ];

        foreach ($failingOnce as $zoomWebinarId) {
            $responses["api.zoom.us/v2/webinars/{$zoomWebinarId}/registrants"] = Http::sequence()
                ->push(['message' => 'error'], 400)
                ->push(['id' => 'registrant'], 201);
        }

        Http::fake($responses + ['api.zoom.us/v2/webinars/*/registrants' => Http::response(['id' => 'registrant'], 201)]);
    }

    private function register(array $sessions)
    {
        return $this->post('/aws/fasttrack-gen-ai', [
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'ana@templet.io',
            'sessions' => $sessions,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function zoomWebinarsRegisteredIn(): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => str_ends_with($request->url(), '/registrants'))
            ->map(fn (Request $request) => explode('/', $request->url())[5])
            ->values()
            ->all();
    }

    public function test_choosing_one_session_registers_only_in_its_zoom_webinar(): void
    {
        $this->fakeZoom();
        $this->webinar();

        $this->register([self::DAY_26])->assertSessionHasNoErrors();

        $this->assertSame([self::DAY_26], $this->zoomWebinarsRegisteredIn());

        $submission = Submission::first();
        $this->assertSame([self::DAY_26], $submission->sessions);
        $this->assertArrayHasKey(self::DAY_26, $submission->zoom_registrations);
        $this->assertCount(1, $submission->zoom_registrations);
        $this->assertNotNull($submission->registered_in_zoom_at);
        $this->assertArrayNotHasKey('sessions', $submission->data);
    }

    public function test_choosing_both_sessions_registers_in_both(): void
    {
        $this->fakeZoom();
        $this->webinar();

        $this->register([self::DAY_25, self::DAY_26])->assertSessionHasNoErrors();

        $this->assertSame([self::DAY_25, self::DAY_26], $this->zoomWebinarsRegisteredIn());
        $this->assertNotNull(Submission::first()->registered_in_zoom_at);
    }

    public function test_at_least_one_known_session_is_required(): void
    {
        $this->fakeZoom();
        $this->webinar();

        $this->register([])->assertSessionHasErrors('sessions');
        $this->register(['123'])->assertSessionHasErrors('sessions.0');

        $this->assertSame(0, Submission::count());
    }

    public function test_a_failed_session_stays_pending_and_is_retried_alone(): void
    {
        $this->fakeZoom(failingOnce: [self::DAY_26]);
        $this->webinar();

        $this->register([self::DAY_25, self::DAY_26]);

        $submission = Submission::first();
        $this->assertNull($submission->registered_in_zoom_at);
        $this->assertSame([self::DAY_26], $submission->pendingZoomWebinarIds());

        $this->assertTrue(app(SubmissionZoomRegistrar::class)->register($submission->fresh()));

        // El 25 no se repite: solo el intento fallido y el reintento del 26.
        $this->assertSame([self::DAY_25, self::DAY_26, self::DAY_26], $this->zoomWebinarsRegisteredIn());
        $this->assertNotNull($submission->fresh()->registered_in_zoom_at);
    }

    public function test_sessions_replace_the_single_zoom_webinar_and_set_the_date(): void
    {
        $webinar = $this->webinar(['zoom_webinar_id' => self::DAY_25, 'event_date' => '2026-11-25']);

        $this->assertNull($webinar->zoom_webinar_id);
        $this->assertSame('2026-11-26', $webinar->event_date->toDateString());
        $this->assertSame([self::DAY_25, self::DAY_26], $webinar->zoomWebinarIds());
    }

    public function test_webinars_without_sessions_keep_registering_in_their_zoom_webinar(): void
    {
        $this->fakeZoom();
        $this->webinar(['multiple_sessions' => false, 'zoom_webinar_id' => self::DAY_25]);

        $this->register([])->assertSessionHasNoErrors();

        $this->assertSame([self::DAY_25], $this->zoomWebinarsRegisteredIn());
        $this->assertNull(Submission::first()->sessions);
    }

    public function test_turning_multiple_sessions_off_clears_the_sessions(): void
    {
        $webinar = $this->webinar();

        $webinar->update(['multiple_sessions' => false, 'zoom_webinar_id' => self::DAY_25]);

        $this->assertNull($webinar->fresh()->sessions);
        $this->assertSame([], $webinar->fresh()->publicSessions());
        $this->assertSame([self::DAY_25], $webinar->fresh()->zoomWebinarIds());
    }

    public function test_attendees_are_synced_from_each_past_session(): void
    {
        Carbon::setTestNow('2026-11-25 23:00:00');
        $webinar = $this->webinar();

        Http::fake([
            'zoom.us/oauth/token' => Http::response(['access_token' => 'token']),
            'api.zoom.us/v2/report/webinars/'.self::DAY_25.'/participants*' => Http::response(['participants' => [
                ['user_id' => '1', 'user_email' => 'ana@templet.io', 'duration' => 600],
                ['user_id' => '2', 'user_email' => 'luis@templet.io', 'duration' => 300],
            ]]),
            'api.zoom.us/*' => Http::response([], 404),
        ]);

        SyncAttendeesJob::dispatchSync($webinar->id);

        // El 26 todavía no ocurre: ni se le pide reporte a Zoom.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), self::DAY_26));
        $this->assertSame(2, Attendee::where('zoom_webinar_id', self::DAY_25)->count());
        $this->assertSame(2, $webinar->fresh()->zoom_participants_count);
    }

    public function test_old_answers_become_sessions_and_pending_ones_get_registered(): void
    {
        $webinar = $this->webinar([
            'form_schema' => [
                ['type' => 'email', 'name' => 'email', 'label' => 'Correo', 'required' => true],
                ['type' => 'radio', 'name' => 'sesiones ', 'label' => 'Sesiones', 'required' => true, 'options' => ['Sesión 01', 'Sesión 02', 'Ambas']],
            ],
        ]);
        $onlySecond = Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'luis@templet.io', 'sesiones ' => 'Sesión 02'], 'registered_in_zoom_at' => now()]);
        $both = Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'ana@templet.io', 'sesiones ' => 'Ambas'], 'registered_in_zoom_at' => now()]);

        $this->artisan('webinars:map-session-field', ['webinar' => 'fasttrack-gen-ai', 'field' => 'sesiones', '--registered-in' => self::DAY_25])
            ->expectsChoice('¿A qué sesiones corresponde «Sesión 02»?', ['Sesión 02 | 26 de noviembre'], ['Sesión 01 | 25 de noviembre', 'Sesión 02 | 26 de noviembre'])
            ->expectsChoice('¿A qué sesiones corresponde «Ambas»?', ['Sesión 01 | 25 de noviembre', 'Sesión 02 | 26 de noviembre'], ['Sesión 01 | 25 de noviembre', 'Sesión 02 | 26 de noviembre'])
            ->assertSuccessful();

        $this->assertSame([self::DAY_26], $onlySecond->fresh()->sessions);
        $this->assertSame([self::DAY_25, self::DAY_26], $both->fresh()->sessions);
        $this->assertSame([self::DAY_26], $both->fresh()->pendingZoomWebinarIds());
        $this->assertNotContains('sesiones ', array_column($webinar->fresh()->form_schema, 'name'));

        $this->fakeZoom();
        $this->artisan('webinars:register-pending-sessions', ['webinar' => 'fasttrack-gen-ai'])->assertSuccessful();

        // Nadie sale del 25: solo se agrega el 26 a quien lo eligió.
        $this->assertSame([self::DAY_26, self::DAY_26], $this->zoomWebinarsRegisteredIn());
        $this->assertSame([], $onlySecond->fresh()->pendingZoomWebinarIds());
        $this->assertSame([], $both->fresh()->pendingZoomWebinarIds());
    }
}
