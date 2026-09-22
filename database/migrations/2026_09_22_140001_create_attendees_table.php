<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->cascadeOnDelete();
            // Id del participante en Zoom (participant_uuid o id); identifica la sesión.
            $table->string('zoom_participant_id')->nullable()->index();
            $table->string('zoom_user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->timestamp('join_time')->nullable();
            $table->timestamp('leave_time')->nullable();
            $table->unsignedInteger('duration')->default(0); // segundos
            $table->string('device')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('location')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['webinar_id', 'zoom_participant_id'], 'attendees_webinar_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
