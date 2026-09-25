<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();

            $table->unsignedTinyInteger('experience_rating')->nullable();
            $table->text('use_case')->nullable();
            $table->string('stage')->nullable();
            $table->boolean('wants_review')->default(false);
            $table->json('guests')->nullable();

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            $table->timestamps();

            $table->index(['survey_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
