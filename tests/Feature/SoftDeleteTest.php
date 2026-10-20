<?php

namespace Tests\Feature;

use App\Filament\Resources\Webinars\Pages\ListWebinars;
use App\Models\Client;
use App\Models\Submission;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function webinarWithSubmissions(int $count = 3): Webinar
    {
        $client = Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Identidad como perímetro',
            'slug' => 'identidad-como-perimetro-ia',
        ]);

        foreach (range(1, $count) as $i) {
            Submission::create([
                'webinar_id' => $webinar->id,
                'data' => ['email' => "lead{$i}@example.com"],
            ]);
        }

        return $webinar;
    }

    public function test_deleting_a_webinar_no_longer_takes_its_submissions_down(): void
    {
        $webinar = $this->webinarWithSubmissions();

        $webinar->delete();

        $this->assertSoftDeleted($webinar);
        $this->assertSame(3, Submission::withoutGlobalScopes()->where('webinar_id', $webinar->id)->count());
    }

    public function test_a_deleted_webinar_can_be_restored_with_everything(): void
    {
        $webinar = $this->webinarWithSubmissions();

        $webinar->delete();
        $webinar->restore();

        $this->assertNull($webinar->fresh()->deleted_at);
        $this->assertSame(3, $webinar->submissions()->count());
    }

    public function test_force_deleting_still_removes_the_children(): void
    {
        $webinar = $this->webinarWithSubmissions();

        $webinar->forceDelete();

        $this->assertSame(0, Submission::withoutGlobalScopes()->where('webinar_id', $webinar->id)->count());
    }

    public function test_a_deleted_webinar_disappears_from_queries_and_from_its_public_page(): void
    {
        $webinar = $this->webinarWithSubmissions();
        $url = $webinar->publicUrl();

        $this->get($url)->assertOk();

        $webinar->delete();

        $this->assertSame(0, Webinar::count());
        $this->assertSame(1, Webinar::withTrashed()->count());
        $this->get($url)->assertNotFound();
    }

    public function test_deleting_a_survey_keeps_its_responses(): void
    {
        $client = Client::create(['name' => 'Escala', 'slug' => 'escala']);

        $survey = Survey::create([
            'client_id' => $client->id,
            'title' => 'Satisfacción',
            'slug' => 'satisfaccion',
        ]);

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'experience_rating' => 5,
            'wants_review' => true,
        ]);

        $survey->delete();

        $this->assertSoftDeleted($survey);
        $this->assertSame(1, SurveyResponse::withoutGlobalScopes()->where('survey_id', $survey->id)->count());

        $survey->restore();

        $this->assertSame(1, $survey->responses()->count());
    }

    public function test_deleting_a_client_hides_its_pages_without_losing_them(): void
    {
        $webinar = $this->webinarWithSubmissions();
        $client = $webinar->client;

        $client->delete();

        $this->assertSoftDeleted($client);
        $this->assertSame(1, Webinar::withoutGlobalScopes()->count());
        $this->get($webinar->publicUrl())->assertNotFound();
    }

    public function test_the_panel_hides_deleted_records_but_can_list_them(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $kept = $this->webinarWithSubmissions();

        $gone = Webinar::create([
            'client_id' => $kept->client_id,
            'title' => 'Borrado',
            'slug' => 'borrado',
        ]);
        $gone->delete();

        Livewire::test(ListWebinars::class)
            ->assertCanSeeTableRecords([$kept])
            ->assertCanNotSeeTableRecords([$gone])
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$gone]);
    }

    public function test_the_panel_can_restore_a_deleted_webinar(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $webinar = $this->webinarWithSubmissions();
        $webinar->delete();

        Livewire::test(ListWebinars::class)
            ->filterTable('trashed', false)
            ->callTableAction('restore', $webinar);

        $this->assertNull($webinar->fresh()->deleted_at);
        $this->assertSame(3, $webinar->submissions()->count());
    }
}
