<?php

use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->string('favicon')->nullable());
        Schema::table('webinars', function (Blueprint $table) {
            $table->string('mobile_hero_image')->nullable();
            $table->string('submit_label', 60)->nullable();
        });
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('mobile_hero_image')->nullable();
            $table->json('question_schema')->nullable();
            $table->json('contact_schema')->nullable();
        });
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->boolean('wants_review')->nullable()->default(null)->change();
            $table->json('answers')->nullable();
            $table->json('contact_answers')->nullable();
            $table->json('schema_snapshot')->nullable();
        });
        Survey::withoutGlobalScopes()->chunkById(100, function ($surveys) {
            foreach ($surveys as $survey) {
                $survey->update(['question_schema' => $survey->questionFields(), 'contact_schema' => $survey->contactFields()]);
                SurveyResponse::withoutGlobalScopes()->where('survey_id', $survey->id)->chunkById(100, function ($responses) use ($survey) {
                    foreach ($responses as $response) {
                        $response->update($response->legacyDynamicData($survey));
                    }
                });
            }
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('survey_responses')->whereNull('wants_review')->update(['wants_review' => false]);
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropColumn(['answers', 'contact_answers', 'schema_snapshot']);
            $table->boolean('wants_review')->nullable(false)->default(false)->change();
        });
        Schema::table('surveys', fn (Blueprint $table) => $table->dropColumn(['mobile_hero_image', 'question_schema', 'contact_schema']));
        Schema::table('webinars', fn (Blueprint $table) => $table->dropColumn(['mobile_hero_image', 'submit_label']));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('favicon'));
    }
};
