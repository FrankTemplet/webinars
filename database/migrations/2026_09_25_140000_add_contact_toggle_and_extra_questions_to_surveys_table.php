<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('contact_enabled')->default(true)->after('guests_count');
            $table->json('extra_questions')->nullable()->after('contact_enabled');
        });

        Schema::table('survey_responses', function (Blueprint $table) {
            $table->json('extra_answers')->nullable()->after('guests');
        });

        // Sin bloque de contacto no hay nombre ni correo que guardar.
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['contact_enabled', 'extra_questions']);
        });

        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropColumn('extra_answers');
        });
    }
};
