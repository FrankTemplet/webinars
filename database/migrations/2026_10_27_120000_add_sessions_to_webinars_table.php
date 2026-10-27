<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            // Cada sesión es un webinar de Zoom: [{zoom_webinar_id, label, date}].
            $table->json('sessions')->nullable()->after('zoom_webinar_id');
            $table->string('sessions_label')->nullable()->after('sessions');
        });

        Schema::table('submissions', function (Blueprint $table) {
            // IDs de Zoom de las sesiones que eligió el lead.
            $table->json('sessions')->nullable()->after('data');
            // Cuándo quedó registrado en cada una: {zoom_webinar_id: fecha}.
            $table->json('zoom_registrations')->nullable()->after('registered_in_zoom_at');
        });
    }

    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropColumn(['sessions', 'sessions_label']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['sessions', 'zoom_registrations']);
        });
    }
};
