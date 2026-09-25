<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webinar_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->string('subtitle')->nullable();
            $table->text('intro')->nullable();

            $table->string('hero_image')->nullable();
            $table->string('header_logo')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();

            // Plantilla fija de 5 preguntas: solo el enunciado es editable.
            $table->string('q1_label')->nullable();
            $table->string('q2_label')->nullable();
            $table->string('q3_label')->nullable();
            $table->json('q3_options')->nullable();
            $table->string('q4_label')->nullable();
            $table->string('q5_label')->nullable();
            $table->unsignedTinyInteger('guests_count')->default(3);

            $table->boolean('is_open')->default(true);
            $table->string('closed_message')->nullable();
            $table->string('thank_you_title')->nullable();
            $table->text('thank_you_message')->nullable();

            $table->timestamps();

            $table->unique(['client_id', 'slug']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
