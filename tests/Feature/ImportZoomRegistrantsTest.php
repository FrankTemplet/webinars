<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Submission;
use App\Models\Webinar;
use App\Services\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ImportZoomRegistrantsTest extends TestCase
{
    use RefreshDatabase;

    private function webinar(array $attributes = []): Webinar
    {
        $client = Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);

        return Webinar::create(array_merge([
            'client_id' => $client->id,
            'title' => 'Identidad como perímetro',
            'slug' => 'identidad-como-perimetro-ia',
            'zoom_webinar_id' => '88240652019',
            'form_schema' => [
                ['type' => 'text', 'name' => 'first_name', 'label' => 'Nombre', 'required' => true],
                ['type' => 'text', 'name' => 'last_name', 'label' => 'Apellido', 'required' => true],
                ['type' => 'email', 'name' => 'email', 'label' => 'Correo', 'required' => true],
                ['type' => 'text', 'name' => 'company', 'label' => 'Empresa', 'required' => false],
            ],
        ], $attributes));
    }

    private function fakeZoom(array $registrants): void
    {
        $this->instance(ZoomService::class, Mockery::mock(ZoomService::class, function ($mock) use ($registrants) {
            $mock->shouldReceive('listRegistrants')->andReturn($registrants);
        }));
    }

    public function test_it_recreates_the_submissions_zoom_still_has(): void
    {
        $webinar = $this->webinar();

        $this->fakeZoom([
            ['first_name' => 'Marco', 'last_name' => 'Osorio', 'email' => 'marco@example.com', 'create_time' => '2026-10-16T16:12:21Z'],
            ['first_name' => 'Ana', 'last_name' => 'Díaz', 'email' => 'ana@example.com', 'create_time' => '2026-10-17T10:00:00Z'],
        ]);

        $this->artisan('webinars:import-zoom-registrants', ['webinar' => $webinar->slug])
            ->assertSuccessful();

        $this->assertSame(2, Submission::withoutGlobalScopes()->where('webinar_id', $webinar->id)->count());

        $first = Submission::withoutGlobalScopes()->where('webinar_id', $webinar->id)->orderBy('id')->first();

        $this->assertSame('Marco', $first->data['first_name']);
        $this->assertSame('marco@example.com', $first->data['email']);
        // La fecha es la del registro en Zoom, no la de la importación.
        $this->assertSame('2026-10-16', $first->created_at->toDateString());
        $this->assertNotNull($first->registered_in_zoom_at);
    }

    public function test_it_does_not_duplicate_what_is_already_there(): void
    {
        $webinar = $this->webinar();

        Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['first_name' => 'Marco', 'last_name' => 'Osorio', 'email' => 'MARCO@example.com'],
        ]);

        $this->fakeZoom([
            ['first_name' => 'Marco', 'last_name' => 'Osorio', 'email' => 'marco@example.com', 'create_time' => '2026-10-16T16:12:21Z'],
            ['first_name' => 'Ana', 'last_name' => 'Díaz', 'email' => 'ana@example.com', 'create_time' => '2026-10-17T10:00:00Z'],
        ]);

        $this->artisan('webinars:import-zoom-registrants', ['webinar' => $webinar->slug])->assertSuccessful();

        $this->assertSame(2, Submission::withoutGlobalScopes()->where('webinar_id', $webinar->id)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $webinar = $this->webinar();

        $this->fakeZoom([
            ['first_name' => 'Ana', 'last_name' => 'Díaz', 'email' => 'ana@example.com', 'create_time' => '2026-10-17T10:00:00Z'],
        ]);

        $this->artisan('webinars:import-zoom-registrants', ['webinar' => $webinar->slug, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Submission::withoutGlobalScopes()->count());
    }

    public function test_it_uses_the_field_names_of_this_webinar(): void
    {
        $webinar = $this->webinar([
            'form_schema' => [
                ['type' => 'text', 'name' => 'nombre', 'label' => 'Nombre', 'required' => true],
                ['type' => 'text', 'name' => 'apellido', 'label' => 'Apellido', 'required' => true],
                ['type' => 'email', 'name' => 'correo', 'label' => 'Correo', 'required' => true],
            ],
        ]);

        $this->fakeZoom([
            ['first_name' => 'Ana', 'last_name' => 'Díaz', 'email' => 'ana@example.com', 'create_time' => '2026-10-17T10:00:00Z'],
        ]);

        $this->artisan('webinars:import-zoom-registrants', ['webinar' => $webinar->slug])->assertSuccessful();

        $data = Submission::withoutGlobalScopes()->first()->data;

        $this->assertSame('Ana', $data['nombre']);
        $this->assertSame('ana@example.com', $data['correo']);
    }

    public function test_it_refuses_a_webinar_without_zoom(): void
    {
        $webinar = $this->webinar(['zoom_webinar_id' => null]);

        $this->artisan('webinars:import-zoom-registrants', ['webinar' => $webinar->slug])
            ->assertFailed();
    }
}
