<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La landing de SASE 2 tenía sus estilos quemados en el componente Vue, bajo
 * una clase que se activaba comparando el slug. Ahora que el estilo es
 * configurable, esos valores pasan a ser datos para que la página publicada
 * no cambie al quitar el bloque de CSS.
 *
 * La tipografía es de marca, así que va en el cliente y la heredan todas sus
 * páginas. Lo demás es de esta landing en particular.
 */
return new class extends Migration
{
    private const CLIENT = 'libertynet';

    private const SLUG = 'mas-alla-del-firewall-la-nueva-era-sase-2';

    private const CUSTOM_CSS = <<<'CSS'
.webinar-page {
    --field-border: #707070;
    --button-transform: none;
}

.webinar-subtitle {
    color: #ff6000 !important;
    font-size: 24px !important;
    line-height: 28.8px !important;
    margin-bottom: 16px !important;
}

.webinar-page .form-field {
    color: #707070 !important;
}

.webinar-page .form-field:focus {
    color: #010812 !important;
    background-color: #f4f4f4 !important;
    box-shadow: 0 0 0 0.25rem rgba(255, 96, 0, 0.25) !important;
}

.webinar-page .btn-submit {
    padding: 10px 24px !important;
}

@media (min-width: 768px) {
    .webinar-page .wrapper {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
    }
}
CSS;

    public function up(): void
    {
        $client = DB::table('clients')->where('slug', self::CLIENT)->first();

        if (! $client) {
            return;
        }

        DB::table('clients')->where('id', $client->id)->update([
            'font_family' => 'Maven Pro',
        ]);

        $webinar = DB::table('webinars')
            ->where('client_id', $client->id)
            ->where('slug', self::SLUG)
            ->first();

        if (! $webinar) {
            return;
        }

        DB::table('webinars')->where('id', $webinar->id)->update([
            'accent_color' => '#FF6000',
            'accent_text_color' => '#FFFFFF',
            'button_radius' => 6,
            'field_radius' => 10,
            'custom_css' => trim(self::CUSTOM_CSS),
            // El bloque de CSS ocultaba el h1; ahora lo hace el toggle.
            'show_title' => false,
        ]);
    }

    public function down(): void
    {
        DB::table('clients')->where('slug', self::CLIENT)->update(['font_family' => null]);

        DB::table('webinars')->where('slug', self::SLUG)->update([
            'accent_color' => null,
            'accent_text_color' => null,
            'button_radius' => null,
            'field_radius' => null,
            'custom_css' => null,
        ]);
    }
};
