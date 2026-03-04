<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->string('meta_campaign_id')->nullable()->after('clay_webhook_url');
            $table->decimal('ad_spend', 10, 2)->default(0)->after('meta_campaign_id');
            $table->timestamp('last_ad_spend_sync_at')->nullable()->after('ad_spend');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            //
        });
    }
};
