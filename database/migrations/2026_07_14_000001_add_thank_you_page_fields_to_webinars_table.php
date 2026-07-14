<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->boolean('thank_you_enabled')->default(false);
            $table->string('thank_you_title')->nullable();
            $table->text('thank_you_message')->nullable();
            $table->string('thank_you_image')->nullable();
            $table->string('thank_you_cta_text')->nullable();
            $table->string('thank_you_cta_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropColumn([
                'thank_you_enabled',
                'thank_you_title',
                'thank_you_message',
                'thank_you_image',
                'thank_you_cta_text',
                'thank_you_cta_url',
            ]);
        });
    }
};
