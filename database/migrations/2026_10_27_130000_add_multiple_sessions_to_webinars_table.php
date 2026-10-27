<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->boolean('multiple_sessions')->default(false)->after('zoom_webinar_id');
        });

        DB::table('webinars')->whereNotNull('sessions')->update(['multiple_sessions' => true]);
    }

    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropColumn('multiple_sessions');
        });
    }
};
