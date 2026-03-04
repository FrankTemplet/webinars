<?php

namespace App\Services;

use FacebookAds\Api;
use FacebookAds\Object\Campaign;
use Illuminate\Support\Facades\Log;

class MetaMarketingService
{
    protected ?string $adAccountId;
    protected bool $isInitialized = false;

    public function __construct(?string $adAccountId = null, ?string $accessToken = null)
    {
        // Default to global config if no overrides provided
        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');

        $token = $accessToken ?? config('services.facebook.access_token');
        $this->adAccountId = $adAccountId ?? config('services.facebook.ad_account_id');

        if ($appId && $appSecret && $token) {
            try {
                // Force re-initialization to ensure we are using the correct credentials
                // for this specific service instance, preventing singleton leakage.
                Api::init($appId, $appSecret, $token);
                $this->isInitialized = true;
            } catch (\Exception $e) {
                Log::error('Meta API Init Failed: ' . $e->getMessage());
            }
        }
    }

    public function getCampaignSpend(string $campaignId): float
    {
        if (!$this->isInitialized || !Api::instance()) {
             Log::warning('Meta API not initialized for this requested scope. Skipping.');
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
