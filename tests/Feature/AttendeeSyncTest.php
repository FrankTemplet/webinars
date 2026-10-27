<?php

namespace Tests\Feature;

use App\Filament\Pages\WebinarAttendees;
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

class AttendeeSyncTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT = 'api.zoom.us/v2/report/webinars/86831590568/participants*';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 12:00:00');

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function webinar(): Webinar
    {
        $client = Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);

        return Webinar::create([
            'client_id' => $client->id,
            'title' => 'Infraestructura',
            'slug' => 'infraestructura',
            'event_date' => '2026-07-23',
            'zoom_webinar_id' => '86831590568',
        ]);
    }

    /**
     * Como lo manda Zoom: `id` vacío para quien entra sin cuenta, `user_id`
     * distinto por sesión.
     */
    private function zoomSession(string $userId, string $email, string $joinTime, int $duration): array
    {
        return ['id' => '', 'user_id' => $userId, 'name' => $email, 'user_email' => $email, 'join_time' => $joinTime, 'duration' => $duration];
    }

    private function fakeZoom(mixed $report): void
    {
        Http::fake([
            'zoom.us/oauth/token' => Http::response(['access_token' => 'token']),
            self::REPORT => $report,
            'api.zoom.us/*' => Http::response([], 404),
        ]);
    }

    public function test_two_sessions_with_the_same_email_and_join_time_are_both_kept(): void
    {
        $this->fakeZoom(Http::response(['participants' => [
            $this->zoomSession('16778240', 'j.acevedo@templet.io', '2026-07-23T14:42:26Z', 4569),
            $this->zoomSession('16779264', 'j.acevedo@templet.io', '2026-07-23T14:42:26Z', 84),
        ]]));
        $webinar = $this->webinar();

        SyncAttendeesJob::dispatchSync($webinar->id);

        $this->assertEqualsCanonicalizing([4569, 84], $webinar->attendees()->pluck('duration')->all());
    }

    public function test_each_reconnection_of_a_signed_in_user_is_kept(): void
    {
        // Quien entra con cuenta trae el mismo `id` en cada sesión.
        $this->fakeZoom(Http::response(['participants' => [
            ['id' => 'gjuzphV0QmWDO', 'user_id' => '16778240', 'user_email' => 'j.acevedo@templet.io', 'join_time' => '2026-07-23T14:42:26Z', 'duration' => 4569],
            ['id' => 'gjuzphV0QmWDO', 'user_id' => '67188736', 'user_email' => 'j.acevedo@templet.io', 'join_time' => '2026-07-23T15:08:42Z', 'duration' => 84],
        ]]));
        $webinar = $this->webinar();

        SyncAttendeesJob::dispatchSync($webinar->id);

        $this->assertEqualsCanonicalizing([4569, 84], $webinar->attendees()->pluck('duration')->all());
    }

    public function test_a_resync_replaces_rows_saved_with_the_old_key_and_records_the_sync(): void
    {
        $this->fakeZoom(Http::response(['participants' => [
            $this->zoomSession('16778240', 'ana@cliente.com', '2026-07-23T15:00:00Z', 3600),
        ]]));
        $webinar = $this->webinar();

        // Guardado antes con la llave vieja (correo + hora de entrada).
        Attendee::create([
            'webinar_id' => $webinar->id,
            'zoom_participant_id' => md5('ana@cliente.com2026-07-23T15:00:00Z'),
            'email' => 'ana@cliente.com',
            'duration' => 3600,
        ]);

        SyncAttendeesJob::dispatchSync($webinar->id);

        $this->assertSame(['16778240'], $webinar->attendees()->pluck('zoom_participant_id')->all());

        $webinar->refresh();
        $this->assertSame('2026-10-26 12:00:00', $webinar->attendees_synced_at->toDateTimeString());
        $this->assertSame(1, $webinar->zoom_participants_count);
    }

    public function test_a_failed_report_changes_nothing(): void
    {
        $this->fakeZoom(Http::response(['code' => 300], 500));
        $webinar = $this->webinar();
        Attendee::create(['webinar_id' => $webinar->id, 'zoom_participant_id' => 'old', 'email' => 'ana@cliente.com']);

        SyncAttendeesJob::dispatchSync($webinar->id);

        $this->assertSame(1, $webinar->attendees()->count());
        $this->assertNull($webinar->refresh()->attendees_synced_at);
    }

    public function test_an_empty_report_does_not_delete_what_was_saved(): void
    {
        $this->fakeZoom(Http::response(['participants' => []]));
        $webinar = $this->webinar();
        Attendee::create(['webinar_id' => $webinar->id, 'zoom_participant_id' => 'old', 'email' => 'ana@cliente.com']);

        SyncAttendeesJob::dispatchSync($webinar->id);

        $this->assertSame(1, $webinar->attendees()->count());
    }

    public function test_the_page_says_when_it_was_last_synced_and_can_sync_again(): void
    {
        $this->fakeZoom(Http::response(['participants' => [
            $this->zoomSession('16778240', 'ana@cliente.com', '2026-07-23T15:00:00Z', 3600),
            $this->zoomSession('16779264', 'ana@cliente.com', '2026-07-23T15:30:00Z', 600),
        ]]));
        $webinar = $this->webinar();
        SyncAttendeesJob::dispatchSync($webinar->id);

        Livewire::test(WebinarAttendees::class)
            ->fillForm(['webinar_id' => $webinar->id])
            ->assertSee('Took place on 23 - 07 - 2026 · Last synced 26 - 10 - 2026 12:00 UTC')
            ->assertSee('of 2 reported by Zoom')
            ->assertActionVisible('sync');
    }

    public function test_rows_synced_before_tracking_ask_for_a_resync(): void
    {
        $webinar = $this->webinar();
        Attendee::create(['webinar_id' => $webinar->id, 'zoom_participant_id' => 'old', 'email' => 'ana@cliente.com']);

        Livewire::test(WebinarAttendees::class)
            ->fillForm(['webinar_id' => $webinar->id])
            ->assertSee('Synced before sync tracking existed');
    }
}
