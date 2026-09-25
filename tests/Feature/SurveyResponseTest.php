<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyResponseTest extends TestCase
{
    use RefreshDatabase;

    private function createSurvey(array $attributes = []): array
    {
        $client = Client::create([
            'name' => 'Test Client',
            'slug' => 'test-client',
        ]);

        $survey = Survey::create(array_merge([
            'client_id' => $client->id,
            'title' => 'Test Survey',
            'slug' => 'test-survey',
        ], $attributes));

        return [$client, $survey];
    }

    public function test_survey_page_renders_with_the_fixed_template(): void
    {
        [$client, $survey] = $this->createSurvey();

        $this->get("/{$client->slug}/surveys/{$survey->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Survey/Show')
                ->where('survey.slug', $survey->slug)
                ->where('survey.guests_count', 3)
                ->has('survey.stage_options', 4));
    }

    public function test_response_is_stored_with_normalized_phone_and_guests(): void
    {
        [$client, $survey] = $this->createSurvey();

        $this->post("/{$client->slug}/surveys/{$survey->slug}", [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.com',
            'phone' => '(555) 123-4567',
            'experience_rating' => 4,
            'use_case' => 'Migrar de firewall perimetral a SASE.',
            'stage' => 'Evaluación de soluciones',
            'wants_review' => true,
            'guests' => [
                ['name' => 'Luis Paz', 'email' => 'luis@example.com', 'topics' => 'Zero Trust'],
                ['name' => '', 'email' => '', 'topics' => ''],
                ['name' => '', 'email' => '', 'topics' => ''],
            ],
            'utm_source' => 'linkedin',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $response = SurveyResponse::firstOrFail();

        $this->assertSame($survey->id, $response->survey_id);
        $this->assertSame(4, $response->experience_rating);
        $this->assertTrue($response->wants_review);
        $this->assertSame('5551234567', $response->phone);
        $this->assertSame('linkedin', $response->utm_source);

        // Las filas de invitados vacías no se guardan.
        $this->assertCount(1, $response->guests);
        $this->assertSame('luis@example.com', $response->guests[0]['email']);
    }

    public function test_validation_rejects_invalid_email_rating_and_stage(): void
    {
        [$client, $survey] = $this->createSurvey();

        $this->post("/{$client->slug}/surveys/{$survey->slug}", [
            'name' => 'X',
            'email' => 'not-an-email',
            'experience_rating' => 9,
            'stage' => 'Made up stage',
            'wants_review' => true,
        ])->assertSessionHasErrors(['email', 'experience_rating', 'stage']);

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_contact_block_can_be_disabled_for_anonymous_responses(): void
    {
        [$client, $survey] = $this->createSurvey(['contact_enabled' => false]);

        $this->get("/{$client->slug}/surveys/{$survey->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('survey.contact_enabled', false));

        $this->post("/{$client->slug}/surveys/{$survey->slug}", [
            'experience_rating' => 5,
            'wants_review' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $response = SurveyResponse::firstOrFail();

        $this->assertNull($response->name);
        $this->assertNull($response->email);
        $this->assertSame(5, $response->experience_rating);
    }

    public function test_extra_questions_are_numbered_after_the_fixed_ones(): void
    {
        [$client, $survey] = $this->createSurvey([
            'extra_questions' => [
                ['type' => 'rating', 'name' => 'recommend_score', 'label' => '¿Nos recomendarías?', 'required' => true],
                ['type' => 'radio', 'name' => 'format', 'label' => '¿Qué formato prefieres?', 'options' => ['Presencial', 'Virtual']],
                // Sin name: se descarta.
                ['type' => 'text', 'name' => '', 'label' => 'Incompleta'],
            ],
        ]);

        $this->get("/{$client->slug}/surveys/{$survey->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('survey.extra_questions', 2)
                ->where('survey.extra_questions.0.number', 6)
                ->where('survey.extra_questions.0.name', 'recommend_score')
                ->where('survey.extra_questions.1.number', 7));
    }

    public function test_extra_questions_are_validated_and_stored(): void
    {
        [$client, $survey] = $this->createSurvey([
            'extra_questions' => [
                ['type' => 'rating', 'name' => 'recommend_score', 'label' => '¿Nos recomendarías?', 'required' => true],
                ['type' => 'radio', 'name' => 'format', 'label' => '¿Qué formato prefieres?', 'options' => ['Presencial', 'Virtual']],
            ],
        ]);

        $payload = [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.com',
            'experience_rating' => 5,
            'wants_review' => false,
        ];

        // Falta la obligatoria y la opción no está en la lista.
        $this->post("/{$client->slug}/surveys/{$survey->slug}", $payload + [
            'extra' => ['format' => 'Híbrido'],
        ])->assertSessionHasErrors(['extra.recommend_score', 'extra.format']);

        $this->assertSame(0, SurveyResponse::count());

        $this->post("/{$client->slug}/surveys/{$survey->slug}", $payload + [
            'extra' => ['recommend_score' => 4, 'format' => 'Virtual'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            ['recommend_score' => 4, 'format' => 'Virtual'],
            SurveyResponse::firstOrFail()->extra_answers
        );
    }

    public function test_answers_to_removed_questions_are_not_stored(): void
    {
        [$client, $survey] = $this->createSurvey([
            'extra_questions' => [
                ['type' => 'text', 'name' => 'still_here', 'label' => 'Sigue activa'],
            ],
        ]);

        $this->post("/{$client->slug}/surveys/{$survey->slug}", [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.com',
            'experience_rating' => 3,
            'wants_review' => false,
            'extra' => ['still_here' => 'sí', 'deleted_question' => 'no debería guardarse'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['still_here' => 'sí'], SurveyResponse::firstOrFail()->extra_answers);
    }

    public function test_closed_survey_rejects_responses(): void
    {
        [$client, $survey] = $this->createSurvey(['is_open' => false]);

        $this->post("/{$client->slug}/surveys/{$survey->slug}", [
            'name' => 'Bea',
            'email' => 'bea@example.com',
            'experience_rating' => 5,
            'wants_review' => false,
        ])->assertSessionHasErrors('survey');

        $this->assertSame(0, SurveyResponse::count());
    }
}
