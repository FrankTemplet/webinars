<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Survey;
use App\Models\User;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'LibertyNet',
            'slug' => 'libertynet',
        ], $attributes));
    }

    public function test_preview_requires_a_session(): void
    {
        $this->get('/previews/webinars')->assertRedirect();
        $this->get('/previews/surveys')->assertRedirect();
    }

    public function test_a_new_webinar_previews_with_a_sample_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $client = $this->client(['accent_color' => '#FF6000']);

        $this->get("/previews/webinars?client={$client->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Webinar/Show')
                ->where('preview', true)
                // Toma el estilo del cliente aunque el webinar no exista aún.
                ->where('webinar.theme.vars.--accent', '#FF6000')
                ->has('webinar.form_schema', 3));
    }

    public function test_an_existing_webinar_previews_with_its_own_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $client = $this->client();

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Más allá del firewall',
            'slug' => 'mas-alla',
            'accent_color' => '#FF6000',
        ]);

        $this->get("/previews/webinars/{$webinar->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('webinar.title', 'Más allá del firewall')
                ->where('webinar.theme.vars.--accent', '#FF6000'));
    }

    public function test_the_preview_payload_carries_what_the_live_editor_needs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $client = $this->client([
            'accent_color' => '#FF6000',
            'font_family' => 'Maven Pro',
            'custom_css' => '.webinar-page { --field-border: #707070; }',
        ]);

        $this->get("/previews/webinars?client={$client->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Lo heredado llega resuelto para que el panel no tenga que
                // conocer los valores por defecto.
                ->where('webinar.theme.editor.inherited.--accent', '#FF6000')
                ->where('webinar.theme.editor.inherited_font', 'Maven Pro')
                ->where('webinar.theme.editor.inherited_css', '.webinar-page { --field-border: #707070; }')
                ->has('webinar.theme.editor.fonts.Maven Pro'));
    }

    public function test_a_survey_previews_for_its_client(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $client = $this->client();

        $survey = Survey::create([
            'client_id' => $client->id,
            'title' => 'Satisfacción',
            'slug' => 'satisfaccion',
        ]);

        $this->get("/previews/surveys/{$survey->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Survey/Show')->where('preview', true));
    }

    public function test_a_client_previews_its_brand_on_a_sample_landing(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $client = $this->client(['accent_color' => '#00A86B']);

        $this->get("/previews/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Webinar/Show')
                ->where('webinar.theme.vars.--accent', '#00A86B'));
    }

    public function test_the_preview_never_posts_anywhere(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/previews/webinars')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('submitUrl', ''));
    }
}
