<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Survey;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageUrlTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);
    }

    public function test_the_public_url_is_the_one_the_page_is_published_at(): void
    {
        $client = $this->client();

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Identidad como perímetro',
            'slug' => 'identidad-como-perimetro-ia',
        ]);

        $survey = Survey::create([
            'client_id' => $client->id,
            'title' => 'Encuesta',
            'slug' => 'satisfaccion',
        ]);

        $this->assertSame(url('/libertynet/identidad-como-perimetro-ia'), $webinar->publicUrl());
        $this->assertSame(url('/libertynet/surveys/satisfaccion'), $survey->publicUrl());
    }

    public function test_the_public_url_responds(): void
    {
        $client = $this->client();

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Identidad como perímetro',
            'slug' => 'identidad-como-perimetro-ia',
        ]);

        $this->get($webinar->publicUrl())->assertOk();
    }

    public function test_the_preview_url_goes_through_the_panel(): void
    {
        $client = $this->client();

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Webinar',
            'slug' => 'mi-webinar',
        ]);

        $survey = Survey::create([
            'client_id' => $client->id,
            'title' => 'Encuesta',
            'slug' => 'mi-encuesta',
        ]);

        // Así la vista previa no dispara los pixeles de la página real.
        $this->assertSame(url("/previews/webinars/{$webinar->id}"), $webinar->previewUrl());
        $this->assertSame(url("/previews/surveys/{$survey->id}"), $survey->previewUrl());
    }

    public function test_records_without_a_client_have_no_public_url(): void
    {
        $webinar = new Webinar(['title' => 'Suelto', 'slug' => 'suelto']);

        $this->assertNull($webinar->publicUrl());
        $this->assertNull($webinar->previewUrl());
    }

    public function test_a_client_has_no_public_page_of_its_own(): void
    {
        $client = $this->client();

        $this->assertNull($client->publicUrl());
        $this->assertSame(url("/previews/clients/{$client->id}"), $client->previewUrl());
    }
}
