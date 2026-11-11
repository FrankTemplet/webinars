<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webinars', fn (Blueprint $table) => $table->text('footer_text')->nullable());
        Schema::table('surveys', fn (Blueprint $table) => $table->text('footer_text')->nullable());
    }

    public function down(): void
    {
        Schema::table('webinars', fn (Blueprint $table) => $table->dropColumn('footer_text'));
        Schema::table('surveys', fn (Blueprint $table) => $table->dropColumn('footer_text'));
    }
};
