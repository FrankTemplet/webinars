<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El estilo de las páginas públicas vive en el cliente: es su marca y se
 * repite en todos sus webinars y encuestas. Cada página puede sobreescribir
 * unos pocos de estos valores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('font_family', 50)->nullable()->after('footer_text');
            $table->string('accent_color', 20)->nullable()->after('font_family');
            $table->string('accent_text_color', 20)->nullable()->after('accent_color');
            $table->string('heading_color', 20)->nullable()->after('accent_text_color');
            $table->string('subtitle_color', 20)->nullable()->after('heading_color');
            $table->string('text_color', 20)->nullable()->after('subtitle_color');
            $table->string('background_color', 20)->nullable()->after('text_color');
            $table->unsignedSmallInteger('button_radius')->nullable()->after('background_color');
            $table->unsignedSmallInteger('field_radius')->nullable()->after('button_radius');
            $table->text('custom_css')->nullable()->after('field_radius');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'font_family', 'accent_color', 'accent_text_color', 'heading_color',
                'subtitle_color', 'text_color', 'background_color',
                'button_radius', 'field_radius', 'custom_css',
            ]);
        });
    }
};
