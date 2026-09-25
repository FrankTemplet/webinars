<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            // La plantilla es la misma para todos los clientes; lo único que
            // cambia entre encuestas es el color de marca y el contraste del
            // botón (Liberty usa naranja/blanco, gen-IA verde/texto oscuro).
            $table->string('accent_color', 7)->default('#FF6000')->after('header_logo');
            $table->string('accent_text_color', 7)->default('#FFFFFF')->after('accent_color');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['accent_color', 'accent_text_color']);
        });
    }
};
