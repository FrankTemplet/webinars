<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo poco que una landing puede cambiar respecto al estilo de su cliente.
 * La tipografía y los colores de texto siguen siendo del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->string('accent_color', 20)->nullable()->after('header_logo');
            $table->string('accent_text_color', 20)->nullable()->after('accent_color');
            $table->string('background_color', 20)->nullable()->after('accent_text_color');
            $table->unsignedSmallInteger('button_radius')->nullable()->after('background_color');
            $table->unsignedSmallInteger('field_radius')->nullable()->after('button_radius');
            $table->text('custom_css')->nullable()->after('field_radius');
        });
    }

    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropColumn([
                'accent_color', 'accent_text_color', 'background_color',
                'button_radius', 'field_radius', 'custom_css',
            ]);
        });
    }
};
