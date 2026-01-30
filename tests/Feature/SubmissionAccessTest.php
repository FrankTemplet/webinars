<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\User;
use App\Models\Client;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_submissions()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get('/admin/submissions');
        $response->assertStatus(200);
    }

    public function test_viewer_can_view_submissions()
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $response = $this->get('/admin/submissions');
        $response->assertStatus(200);
    }

    public function test_viewer_cannot_see_clients_in_navigation()
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $response = $this->get('/admin/submissions');
        $response->assertDontSee('Clients');
        $response->assertDontSee('Webinars');
        $response->assertDontSee('Users');
    }

    public function test_admin_can_see_everything_in_navigation()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get('/admin/submissions');
        $response->assertSee('Clients');
        $response->assertSee('Webinars');
        $response->assertSee('Users');
    }
}
