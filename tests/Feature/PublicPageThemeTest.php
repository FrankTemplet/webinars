<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Survey;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageThemeTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Escala 24x7',
            'slug' => 'escala',
            'meta_access_token' => 'secreto-de-meta',
        ], $attributes));
    }

    private function webinar(Client $client, array $attributes = []): Webinar
    {
        return Webinar::create(array_merge([
            'client_id' => $client->id,
            'title' => 'Test Webinar',
            'slug' => 'test-webinar',
        ], $attributes));
    }

    public function test_without_any_style_the_template_values_are_used(): void
    {
        $webinar = $this->webinar($this->client());

        $this->get("/escala/{$webinar->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('webinar.theme.vars.--accent', '#00B0D3')
                ->where('webinar.theme.vars.--font', "'Roboto', sans-serif")
                ->where('webinar.theme.vars.--radius-field', '5px')
                ->where('webinar.theme.custom_css', null));
    }

    public function test_the_page_inherits_the_client_style(): void
    {
        $client = $this->client([
            'font_family' => 'Maven Pro',
            'accent_color' => '#FF6000',
            'heading_color' => '#010812',
            'button_radius' => 6,
        ]);

        $webinar = $this->webinar($client);

        $this->get("/escala/{$webinar->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('webinar.theme.vars.--accent', '#FF6000')
                ->where('webinar.theme.vars.--heading', '#010812')
                ->where('webinar.theme.vars.--radius-button', '6px')
                ->where('webinar.theme.vars.--font', "'Maven Pro', sans-serif")
                ->where('webinar.theme.font_url', 'https://fonts.bunny.net/css?family=maven-pro:400,500,600,700,800'));
    }

    public function test_the_page_overrides_only_what_it_sets(): void
    {
        $client = $this->client([
            'font_family' => 'Maven Pro',
            'accent_color' => '#FF6000',
            'heading_color' => '#010812',
            'field_radius' => 10,
        ]);

        $webinar = $this->webinar($client, [
            'accent_color' => '#00B0D3',
            'button_radius' => 0,
        ]);

        $this->get("/escala/{$webinar->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Lo que la página define gana.
                ->where('webinar.theme.vars.--accent', '#00B0D3')
                ->where('webinar.theme.vars.--radius-button', '0px')
                // Lo demás sigue siendo del cliente.
                ->where('webinar.theme.vars.--heading', '#010812')
                ->where('webinar.theme.vars.--radius-field', '10px')
                ->where('webinar.theme.vars.--font', "'Maven Pro', sans-serif"));
    }

    public function test_custom_css_stacks_client_first_then_page(): void
    {
        $client = $this->client(['custom_css' => '.webinar-page { --field-border: #707070; }']);
        $webinar = $this->webinar($client, ['custom_css' => '.btn-submit { padding: 10px 24px; }']);

        $css = $webinar->resolvedCustomCss();

        $this->assertStringContainsString('--field-border', $css);
        $this->assertStringContainsString('padding: 10px 24px', $css);
        $this->assertLessThan(
            strpos($css, 'padding: 10px 24px'),
            strpos($css, '--field-border'),
            'El CSS del cliente debe ir antes que el de la página.'
        );
    }

    public function test_custom_css_cannot_close_the_style_tag(): void
    {
        $webinar = $this->webinar($this->client(), [
            'custom_css' => 'body { color: red; }</style><script>alert(1)</script>',
        ]);

        $css = $webinar->resolvedCustomCss();

        $this->assertStringNotContainsString('</style', $css);
        $this->assertStringContainsString('body { color: red; }', $css);
    }

    public function test_an_unknown_font_falls_back_instead_of_breaking_the_page(): void
    {
        $client = $this->client(['font_family' => 'Comic Sans']);
        $webinar = $this->webinar($client);

        $this->get("/escala/{$webinar->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('webinar.theme.vars.--font', "'Roboto', sans-serif"));
    }

    public function test_surveys_inherit_the_same_client_style(): void
    {
        $client = $this->client(['accent_color' => '#00A86B', 'font_family' => 'Inter']);

        $survey = Survey::create([
            'client_id' => $client->id,
            'title' => 'Satisfacción',
            'slug' => 'satisfaccion',
        ]);

        $this->get("/escala/surveys/{$survey->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('survey.theme.vars.--accent', '#00A86B')
                ->where('survey.theme.vars.--font', "'Inter', sans-serif")
                // La encuesta conserva los radios de su propia plantilla.
                ->where('survey.theme.vars.--radius-field', '10px'));
    }

    public function test_the_public_payload_hides_server_only_credentials(): void
    {
        $client = $this->client();
        $webinar = $this->webinar($client, [
            'clay_webhook_url' => 'https://hooks.clay.com/secreto',
            'tracking_scripts' => [[
                'platform' => 'facebook',
                'pixel_id' => '123',
                'access_token' => 'token-de-conversiones',
                'enabled' => true,
            ]],
        ]);

        $response = $this->get("/escala/{$webinar->slug}");

        $response->assertOk();
        $response->assertDontSee('token-de-conversiones');
        $response->assertDontSee('hooks.clay.com', false);
        $response->assertDontSee('secreto-de-meta');
        $response->assertInertia(fn ($page) => $page->where('webinar.tracking_scripts.0.pixel_id', '123'));
    }
}
