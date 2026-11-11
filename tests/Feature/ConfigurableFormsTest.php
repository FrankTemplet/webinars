<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponses\Pages\ListSurveyResponses;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Models\Client;
use App\Models\Submission;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfigurableFormsTest extends TestCase
{
    use RefreshDatabase;

    private function survey(array $attributes = []): Survey
    {
        $client = Client::create(['name' => 'Client', 'slug' => 'client']);

        return Survey::create(array_merge([
            'client_id' => $client->id, 'title' => 'Survey', 'slug' => 'survey',
            'question_schema' => [['name' => 'topics', 'label' => 'Topics', 'type' => 'multiselect', 'required' => true, 'options' => ['Cloud', 'Security']]],
            'contact_schema' => [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'hidden' => true],
                ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => true],
            ],
        ], $attributes));
    }

    public function test_dynamic_answers_keep_their_labels_after_a_question_is_removed(): void
    {
        $survey = $this->survey();
        $this->post('/client/surveys/survey', [
            'answers' => ['topics' => ['Cloud', 'Security'], 'removed' => 'ignore'],
            'contact' => ['company' => 'Acme', 'email' => 'hidden@example.com'],
        ])->assertSessionHasNoErrors();
        $response = SurveyResponse::firstOrFail();
        $this->assertSame(['topics' => ['Cloud', 'Security']], $response->answers);
        $this->assertSame(['company' => 'Acme'], $response->contact_answers);
        $this->assertNull($response->email);
        $survey->update(['question_schema' => [['name' => 'new', 'label' => 'New', 'type' => 'text']]]);
        $entries = collect($response->fresh()->answerEntries())->keyBy('name');
        $this->assertSame('Topics', $entries['questions.topics']['label']);
        $this->assertSame('Cloud, Security', $entries['questions.topics']['value']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $page = Livewire::test(ListSurveyResponses::class)->mountTableAction('view', $response);
        $this->assertNotEmpty($page->instance()->mountedActions);
        $this->assertStringContainsString('Cloud, Security', $page->instance()->getSchema($page->instance()->getMountedActionSchemaName())->toHtml());
    }

    public function test_multiple_choice_rejects_empty_duplicate_unknown_and_scalar_answers(): void
    {
        $this->survey();
        foreach ([[], ['Cloud', 'Cloud'], ['Unknown'], 'Cloud'] as $value) {
            $this->post('/client/surveys/survey', ['answers' => ['topics' => $value], 'contact' => ['company' => 'Acme']])
                ->assertSessionHasErrors();
        }
        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_contact_toggle_skips_required_fields_and_new_question_types_do_not_fill_old_columns(): void
    {
        $this->survey([
            'contact_enabled' => false,
            'question_schema' => [['name' => 'experience_rating', 'label' => 'Comment', 'type' => 'text', 'required' => true]],
        ]);
        $this->post('/client/surveys/survey', ['answers' => ['experience_rating' => 'Great']])->assertSessionHasNoErrors();
        $response = SurveyResponse::firstOrFail();
        $this->assertNull($response->experience_rating);
        $this->assertSame(['experience_rating' => 'Great'], $response->answers);
        $this->assertSame([], $response->contact_answers);
    }

    public function test_contact_validation_and_guest_rows(): void
    {
        $this->survey(['question_schema' => [
            ['name' => 'guests', 'label' => 'Guests', 'type' => 'guests', 'guests_count' => 2],
            ['name' => 'rating', 'label' => 'Rating', 'type' => 'rating', 'required' => true],
        ]]);
        $this->post('/client/surveys/survey', ['answers' => ['rating' => 9], 'contact' => []])
            ->assertSessionHasErrors(['answers.rating', 'contact.company']);
        $this->post('/client/surveys/survey', [
            'contact' => ['company' => 'Acme'],
            'answers' => ['rating' => 4, 'guests' => [
                ['name' => ' Ana ', 'email' => 'ana@example.com', 'topics' => 'Cloud'],
                ['name' => '', 'email' => '', 'topics' => ''],
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertCount(1, SurveyResponse::firstOrFail()->guests);
    }

    public function test_webinar_multiple_choices_are_validated_and_stored_as_arrays(): void
    {
        $survey = $this->survey();
        Webinar::create(['client_id' => $survey->client_id, 'title' => 'Webinar', 'slug' => 'webinar', 'form_schema' => $survey->question_schema]);
        foreach ([[], ['Cloud', 'Cloud'], ['Unknown'], 'Cloud'] as $value) {
            $this->post('/client/webinar', ['topics' => $value])->assertSessionHasErrors();
        }
        $this->post('/client/webinar', ['topics' => ['Cloud', 'Security']])->assertSessionHasNoErrors();
        $this->assertSame(['Cloud', 'Security'], Submission::firstOrFail()->data['topics']);
    }

    public function test_admin_preserves_legacy_labels_and_requires_one_question(): void
    {
        $survey = $this->survey(['question_schema' => null, 'contact_schema' => null, 'q1_label' => 'Custom rating', 'q3_options' => ['A', 'B']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $page = Livewire::test(EditSurvey::class, ['record' => $survey->id]);
        $questions = array_values($page->get('data.question_schema'));
        $this->assertSame('Custom rating', $questions[0]['label']);
        $this->assertSame(['A', 'B'], $questions[2]['options']);
        $page->fillForm(['question_schema' => []])->call('save')->assertHasFormErrors(['question_schema']);
    }

    public function test_images_favicon_and_button_are_public_without_client_secrets(): void
    {
        $survey = $this->survey(['mobile_hero_image' => 'surveys/mobile.png']);
        $survey->client->update(['favicon' => 'clients/favicon.png', 'meta_access_token' => 'secret']);
        $this->get('/client/surveys/survey')->assertInertia(fn ($page) => $page
            ->where('survey.mobile_hero_image', 'surveys/mobile.png')->where('client.favicon', 'clients/favicon.png')->missing('client.meta_access_token'));
        Webinar::create(['client_id' => $survey->client_id, 'title' => 'Webinar', 'slug' => 'webinar', 'mobile_hero_image' => 'webinars/mobile.png', 'submit_label' => 'Quiero asistir']);
        $this->get('/client/webinar')->assertInertia(fn ($page) => $page
            ->where('webinar.mobile_hero_image', 'webinars/mobile.png')->where('webinar.submit_label', 'Quiero asistir'));
    }

    public function test_migration_preserves_custom_questions_and_historical_answers(): void
    {
        $migration = require database_path('migrations/2026_11_11_120000_add_public_form_configuration.php');
        $migration->down();
        $client = Client::create(['name' => 'Legacy', 'slug' => 'legacy']);
        $survey = Survey::create([
            'client_id' => $client->id, 'title' => 'Legacy survey', 'slug' => 'legacy',
            'q1_label' => 'Custom rating', 'q3_options' => ['A', 'B'], 'guests_count' => 0,
            'extra_questions' => [['name' => 'comment', 'label' => 'Comment', 'type' => 'text']],
        ]);
        $response = SurveyResponse::create([
            'survey_id' => $survey->id, 'name' => 'Ana', 'email' => 'ana@example.com',
            'experience_rating' => 4, 'stage' => 'B', 'wants_review' => true,
            'extra_answers' => ['comment' => 'Excellent'],
        ]);
        $migration->up();
        $survey->refresh();
        $this->assertCount(5, $survey->question_schema);
        $this->assertSame('Custom rating', $survey->question_schema[0]['label']);
        $this->assertSame(['A', 'B'], $survey->question_schema[2]['options']);
        $response->refresh();
        $this->assertSame(4, $response->answers['experience_rating']);
        $this->assertSame('Excellent', $response->answers['extra_comment']);
        $this->assertSame('Ana', $response->contact_answers['name']);
        $this->assertSame('Comment', $response->schema_snapshot['questions'][4]['label']);
    }

    public function test_preview_includes_unsaved_image_urls_and_mobile_controls(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $survey = $this->survey();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $page = Livewire::test(EditSurvey::class, ['record' => $survey->id])
            ->fillForm(['mobile_hero_image' => \Illuminate\Http\UploadedFile::fake()->image('mobile.png')])
            ->mountAction('preview');
        $html = $page->instance()->getMountedAction()->getModalContent()->render();
        $this->assertStringContainsString('device(390)', $html);
        $this->assertStringContainsString('device(1440)', $html);
        $this->assertStringContainsString('preview-file', $html);
        $this->assertNull($survey->fresh()->mobile_hero_image);
    }

    public function test_export_includes_removed_question_answers(): void
    {
        $survey = $this->survey();
        $this->post('/client/surveys/survey', [
            'answers' => ['topics' => ['Cloud', 'Security']], 'contact' => ['company' => 'Acme'],
        ])->assertSessionHasNoErrors();
        $survey->update(['question_schema' => [['name' => 'new', 'label' => 'New', 'type' => 'text']]]);
        $method = new \ReflectionMethod(\App\Filament\Resources\SurveyResponses\Tables\SurveyResponsesTable::class, 'getExcelColumns');
        $export = \pxlrbt\FilamentExcel\Exports\ExcelExport::make()->withColumns($method->invoke(null));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $export->hydrate(Livewire::test(ListSurveyResponses::class)->instance());
        $mapped = $export->map(SurveyResponse::firstOrFail());
        $this->assertStringContainsString('Topics: Cloud, Security', $mapped['dynamic_answers']);
        $this->assertStringContainsString('Company: Acme', $mapped['dynamic_answers']);
    }

    public function test_chart_counts_each_selected_option_once(): void
    {
        $survey = $this->survey();
        $webinar = Webinar::create(['client_id' => $survey->client_id, 'title' => 'Webinar', 'slug' => 'webinar']);
        Submission::create(['webinar_id' => $webinar->id, 'data' => ['topics' => ['Cloud', 'Security']]]);
        Submission::create(['webinar_id' => $webinar->id, 'data' => ['topics' => ['Cloud']]]);
        $widget = new \App\Filament\Widgets\DynamicFieldChartWidget;
        $widget->fieldName = 'topics';
        $widget->fieldType = 'multiselect';
        $widget->filters = ['webinar_id' => $webinar->id];
        $data = (new \ReflectionMethod($widget, 'getData'))->invoke($widget);
        $this->assertSame(['Cloud', 'Security'], $data['labels']);
        $this->assertSame([2, 1], $data['datasets'][0]['data']);
    }

    public function test_saved_questions_can_be_reordered_without_changing_their_keys(): void
    {
        $survey = $this->survey(['question_schema' => [
            ['name' => 'first', 'label' => 'First', 'type' => 'text'],
            ['name' => 'second', 'label' => 'Second', 'type' => 'radio', 'options' => ['A', 'B']],
            ['name' => 'third', 'label' => 'Third', 'type' => 'rating'],
        ]]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $page = Livewire::test(EditSurvey::class, ['record' => $survey->id]);
        $keys = array_keys($page->get('data.question_schema'));
        $page->callFormComponentAction('question_schema', 'reorder', arguments: ['items' => array_reverse($keys)])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(['third', 'second', 'first'], array_column($survey->fresh()->question_schema, 'name'));
        $this->assertSame(['A', 'B'], $survey->fresh()->question_schema[1]['options']);
    }

    public function test_mobile_header_repair_only_moves_the_identified_artwork(): void
    {
        $survey = $this->survey([
            'slug' => 'ataque-ia-de-la-exposicion-a-la-prevencion',
            'hero_image' => 'surveys/heroes/desktop.png',
            'header_logo' => 'surveys/logos/01M763K8XNN7VXRWX2TN54801N.png',
        ]);
        $survey->client->update(['slug' => 'libertynet']);
        $migration = require database_path('migrations/2026_11_11_130000_correct_ataque_ia_survey_mobile_header.php');
        $migration->up();
        $survey->refresh();
        $this->assertNull($survey->header_logo);
        $this->assertSame('surveys/logos/01M763K8XNN7VXRWX2TN54801N.png', $survey->mobile_hero_image);
        $this->assertSame('surveys/heroes/desktop.png', $survey->hero_image);
        $migration->up();
        $this->assertNull($survey->fresh()->header_logo);
        $migration->down();
        $this->assertNull($survey->fresh()->mobile_hero_image);
        $survey->update(['mobile_hero_image' => 'surveys/heroes/new-mobile.png']);
        $migration->up();
        $this->assertSame('surveys/heroes/new-mobile.png', $survey->fresh()->mobile_hero_image);
        $this->assertNotNull($survey->fresh()->header_logo);
    }

    public function test_contact_fields_can_be_reordered_and_saved(): void
    {
        $survey = $this->survey();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $page = Livewire::test(EditSurvey::class, ['record' => $survey->id]);
        $keys = array_keys($page->get('data.contact_schema'));
        $page->callFormComponentAction('contact_schema', 'reorder', arguments: ['items' => array_reverse($keys)])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(['company', 'email'], array_column($survey->fresh()->contact_schema, 'name'));
    }
}
