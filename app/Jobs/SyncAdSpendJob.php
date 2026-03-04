<?php

namespace App\Jobs;

use App\Models\Webinar;
use App\Services\MetaMarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAdSpendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Get webinars that have a meta_campaign_id
        $webinars = Webinar::with('client')
            ->whereNotNull('meta_campaign_id')
            ->where('meta_campaign_id', '!=', '')
            ->get();

        Log::info("Syncing Ad Spend for {$webinars->count()} webinars.");

        foreach ($webinars as $webinar) {
            // Determine credentials
            $client = $webinar->client;

            $adAccountId = $client?->meta_ad_account_id;
            $accessToken = $client?->meta_access_token;

            // Instantiate service with specific credentials (or null/null to use global defaults)
            $service = new MetaMarketingService($adAccountId, $accessToken);

            $spend = $service->getCampaignSpend($webinar->meta_campaign_id);

            $webinar->update([
                'ad_spend' => $spend,
                'last_ad_spend_sync_at' => now(),
            ]);

            Log::info("Synced Webinar {$webinar->id} (Campaign {$webinar->meta_campaign_id}): \${$spend}");
        }
    }
}
