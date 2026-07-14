<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebinarThankYouPageTest extends TestCase
{
    use RefreshDatabase;

    private function createWebinar(array $attributes = []): array
    {
        $client = Client::create([
            'name' => 'Test Client',
            'slug' => 'test-client',
        ]);

        $webinar = Webinar::create(array_merge([
            'client_id' => $client->id,
            'title' => 'Test Webinar',
            'slug' => 'test-webinar',
            'form_schema' => [
                [
                    'type' => 'text',
                    'name' => 'first_name',
                    'label' => 'Nombre',
                    'required' => true,
                ],
            ],
        ], $attributes));

        return [$client, $webinar];
    }

    public function test_submission_redirects_to_thank_you_page_when_enabled()
    {
        [$client, $webinar] = $this->createWebinar([
            'thank_you_enabled' => true,
            'thank_you_image' => 'webinars/thank-you/bg.jpg',
        ]);

        $response = $this->post("/{$client->slug}/{$webinar->slug}", [
            'first_name' => 'John',
        ]);

        $response->assertRedirect("/{$client->slug}/{$webinar->slug}/thank-you");
    }

    public function test_submission_redirects_back_when_disabled()
    {
        [$client, $webinar] = $this->createWebinar();

        $response = $this->from("/{$client->slug}/{$webinar->slug}")
            ->post("/{$client->slug}/{$webinar->slug}", [
                'first_name' => 'John',
            ]);

        $response->assertRedirect("/{$client->slug}/{$webinar->slug}");
        $response->assertSessionHas('success');
    }

    public function test_thank_you_page_renders_after_registration()
    {
        [$client, $webinar] = $this->createWebinar([
            'thank_you_enabled' => true,
            'thank_you_image' => 'webinars/thank-you/bg.jpg',
            'thank_you_title' => 'Custom Thanks',
        ]);

        $this->post("/{$client->slug}/{$webinar->slug}", [
            'first_name' => 'John',
        ]);

        $response = $this->get("/{$client->slug}/{$webinar->slug}/thank-you");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Webinar/ThankYou')
            ->where('webinar.thank_you_title', 'Custom Thanks')
        );
    }

    public function test_direct_access_without_registration_redirects_to_webinar()
    {
        [$client, $webinar] = $this->createWebinar([
            'thank_you_enabled' => true,
            'thank_you_image' => 'webinars/thank-you/bg.jpg',
        ]);

        $response = $this->get("/{$client->slug}/{$webinar->slug}/thank-you");

        $response->assertRedirect("/{$client->slug}/{$webinar->slug}");
    }

    public function test_thank_you_page_redirects_to_webinar_when_disabled()
    {
        [$client, $webinar] = $this->createWebinar();

        $response = $this->get("/{$client->slug}/{$webinar->slug}/thank-you");

        $response->assertRedirect("/{$client->slug}/{$webinar->slug}");
    }
}
