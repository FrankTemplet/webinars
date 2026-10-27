<?php

namespace Tests\Feature;

use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Models\ApiKey;
use App\Models\Attendee;
use App\Models\Client;
use App\Models\Submission;
use App\Models\User;
use App\Models\Webinar;
use App\Services\SubmissionZoomRegistrar;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Que las sesiones no cambien cómo se registran en Zoom los webinars de un
 * solo Zoom, ni los registros que ya existían, y que las acciones del panel
 * y la API respeten las sesiones.
 */
class ZoomRegistrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const SINGLE = '81111111111';

    private const DAY_25 = '86036019470';

    private const DAY_26 = '85795097074';

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::create(['name' => 'AWS', 'slug' => 'aws']);
    }

    private function singleZoomWebinar(): Webinar
    {
        return Webinar::create([
            'client_id' => $this->client->id,
            'title' => 'Infraestructura',
            'slug' => 'infraestructura',
            'zoom_webinar_id' => self::SINGLE,
            'form_schema' => [
                ['type' => 'email', 'name' => 'email', 'label' => 'Correo', 'required' => true],
            ],
        ]);
    }

    private function sessionsWebinar(): Webinar
    {
        return Webinar::create([
            'client_id' => $this->client->id,
            'title' => 'FastTrack GEN AI',
            'slug' => 'fasttrack-gen-ai',
            'multiple_sessions' => true,
            'sessions' => [
                ['zoom_webinar_id' => self::DAY_25, 'label' => 'Sesión 01', 'date' => '2026-11-25'],
                ['zoom_webinar_id' => self::DAY_26, 'label' => 'Sesión 02', 'date' => '2026-11-26'],
            ],
        ]);
    }

    /**
     * Zoom responde bien salvo en los webinars de $failing.
     */
    private function fakeZoom(array $failing = []): void
    {
        $responses = ['zoom.us/oauth/token' => Http::response(['access_token' => 'token'])];

        foreach ($failing as $zoomWebinarId) {
            $responses["api.zoom.us/v2/webinars/{$zoomWebinarId}/registrants"] = Http::response(['message' => 'error'], 400);
        }

        Http::fake($responses + ['api.zoom.us/v2/webinars/*/registrants' => Http::response(['id' => 'registrant'], 201)]);
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

    private function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_single_zoom_registration_that_fails_stays_pending(): void
    {
        $this->fakeZoom(failing: [self::SINGLE]);
        $this->singleZoomWebinar();

        $this->post('/aws/infraestructura', ['email' => 'ana@templet.io'])->assertSessionHasNoErrors();

        $submission = Submission::first();
        $this->assertNull($submission->registered_in_zoom_at);
        $this->assertNull($submission->zoom_registrations);
        $this->assertSame([self::SINGLE], $submission->pendingZoomWebinarIds());
    }

    public function test_single_zoom_registration_that_succeeds_is_marked(): void
    {
        $this->fakeZoom();
        $this->singleZoomWebinar();

        $this->post('/aws/infraestructura', ['email' => 'ana@templet.io']);

        $submission = Submission::first();
        $this->assertNotNull($submission->registered_in_zoom_at);
        $this->assertSame([], $submission->pendingZoomWebinarIds());
    }

    public function test_registrations_made_before_sessions_are_not_sent_to_zoom_again(): void
    {
        $this->fakeZoom();
        $webinar = $this->singleZoomWebinar();
        $old = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['email' => 'ana@templet.io'],
            'registered_in_zoom_at' => now()->subMonth(),
        ]);

        $this->assertSame([], $old->pendingZoomWebinarIds());
        $this->assertTrue(app(SubmissionZoomRegistrar::class)->register($old));

        $this->assertSame([], $this->zoomWebinarsRegisteredIn());
        $this->assertEquals(now()->subMonth()->startOfSecond(), $old->fresh()->registered_in_zoom_at);

        $this->artisan('webinars:register-pending-sessions', ['webinar' => 'infraestructura'])->assertSuccessful();
        $this->assertSame([], $this->zoomWebinarsRegisteredIn());
    }

    public function test_bulk_action_registers_only_the_pending_ones_of_a_single_zoom_webinar(): void
    {
        $this->fakeZoom();
        $this->actingAsAdmin();
        $webinar = $this->singleZoomWebinar();
        Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'ya@templet.io'], 'registered_in_zoom_at' => now()]);
        $pending = Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'nuevo@templet.io']]);

        Livewire::test(ListSubmissions::class)
            ->filterTable('submitted', ['client_id' => $this->client->id, 'webinar_id' => $webinar->id])
            ->callAction(TestAction::make('registerPendingInZoom')->table());

        $this->assertSame([self::SINGLE], $this->zoomWebinarsRegisteredIn());
        $this->assertNotNull($pending->fresh()->registered_in_zoom_at);
    }

    public function test_bulk_action_registers_the_pending_sessions_of_each_lead(): void
    {
        $this->fakeZoom();
        $this->actingAsAdmin();
        $webinar = $this->sessionsWebinar();
        $submission = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['email' => 'ana@templet.io'],
            'sessions' => [self::DAY_25, self::DAY_26],
            'zoom_registrations' => [self::DAY_25 => now()->toIso8601String()],
        ]);

        Livewire::test(ListSubmissions::class)
            ->filterTable('submitted', ['client_id' => $this->client->id, 'webinar_id' => $webinar->id])
            ->callAction(TestAction::make('registerPendingInZoom')->table());

        $this->assertSame([self::DAY_26], $this->zoomWebinarsRegisteredIn());
        $this->assertNotNull($submission->fresh()->registered_in_zoom_at);
    }

    public function test_row_action_completes_pending_sessions_and_re_registers_complete_ones(): void
    {
        $this->fakeZoom();
        $this->actingAsAdmin();
        $webinar = $this->sessionsWebinar();
        $submission = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['email' => 'ana@templet.io'],
            'sessions' => [self::DAY_25, self::DAY_26],
            'zoom_registrations' => [self::DAY_25 => now()->toIso8601String()],
        ]);

        $page = Livewire::test(ListSubmissions::class)
            ->filterTable('submitted', ['client_id' => $this->client->id, 'webinar_id' => $webinar->id]);

        $page->callAction(TestAction::make('registerInZoom')->table($submission));
        $this->assertSame([self::DAY_26], $this->zoomWebinarsRegisteredIn());

        // Ya completo, la acción reenvía a todas las sesiones que eligió.
        $page->callAction(TestAction::make('registerInZoom')->table($submission->fresh()));
        $this->assertSame([self::DAY_26, self::DAY_25, self::DAY_26], $this->zoomWebinarsRegisteredIn());
    }

    public function test_row_action_keeps_registering_single_zoom_webinars(): void
    {
        $this->fakeZoom();
        $this->actingAsAdmin();
        $webinar = $this->singleZoomWebinar();
        $submission = Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'ana@templet.io']]);

        // La tabla no muestra nada hasta elegir cliente y webinar.
        Livewire::test(ListSubmissions::class)
            ->filterTable('submitted', ['client_id' => $this->client->id, 'webinar_id' => $webinar->id])
            ->callAction(TestAction::make('registerInZoom')->table($submission));

        $this->assertSame([self::SINGLE], $this->zoomWebinarsRegisteredIn());
        $this->assertNotNull($submission->fresh()->registered_in_zoom_at);
    }

    public function test_stats_api_reports_each_session_and_keeps_a_zoom_webinar_id(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('La API de stats cuenta correos con JSON_UNQUOTE, que solo existe en MySQL.');
        }

        $webinar = $this->sessionsWebinar();
        Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'ana@templet.io'], 'sessions' => [self::DAY_25, self::DAY_26]]);
        Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'luis@templet.io'], 'sessions' => [self::DAY_26]]);
        Attendee::create(['webinar_id' => $webinar->id, 'zoom_webinar_id' => self::DAY_25, 'zoom_participant_id' => '1', 'email' => 'ana@templet.io']);
        $this->singleZoomWebinar();
        [, $key] = ApiKey::generate('operator');

        $rows = collect($this->getJson('/api/v1/stats', ['X-Api-Key' => $key])->assertOk()->json('data'))->keyBy('webinar.slug');

        $this->assertSame(self::DAY_25, $rows['fasttrack-gen-ai']['webinar']['zoom_webinar_id']);
        $this->assertSame([1, 2], array_column($rows['fasttrack-gen-ai']['sessions'], 'registrations'));
        $this->assertSame([1, 0], array_column($rows['fasttrack-gen-ai']['sessions'], 'unique_attendees'));

        $this->assertSame(self::SINGLE, $rows['infraestructura']['webinar']['zoom_webinar_id']);
        $this->assertSame([], $rows['infraestructura']['sessions']);
    }
}
