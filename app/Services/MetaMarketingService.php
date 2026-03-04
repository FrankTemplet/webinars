<?php

namespace App\Services;

use FacebookAds\Api;
use FacebookAds\Object\Campaign;
use Illuminate\Support\Facades\Log;

class MetaMarketingService
{
    protected ?string $adAccountId;

    public function __construct(?string $adAccountId = null, ?string $accessToken = null)
    {
        // Default to global config if no overrides provided
        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');

        $token = $accessToken ?? config('services.facebook.access_token');
        $this->adAccountId = $adAccountId ?? config('services.facebook.ad_account_id');

        if ($appId && $appSecret && $token) {
            try {
                if (!Api::instance()) {
                   Api::init($appId, $appSecret, $token);
                } else {
                    // Re-init if using a different token for this instance?
                    // The SDK singleton pattern makes this tricky.
                    // Best practice: Pass the Api instance to the objects, but here we are using static methods.
                    // We can force re-init or just rely on 'Api::init' replacing the instance.
                    Api::init($appId, $appSecret, $token);
                }
            } catch (\Exception $e) {
                Log::error('Meta API Init Failed: ' . $e->getMessage());
            }
        }
    }

    public function getCampaignSpend(string $campaignId): float
    {
        if (!Api::instance()) {
             Log::warning('Meta API not initialized. Cannot fetch campaign spend.');
             return 0.0;
        }

        try {
            $campaign = new Campaign($campaignId);
            $insights = $campaign->getInsights(
                ['spend'],
                [
                    'date_preset' => 'lifetime', // Get total lifetime spend
                ]
            );

            if ($insights->count() > 0) {
                // insights returns a Cursor, getting current() gives an AbstractObject
                $data = $insights->current()->getData();
                return (float) ($data['spend'] ?? 0);
            }

            return 0.0;
        } catch (\Exception $e) {
            Log::error("Meta API Error for Campaign {$campaignId}: " . $e->getMessage());
            return 0.0;
        }
    }
}
