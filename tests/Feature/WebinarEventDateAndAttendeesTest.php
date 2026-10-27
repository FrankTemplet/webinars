<?php

namespace Tests\Feature;

use App\Filament\Pages\WebinarAttendees;
use App\Filament\Resources\Webinars\Pages\EditWebinar;
use App\Jobs\SyncAttendeesJob;
use App\Models\Attendee;
use App\Models\Client;
use App\Models\User;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WebinarEventDateAndAttendeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 12:00:00');

        Http::fake([
            'zoom.us/oauth/token' => Http::response(['access_token' => 'token']),
            'api.zoom.us/v2/report/webinars/*/participants*' => Http::response(['participants' => [
                ['id' => 'p1', 'name' => 'Ana', 'user_email' => 'ana@cliente.com', 'join_time' => '2026-07-23T15:00:00Z', 'duration' => 3600],
                ['id' => 'p2', 'name' => 'Mariana', 'user_email' => 'mariana@templet.io', 'join_time' => '2026-07-23T15:01:00Z', 'duration' => 3500],
            ]]),
            'api.zoom.us/*' => Http::response([], 404),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function webinar(array $attributes = []): Webinar
    {
        $client = Client::firstOrCreate(['slug' => 'libertynet'], ['name' => 'LibertyNet']);

        return Webinar::create([
            'client_id' => $client->id,
            'title' => 'Infraestructura',
            'slug' => 'infraestructura-'.Webinar::withoutGlobalScopes()->count(),
            ...$attributes,
        ]);
    }

    public function test_the_date_summary_says_when_the_webinar_is(): void
    {
        $this->assertNull($this->webinar()->eventDateSummary());
        $this->assertSame('Took place on 23 - 07 - 2026', $this->webinar(['event_date' => '2026-07-23'])->eventDateSummary());
        $this->assertSame('Takes place today (26 - 10 - 2026)', $this->webinar(['event_date' => '2026-10-26'])->eventDateSummary());
        $this->assertSame('Upcoming · 19 - 11 - 2026', $this->webinar(['event_date' => '2026-11-19'])->eventDateSummary());
    }

    public function test_the_edit_page_shows_the_date_summary(): void
    {
        $webinar = $this->webinar(['event_date' => '2026-07-23']);

        Livewire::test(EditWebinar::class, ['record' => $webinar->getRouteKey()])
            ->assertSee('Took place on 23 - 07 - 2026');
    }

    public function test_attendance_comes_from_stored_attendees_without_internal_ones(): void
    {
        $webinar = $this->webinar(['zoom_webinar_id' => '86831590568']);

        SyncAttendeesJob::dispatchSync($webinar->id);

        $this->assertSame(2, $webinar->attendees()->count());
        $this->assertSame(1, $webinar->uniqueExternalAttendees());

        // Zoom ya no responde por el webinar: el conteo no cambia.
        Http::fake(['*' => Http::response([], 404)]);
        $this->assertSame(1, $webinar->fresh()->uniqueExternalAttendees());
    }

    public function test_the_attendees_page_offers_sync_until_there_are_attendees(): void
    {
        $webinar = $this->webinar(['zoom_webinar_id' => '86198352801', 'event_date' => '2026-02-19']);

        Livewire::test(WebinarAttendees::class)
            ->assertSee('Select a webinar')
            ->fillForm(['client_id' => $webinar->client_id, 'webinar_id' => $webinar->id])
            ->assertSee('been synced from Zoom yet')
            ->assertActionVisible('sync')
            ->callAction('sync')
            ->assertDontSee('been synced from Zoom yet')
            ->assertSee('Took place on 19 - 02 - 2026')
            ->assertCanSeeTableRecords(Attendee::all())
            ->assertSee('ana@cliente.com');
    }

    public function test_only_webinars_from_before_today_are_offered(): void
    {
        $past = $this->webinar(['title' => 'Pasado', 'event_date' => '2026-10-25']);
        $this->webinar(['title' => 'Hoy', 'event_date' => '2026-10-26']);
        $this->webinar(['title' => 'Futuro', 'event_date' => '2026-11-19']);
        $this->webinar(['title' => 'Sin fecha']);

        $options = collect(
            Livewire::test(WebinarAttendees::class)
                ->instance()
                ->callSchemaComponentMethod('form.webinar_id', 'getOptionsForJs')
        )->pluck('label', 'value')->all();

        $this->assertSame([(string) $past->id => 'Pasado — 25 - 10 - 2026'], $options);
    }

    public function test_a_webinar_without_zoom_says_so_instead_of_offering_sync(): void
    {
        $webinar = $this->webinar(['event_date' => '2026-07-23']);

        Livewire::test(WebinarAttendees::class)
            ->fillForm(['client_id' => $webinar->client_id, 'webinar_id' => $webinar->id])
            ->assertSee('No Zoom webinar linked')
            ->assertActionHidden('sync');
    }

    public function test_only_admins_can_sync(): void
    {
        $webinar = $this->webinar(['zoom_webinar_id' => '86198352801', 'event_date' => '2026-02-19']);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $viewer->clients()->attach($webinar->client_id);
        $this->actingAs($viewer);

        Livewire::test(WebinarAttendees::class)
            ->fillForm(['webinar_id' => $webinar->id])
            ->assertActionHidden('sync');
    }

    public function test_the_scheduled_sync_skips_webinars_older_than_thirty_days(): void
    {
        $old = $this->webinar(['zoom_webinar_id' => '1', 'event_date' => '2026-07-23']);
        $recent = $this->webinar(['zoom_webinar_id' => '2', 'event_date' => '2026-10-21']);
        $undated = $this->webinar(['zoom_webinar_id' => '3']);
        $upcoming = $this->webinar(['zoom_webinar_id' => '4', 'event_date' => '2026-11-19']);

        SyncAttendeesJob::dispatchSync();

        $this->assertSame(0, $old->attendees()->count());
        $this->assertSame(2, $recent->attendees()->count());
        $this->assertSame(2, $undated->attendees()->count());
        $this->assertSame(0, $upcoming->attendees()->count());

        // Pedido a mano, sí se sincroniza aunque sea viejo.
        SyncAttendeesJob::dispatchSync($old->id);
        $this->assertSame(2, $old->attendees()->count());
    }
}
