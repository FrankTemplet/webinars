<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Submission;
use App\Models\Webinar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportSubmissionsFromExportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'Webinar', 'Nombre', 'Apellido', 'Correo electrónico corporativo', 'Móvil / Celular',
        'Empresa', 'Sector', 'Puesto', 'País', 'Enviado a Clay', 'Registrado en Zoom',
        'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Term', 'UTM Content', 'Fecha de Creación',
    ];

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = storage_path('framework/testing/export.xlsx');

        if (! is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0777, true);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    /**
     * Fila con el mismo formato que produce el export del panel.
     */
    private function row(array $overrides = []): array
    {
        return array_values(array_merge([
            'webinar' => 'Identidad como perímetro de IA',
            'nombre' => 'Marco',
            'apellido' => 'Osorio',
            'email' => 'marco@example.com',
            'movil' => '3001234567',
            'empresa' => 'Tecnoredes',
            'sector' => 'Telecomunicaciones',
            'puesto' => 'Ingeniero',
            'pais' => 'Colombia',
            'clay' => '',
            'zoom' => '2026-10-16 19:23:29',
            'utm_source' => 'cpc',
            'utm_medium' => 'meta',
            'utm_campaign' => 'webinar',
            'utm_term' => 'deepfake_frio',
            'utm_content' => 'estatico',
            'creado' => '2026-10-16 19:23:28',
        ], $overrides));
    }

    private function writeExport(array $rows): void
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        (new Xlsx($sheet->getParent()))->save($this->file);
    }

    private function webinar(): Webinar
    {
        $client = Client::create(['name' => 'LibertyNet', 'slug' => 'libertynet']);

        return Webinar::create([
            'client_id' => $client->id,
            'title' => 'Question Everything',
            'slug' => 'identidad-como-perimetro-ia',
        ]);
    }

    private function import(Webinar $webinar, array $options = [])
    {
        return $this->artisan('webinars:import-submissions', array_merge([
            'webinar' => $webinar->slug,
            'file' => $this->file,
            '--from' => 'Identidad como perímetro de IA',
        ], $options));
    }

    public function test_it_creates_submissions_with_every_field(): void
    {
        $webinar = $this->webinar();
        $this->writeExport([$this->row()]);

        $this->import($webinar)->assertSuccessful();

        $submission = Submission::withoutGlobalScopes()->first();

        $this->assertSame('Marco', $submission->data['first_name']);
        $this->assertSame('marco@example.com', $submission->data['email']);
        $this->assertSame('3001234567', $submission->data['phone_number']);
        $this->assertSame('Tecnoredes', $submission->data['company']);
        $this->assertSame('Telecomunicaciones', $submission->data['sector']);
        $this->assertSame('Ingeniero', $submission->data['job_title']);
        $this->assertSame('Colombia', $submission->data['country']);
        // Los encabezados con mayúsculas seguidas se normalizan bien.
        $this->assertSame('cpc', $submission->utm_source);
        $this->assertSame('deepfake_frio', $submission->utm_term);
        $this->assertSame('2026-10-16 19:23:28', $submission->created_at->toDateTimeString());
    }

    public function test_it_completes_what_is_missing_without_overwriting(): void
    {
        $webinar = $this->webinar();

        $existing = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['first_name' => 'Nombre editado a mano', 'email' => 'marco@example.com'],
        ]);
        $existing->forceFill(['created_at' => Carbon::parse('2026-10-16 19:23:29')])->save();

        $this->writeExport([$this->row()]);

        $this->import($webinar)->assertSuccessful();

        $existing->refresh();

        // Lo que ya había se respeta.
        $this->assertSame('Nombre editado a mano', $existing->data['first_name']);
        // Lo que faltaba se completa.
        $this->assertSame('Tecnoredes', $existing->data['company']);
        $this->assertSame('Osorio', $existing->data['last_name']);
        $this->assertSame(1, Submission::withoutGlobalScopes()->count());
    }

    public function test_real_utms_replace_the_reconstructed_ones(): void
    {
        $webinar = $this->webinar();

        $existing = Submission::create([
            'webinar_id' => $webinar->id,
            'data' => ['email' => 'marco@example.com'],
            'utm_term' => 'voceros_frio',
        ]);
        $existing->forceFill([
            'created_at' => Carbon::parse('2026-10-16 19:23:29'),
            'utms_reconstructed_at' => now(),
        ])->save();

        $this->writeExport([$this->row()]);

        $this->import($webinar)->assertSuccessful();

        $existing->refresh();

        $this->assertSame('deepfake_frio', $existing->utm_term);
        // Ya no es un dato inferido.
        $this->assertNull($existing->utms_reconstructed_at);
    }

    public function test_it_keeps_a_close_date_and_fixes_a_wrong_one(): void
    {
        $webinar = $this->webinar();

        // Fecha de Zoom: un segundo de desfase, no se toca.
        $close = Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'marco@example.com']]);
        $close->forceFill(['created_at' => Carbon::parse('2026-10-16 19:23:29')])->save();

        // Fecha de la importación: días de desfase, se corrige.
        $wrong = Submission::create(['webinar_id' => $webinar->id, 'data' => ['email' => 'ana@example.com']]);
        $wrong->forceFill(['created_at' => Carbon::parse('2026-10-20 19:00:00')])->save();

        $this->writeExport([
            $this->row(),
            $this->row(['email' => 'ana@example.com', 'creado' => '2026-10-17 10:00:00', 'zoom' => '2026-10-17 10:00:01']),
        ]);

        $this->import($webinar)->assertSuccessful();

        $this->assertSame('2026-10-16 19:23:29', $close->refresh()->created_at->toDateTimeString());
        $this->assertSame('2026-10-17 10:00:00', $wrong->refresh()->created_at->toDateTimeString());
    }

    public function test_it_ignores_rows_of_other_webinars(): void
    {
        $webinar = $this->webinar();

        $this->writeExport([
            $this->row(),
            $this->row(['webinar' => 'Otro webinar', 'email' => 'otro@example.com']),
        ]);

        $this->import($webinar)->assertSuccessful();

        $this->assertSame(1, Submission::withoutGlobalScopes()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $webinar = $this->webinar();
        $this->writeExport([$this->row()]);

        $this->import($webinar, ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Submission::withoutGlobalScopes()->count());
    }

    public function test_it_fails_when_no_row_matches(): void
    {
        $webinar = $this->webinar();
        $this->writeExport([$this->row(['webinar' => 'Otro'])]);

        $this->import($webinar, ['--from' => 'No existe'])->assertFailed();
    }
}
