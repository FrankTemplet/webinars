<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyAdminFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_form_describes_both_fixed_and_additional_questions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/admin/surveys/create');

        $response->assertStatus(200);
        $response->assertSee('Template Questions');
        $response->assertSee('Additional Questions');
        $response->assertSee('Contact Block');

        // La copia vieja decía que la plantilla era lo único editable.
        $response->assertDontSee('fixed 5-question template', false);
        $response->assertDontSee('Only the wording of each question is editable', false);
    }
}
