<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            // Sesión a la que asistió, en webinars con varios webinars de Zoom.
            $table->string('zoom_webinar_id')->nullable()->after('webinar_id');
        });

        // Lo ya sincronizado viene del único Zoom que tenía cada webinar.
        DB::table('attendees')->update([
            'zoom_webinar_id' => DB::table('webinars')
                ->whereColumn('webinars.id', 'attendees.webinar_id')
                ->select('zoom_webinar_id'),
        ]);

        // Un mismo user_id podría repetirse entre los reportes de dos sesiones.
        // El índice nuevo va primero: MySQL no suelta el que sostiene la
        // llave foránea de webinar_id sin otro que lo reemplace.
        Schema::table('attendees', function (Blueprint $table) {
            $table->unique(['webinar_id', 'zoom_webinar_id', 'zoom_participant_id'], 'attendees_webinar_session_participant_unique');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropUnique('attendees_webinar_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->unique(['webinar_id', 'zoom_participant_id'], 'attendees_webinar_participant_unique');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->dropUnique('attendees_webinar_session_participant_unique');
            $table->dropColumn('zoom_webinar_id');
        });
    }
};
