<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Webinar;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebinarSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_submit_registration_with_radio_button_group()
    {
        $client = Client::create([
            'name' => 'Test Client',
            'slug' => 'test-client',
        ]);

        $webinar = Webinar::create([
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
                [
                    'type' => 'radio',
                    'name' => 'preference',
                    'label' => 'Preferences',
                    'required' => true,
                    'options' => ['Option 1', 'Option 2'],
                ],
            ],
        ]);

        $response = $this->post("/{$client->slug}/{$webinar->slug}", [
            'first_name' => 'John',
            'preference' => 'Option 2',
        ]);

        $response->assertStatus(302); // Redirects back

        $submission = Submission::first();
        $this->assertNotNull($submission);
        $this->assertEquals($webinar->id, $submission->webinar_id);
        $this->assertEquals('John', $submission->data['first_name']);
        $this->assertEquals('Option 2', $submission->data['preference']);
    }

    public function test_validation_fails_if_required_radio_button_is_missing()
    {
        $client = Client::create([
            'name' => 'Test Client',
            'slug' => 'test-client',
        ]);

        $webinar = Webinar::create([
            'client_id' => $client->id,
            'title' => 'Test Webinar',
            'slug' => 'test-webinar',
            'form_schema' => [
                [
                    'type' => 'radio',
                    'name' => 'preference',
                    'label' => 'Preferences',
                    'required' => true,
                    'options' => ['Option 1', 'Option 2'],
                ],
            ],
        ]);

        $response = $this->post("/{$client->slug}/{$webinar->slug}", [
            // 'preference' is missing
        ]);

        $response->assertSessionHasErrors(['preference']);
        $this->assertEquals(0, Submission::count());
    }
}
