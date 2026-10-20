<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las encuestas ya traían accent_color y accent_text_color; se completan con
 * los mismos overrides que tienen los webinars.
 *
 * Esas dos columnas nacieron con DEFAULT en la base, así que toda encuesta
 * traía el naranja puesto y nunca habría heredado el color del cliente. Se
 * les quita el default y se vacían las que quedaron con el valor de la
 * plantilla: heredan, y como la plantilla es ese mismo naranja, ninguna
 * encuesta publicada cambia de aspecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('background_color', 20)->nullable()->after('accent_text_color');
            $table->unsignedSmallInteger('button_radius')->nullable()->after('background_color');
            $table->unsignedSmallInteger('field_radius')->nullable()->after('button_radius');
            $table->text('custom_css')->nullable()->after('field_radius');
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->string('accent_color', 20)->nullable()->default(null)->change();
            $table->string('accent_text_color', 20)->nullable()->default(null)->change();
        });

        DB::table('surveys')->where('accent_color', '#FF6000')->update(['accent_color' => null]);
        DB::table('surveys')->where('accent_text_color', '#FFFFFF')->update(['accent_text_color' => null]);
    }

    public function down(): void
    {
        DB::table('surveys')->whereNull('accent_color')->update(['accent_color' => '#FF6000']);
        DB::table('surveys')->whereNull('accent_text_color')->update(['accent_text_color' => '#FFFFFF']);

        Schema::table('surveys', function (Blueprint $table) {
            $table->string('accent_color', 7)->default('#FF6000')->change();
            $table->string('accent_text_color', 7)->default('#FFFFFF')->change();

            $table->dropColumn([
                'background_color', 'button_radius', 'field_radius', 'custom_css',
            ]);
        });
    }
};
