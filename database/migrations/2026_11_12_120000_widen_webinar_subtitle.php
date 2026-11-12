<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webinars', fn (Blueprint $table) => $table->string('subtitle', 265)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('webinars', fn (Blueprint $table) => $table->string('subtitle')->nullable()->change());
    }
};
