<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->timestamp('attendees_synced_at')->nullable()->after('zoom_webinar_id');
            $table->unsignedInteger('zoom_participants_count')->nullable()->after('attendees_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropColumn(['attendees_synced_at', 'zoom_participants_count']);
        });
    }
};
