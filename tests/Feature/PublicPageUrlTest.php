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

    public function test_preview_url_points_at_this_application(): void
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

        $this->assertSame(url('/libertynet/mi-webinar'), $webinar->previewUrl());
        $this->assertSame(url('/libertynet/surveys/mi-encuesta'), $survey->previewUrl());
    }

    public function test_live_url_uses_the_client_subdomain_outside_local(): void
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

        // En local y en pruebas no hay subdominios, así que cae en el preview.
        $this->assertSame($webinar->previewUrl(), $webinar->liveUrl());

        app()->detectEnvironment(fn () => 'production');

        $this->assertSame(
            'https://libertynet.templet.io/webinars/mi-webinar',
            $webinar->liveUrl()
        );
        $this->assertSame(
            'https://libertynet.templet.io/surveys/mi-encuesta',
            $survey->liveUrl()
        );
    }

    public function test_records_without_a_client_have_no_public_url(): void
    {
        $webinar = new Webinar(['title' => 'Suelto', 'slug' => 'suelto']);

        $this->assertNull($webinar->previewUrl());
        $this->assertNull($webinar->liveUrl());
    }
}
