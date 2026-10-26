<?php

namespace Tests\Feature;

use App\Filament\Resources\Webinars\Pages\EditWebinar;
use App\Models\Client;
use App\Models\User;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class WebinarZoomSelectTest extends TestCase
{
    use RefreshDatabase;

    private const EXPIRED_ID = '86831590568';
    private const UPCOMING_ID = '88281699851';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 12:00:00');

        // Zoom lista solo los próximos; el expirado ya solo existe en su reporte.
        Http::fake([
            'zoom.us/oauth/token' => Http::response(['access_token' => 'token']),
            'api.zoom.us/v2/users/me/webinars*' => Http::response(['webinars' => [
                ['id' => (int) self::UPCOMING_ID, 'topic' => 'Kiro ', 'start_time' => '2026-11-19T15:00:00Z', 'duration' => 60, 'timezone' => 'America/Bogota'],
            ]]),
            'api.zoom.us/v2/webinars/'.self::EXPIRED_ID => Http::response(['code' => 3001], 404),
            'api.zoom.us/v2/report/webinars/'.self::EXPIRED_ID => Http::response([
                'topic' => 'Infraestructura', 'start_time' => '2026-07-23T14:42:26Z', 'end_time' => '2026-07-23T16:00:00Z',
            ]),
            'api.zoom.us/*' => Http::response([], 404),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function edit(array $attributes = []): Testable
    {
        $client = Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Infraestructura',
            'slug' => 'infraestructura',
            ...$attributes,
        ]);

        return Livewire::test(EditWebinar::class, ['record' => $webinar->getRouteKey()]);
    }

    private function zoomSelect(Testable $page, string $method, array $arguments = []): array
    {
        return collect($page->instance()->callSchemaComponentMethod('form.zoom_webinar_id', $method, $arguments))
            ->pluck('label', 'value')
            ->all();
    }

    public function test_the_select_lists_upcoming_zoom_webinars_with_their_date(): void
    {
        $this->assertSame(
            [self::UPCOMING_ID => 'Kiro — 19 - 11 - 2026'],
            $this->zoomSelect($this->edit(), 'getOptionsForJs'),
        );
    }

    public function test_an_expired_zoom_webinar_is_found_by_id(): void
    {
        $this->assertSame(
            [self::EXPIRED_ID => 'Infraestructura — 23 - 07 - 2026 (Ended)'],
            $this->zoomSelect($this->edit(), 'getSearchResultsForJs', ['search' => '868 3159 0568']),
        );
    }

    public function test_choosing_a_zoom_webinar_fills_an_empty_date(): void
    {
        $this->edit()
            ->fillForm(['zoom_webinar_id' => self::EXPIRED_ID])
            ->assertSchemaStateSet(['event_date' => '2026-07-23']);
    }

    public function test_an_expired_zoom_webinar_can_be_saved(): void
    {
        $this->edit()
            ->fillForm(['zoom_webinar_id' => self::EXPIRED_ID, 'campaign' => 'LATAM_Reg_Web_AWS_Ent_Jul2026'])
            ->call('save')
            ->assertHasNoFormErrors();

        $webinar = Webinar::firstOrFail();
        $this->assertSame(self::EXPIRED_ID, $webinar->zoom_webinar_id);
        $this->assertSame('LATAM_Reg_Web_AWS_Ent_Jul2026', $webinar->campaign);
        $this->assertSame('2026-07-23', $webinar->event_date->toDateString());
    }

    public function test_an_unknown_zoom_id_is_rejected(): void
    {
        $this->edit()
            ->fillForm(['zoom_webinar_id' => '11111111111'])
            ->call('save')
            ->assertHasFormErrors(['zoom_webinar_id']);
    }

    public function test_a_saved_zoom_id_that_zoom_no_longer_knows_still_saves(): void
    {
        $this->edit(['zoom_webinar_id' => '11111111111'])
            ->fillForm(['campaign' => 'LATAM_Reg_Web_AWS_Ent_Jul2026'])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
