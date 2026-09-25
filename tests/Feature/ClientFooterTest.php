<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Survey;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFooterTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Escala 24x7',
            'slug' => 'escala',
        ], $attributes));
    }

    public function test_footer_is_null_when_the_client_has_not_set_one(): void
    {
        $this->assertNull($this->createClient()->footer);
        $this->assertNull($this->createClient(['slug' => 'otro', 'footer_text' => '   '])->footer);
    }

    public function test_footer_resolves_year_and_client_placeholders(): void
    {
        $client = $this->createClient([
            'footer_text' => 'Copyright {year}. {client}™ Cloud Done Right ®. Todos los derechos reservados.',
        ]);

        $this->assertSame(
            'Copyright '.now()->year.'. Escala 24x7™ Cloud Done Right ®. Todos los derechos reservados.',
            $client->footer
        );
    }

    public function test_survey_page_uses_the_client_footer(): void
    {
        $client = $this->createClient(['footer_text' => 'Pie propio de {client}']);
        $survey = Survey::create([
            'client_id' => $client->id,
            'title' => 'Test Survey',
            'slug' => 'test-survey',
        ]);

        $this->get("/{$client->slug}/surveys/{$survey->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('client.footer', 'Pie propio de Escala 24x7'));
    }

    public function test_webinar_page_uses_the_client_footer(): void
    {
        $client = $this->createClient(['footer_text' => 'Pie propio de {client}']);
        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Test Webinar',
            'slug' => 'test-webinar',
            'form_schema' => [
                ['type' => 'email', 'label' => 'Email', 'name' => 'email', 'required' => true],
            ],
        ]);

        $this->get("/{$client->slug}/{$webinar->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('client.footer', 'Pie propio de Escala 24x7'));
    }

    public function test_footer_is_not_exposed_through_the_public_api(): void
    {
        $client = $this->createClient(['footer_text' => 'Pie interno']);
        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Test Webinar',
            'slug' => 'test-webinar',
        ]);

        $payload = (new \App\Http\Resources\SubmissionResource(
            \App\Models\Submission::create([
                'webinar_id' => $webinar->id,
                'data' => ['email' => 'ana@example.com'],
            ])->load('webinar.client')
        ))->toArray(request());

        $this->assertSame(
            ['id', 'name', 'slug'],
            array_keys($payload['webinar']['client'])
        );
    }
}
