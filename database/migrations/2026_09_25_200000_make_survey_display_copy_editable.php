<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('contact_title')->nullable()->after('contact_enabled');
            $table->string('contact_description')->nullable()->after('contact_title');
            $table->string('submit_label')->nullable()->after('contact_description');
            $table->string('yes_label')->nullable()->after('q4_label');
            $table->string('no_label')->nullable()->after('yes_label');
        });

        // `title` pasa a ser solo el nombre interno, así que lo que antes se
        // mostraba (título y subtítulo) se conserva dentro del intro para no
        // perder la copia ya publicada.
        foreach (DB::table('surveys')->get(['id', 'title', 'subtitle', 'intro']) as $survey) {
            $intro = '<h2>'.e($survey->title).'</h2>';

            if (filled($survey->subtitle)) {
                $intro .= '<p><strong>'.e($survey->subtitle).'</strong></p>';
            }

            DB::table('surveys')
                ->where('id', $survey->id)
                ->update(['intro' => $intro.($survey->intro ?? '')]);
        }

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('slug');
            $table->dropColumn([
                'contact_title', 'contact_description', 'submit_label', 'yes_label', 'no_label',
            ]);
        });
    }
};
