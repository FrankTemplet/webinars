<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponses\Pages\ListSurveyResponses;
use App\Filament\Resources\SurveyResponses\Schemas\SurveyResponseForm;
use App\Filament\Resources\Webinars\Pages\CreateWebinar;
use App\Filament\Resources\Webinars\Pages\EditWebinar;
use App\Models\Client;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPublicPageActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedClientWithContent(): array
    {
        $client = Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Más allá del firewall',
            'slug' => 'mas-alla-del-firewall',
        ]);

        $survey = Survey::create([
            'client_id' => $client->id,
            'webinar_id' => $webinar->id,
            'title' => 'Satisfacción firewall',
            'slug' => 'satisfaccion-firewall',
        ]);

        return [$client, $webinar, $survey];
    }

    public function test_webinar_list_offers_preview_and_live(): void
    {
        $this->actingAs($this->admin());
        [, $webinar] = $this->seedClientWithContent();

        $response = $this->get('/admin/webinars');

        $response->assertOk();
        $response->assertSee('Preview');
        $response->assertSee('Live');
        $response->assertSee($webinar->publicUrl(), false);
    }

    public function test_survey_list_offers_preview_and_live(): void
    {
        $this->actingAs($this->admin());
        [, , $survey] = $this->seedClientWithContent();

        $response = $this->get('/admin/surveys');

        $response->assertOk();
        $response->assertSee('Preview');
        $response->assertSee('Live');
        $response->assertSee($survey->publicUrl(), false);
    }

    public function test_the_new_webinar_form_offers_a_preview(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get('/admin/webinars/create');

        $response->assertOk();
        $response->assertSee('Preview');
    }

    public function test_both_the_new_and_the_edit_form_expose_the_preview_action(): void
    {
        $this->actingAs($this->admin());
        [, $webinar] = $this->seedClientWithContent();

        Livewire::test(CreateWebinar::class)->assertActionExists('preview');

        Livewire::test(EditWebinar::class, ['record' => $webinar->getRouteKey()])
            ->assertActionExists('preview');
    }

    public function test_the_client_form_offers_a_brand_preview(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get('/admin/clients/create');

        $response->assertOk();
        $response->assertSee('Preview');
        $response->assertSee('Custom CSS');
    }

    public function test_the_page_style_panel_only_offers_the_overrides(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get('/admin/webinars/create');

        $response->assertOk();
        $response->assertSee('Accent Color');
        $response->assertSee('Button Radius');
        // La tipografía y los colores de texto son del cliente.
        $response->assertDontSee('Body Text');
    }

    public function test_the_response_detail_resolves_the_survey_and_webinar_titles(): void
    {
        [, $webinar, $survey] = $this->seedClientWithContent();

        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'experience_rating' => 5,
            'wants_review' => true,
        ]);

        $data = SurveyResponseForm::withRelatedTitles([], $response->fresh());

        $this->assertSame($survey->title, $data['survey_title']);
        $this->assertSame($webinar->title, $data['webinar_title']);
    }

    public function test_a_response_whose_survey_has_no_webinar_leaves_it_empty(): void
    {
        $client = Client::create(['name' => 'Escala', 'slug' => 'escala']);

        $survey = Survey::create([
            'client_id' => $client->id,
            'title' => 'Suelta',
            'slug' => 'suelta',
        ]);

        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'experience_rating' => 4,
            'wants_review' => false,
        ]);

        $data = SurveyResponseForm::withRelatedTitles([], $response->fresh());

        $this->assertSame('Suelta', $data['survey_title']);
        $this->assertNull($data['webinar_title']);
    }

    public function test_the_response_modal_is_filled_with_both_titles(): void
    {
        $this->actingAs($this->admin());
        [, $webinar, $survey] = $this->seedClientWithContent();

        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'experience_rating' => 5,
            'wants_review' => true,
        ]);

        Livewire::test(ListSurveyResponses::class)
            ->mountTableAction('view', $response)
            ->assertTableActionDataSet([
                'survey_title' => $survey->title,
                'webinar_title' => $webinar->title,
            ]);
    }

    public function test_survey_responses_show_the_linked_webinar(): void
    {
        $this->actingAs($this->admin());
        [, $webinar, $survey] = $this->seedClientWithContent();

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'experience_rating' => 5,
            'wants_review' => true,
        ]);

        $response = $this->get('/admin/survey-responses');

        $response->assertOk();
        $response->assertSee('Webinar');
        $response->assertSee($webinar->title);
    }
}
