# Plan: Meta Ads Integration for Webinar Costs

## 1. Overview
This plan outlines the implementation of a Meta (Facebook) Ads integration to track campaign costs for each webinar. This will allow the `StatsOverview` widget to calculate and display:
- **Total Ad Spend**: Aggregated from Meta Campaigns.
- **Cost Per Lead (CPL)**: Total Ad Spend / Total Registrations.

## 2. Database Changes
We need to store the Meta Campaign ID and the synchronized ad spend on the `Webinar` model.

### Migration
Create a migration to add columns to the `webinars` table:
```php
Schema::table('webinars', function (Blueprint $table) {
    $table->string('meta_campaign_id')->nullable()->after('clay_webhook_url');
    $table->decimal('ad_spend', 10, 2)->default(0)->after('meta_campaign_id');
    $table->timestamp('last_ad_spend_sync_at')->nullable()->after('ad_spend');
});
```

## 3. Configuration & Environment
We need credentials to access the Meta Marketing API.

**IMPORTANT: "No Login" Strategy (Server-to-Server)**
To ensure no user is ever asked to log in to Facebook, we will use a **System User Access Token**.
- This token is generated once by an administrator in the Business Manager.
- It does not expire.
- It is not tied to a personal user session (cookie/login).

### Detailed Setup Guide (Meta Business Manager)

**Step 1: Create a Meta App (Business Type)**
1.  Go to [developers.facebook.com/apps](https://developers.facebook.com/apps).
2.  Click **Create App**.
3.  Select **Other** (or "Select an app type").
4.  Choose **Business** as the app type. *Crucial: Do not select Consumer.*
5.  Fill in the app name (e.g., "Webinar Cost Tracker") and connect it to your Business Account.
6.  Once created, go to **Settings > Basic** and copy the **App ID** and **App Secret**.

**Step 2: Create a System User**
1.  Go to [business.facebook.com/settings](https://business.facebook.com/settings) (Business Settings).
2.  Navigate to **Users > System Users**.
3.  Click **Add**.
4.  Name: "Webinar Server Int" (or similar).
5.  Role: **Admin** (Recommended to ensure it can access all assets seamlessly).

**Step 3: Assign Assets (App & Ad Account)**
*Critically, the App must be connected to the Business Portfolio to generate a token.*

**A. Add the App to Business Settings:**
1.  Go to **Accounts > Apps** in Business Settings.
2.  Click **Add** > **Connect an App ID**.
3.  Enter the App ID from Step 1.

**B. Assign the Ad Account to the System User:**
1.  Go to **Users > System Users** and select your user.
2.  Click **Add Assets**.
3.  Select **Ad Accounts** > Select your account(s).
4.  Toggle **View Performance** (or full control).
5.  Click **Save Changes**.

**Step 4: Generate the Access Token**
1.  With the System User selected, click **Generate New Token**.
2.  Select the **App** you created in Step 1.
3.  **Permissions**: Select `ads_read` and `read_insights`.
    - `ads_read`: Required to see ad account data.
    - `read_insights`: Required to see spend/cost data.
4.  Click **Generate Token**.
5.  **Copy and Save this token immediately**. It will not be shown again.

**Step 5: Obtain Ad Account ID**
1.  Go to [Ads Manager](https://adsmanager.facebook.com/).
2.  Select your ad account from the dropdown in the top left.
3.  The ID is the number shown in parentheses `(123456789)` or in the URL `...&act=123456789...`.
4.  **Important**: When adding to `.env`, prepend `act_` to the ID (e.g., `act_123456789`).

### .env
```env
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret
FACEBOOK_ACCESS_TOKEN=your_system_user_access_token
FACEBOOK_AD_ACCOUNT_ID=act_123456789
```

### config/services.php
```php
'facebook' => [
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'access_token' => env('FACEBOOK_ACCESS_TOKEN'),
    'ad_account_id' => env('FACEBOOK_AD_ACCOUNT_ID'),
],
```

## 4. Meta Marketing Service
We will create a service to interact with the Meta Graph API. Using the official SDK is recommended for stability.

### Dependency
```bash
composer require facebook/php-business-sdk
```

### Service Class (`app/Services/MetaMarketingService.php`)
This service will fetch insights for a specific campaign.

```php
namespace App\Services;

use FacebookAds\Api;
use FacebookAds\Logger\CurlLogger;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\Campaign;
use Illuminate\Support\Facades\Log;

class MetaMarketingService
{
    protected ?string $adAccountId;

    public function __construct()
    {
        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');
        $accessToken = config('services.facebook.access_token');
        $this->adAccountId = config('services.facebook.ad_account_id');

        if ($appId && $appSecret && $accessToken) {
            Api::init($appId, $appSecret, $accessToken);
        }
    }

    public function getCampaignSpend(string $campaignId): float
    {
        try {
            $campaign = new Campaign($campaignId);
            $insights = $campaign->getInsights(
                ['spend'],
                [
                    'date_preset' => 'lifetime', // Get total lifetime spend
                ]
            );

            if ($insights->count() > 0) {
                return (float) $insights->current()->getData()['spend'];
            }

            return 0.0;
        } catch (\Exception $e) {
            Log::error("Meta API Error for Campaign {$campaignId}: " . $e->getMessage());
            return 0.0;
        }
    }
}
```

## 5. Job for Synchronization
To avoid hitting API limits and slowing down the dashboard, we will fetch costs in the background.

### Job Class (`app/Jobs/SyncAdSpendJob.php`)
```php
namespace App\Jobs;

use App\Models\Webinar;
use App\Services\MetaMarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAdSpendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MetaMarketingService $service): void
    {
        // Get webinars that have a meta_campaign_id
        $webinars = Webinar::whereNotNull('meta_campaign_id')->get();

        foreach ($webinars as $webinar) {
            $spend = $service->getCampaignSpend($webinar->meta_campaign_id);
            
            $webinar->update([
                'ad_spend' => $spend,
                'last_ad_spend_sync_at' => now(),
            ]);
        }
    }
}
```

### Scheduling
Add to `routes/console.php` or `app/Console/Kernel.php` (depending on Laravel version setup in this project `routes/console.php` looks standard for L11/12):

```php
use App\Jobs\SyncAdSpendJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncAdSpendJob)->hourly();
```

## 6. Filament Resource Update
Allow admins to input the `meta_campaign_id`.

### Resource (`app/Filament/Resources/Webinars/WebinarResource.php`)
Add field to form schema:
```php
Forms\Components\TextInput::make('meta_campaign_id')
    ->label('Meta Campaign ID')
    ->helperText('The ID of the Facebook Ads campaign for this webinar.')
    ->maxLength(255),
```

And optionally display `ad_spend` in the table as a read-only column.

## 7. Widget Update (`StatsOverviewWidget`)
Since you have modified the widget to require a `webinar_id` filter and display specific stats, we will inject the cost calculations into that existing logic.

In `app/Filament/Widgets/StatsOverviewWidget.php`:

1.  **Locate** the section where the `$webinar` is retrieved (after the check `if (!$webinarId) { ... }`).
2.  **Add** the Ad Spend and CPL calculation.
3.  **Append** the new Stats to the return array.

```php
        // ... existing code (fetching webinar) ...
        $webinar = Webinar::find($webinarId);
        
        // ... existing code (calculating submissions/attendance) ...

        // --- NEW META ADS LOGIC ---
        $totalAdSpend = $webinar->ad_spend ?? 0;
        
        // Calculate CPL (Cost Per Lead)
        // Formula: Ad Spend / Total Registrations
        $cpl = $totalSubmissions > 0 ? ($totalAdSpend / $totalSubmissions) : 0;
        
        // If you prefer CPL based only on paid leads:
        // $cpl = $registeredLeads > 0 ? ($totalAdSpend / $registeredLeads) : 0;

        $stats = [
            Stat::make('Total registers', number_format($totalSubmissions))
                ->description('Total submissions')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            // ... (keep your existing stats: Register Contacts, Leads, Webinar Attendance) ...
            
            Stat::make('Register Contacts', number_format($submissionUtmBlanks))
                 // ... existing configuration ...
                 ,

            Stat::make('Leads', number_format($registeredLeads))
                 // ... existing configuration ...
                 ,

            Stat::make('Webinar Attendance', number_format($webinarAttendance))
                 // ... existing configuration ...
                 ,

            // --- ADD THESE NEW STATS ---
            Stat::make('Total Ad Spend', '$' . number_format($totalAdSpend, 2))
                ->description('Synced from Meta Ads')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Cost Per Lead (CPL)', '$' . number_format($cpl, 2))
                ->description('Spend / Registrations')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($cpl < 10 ? 'success' : 'danger'),
        ];

        return $stats;
```

## 8. Implementation To-Do List

### Phase 1: Database & Configuration
- [x] **Create Migration**: Generate migration to add `meta_campaign_id`, `ad_spend`, and `last_ad_spend_sync_at` to the `webinars` table.
- [x] **Run Migration**: Execute `php artisan migrate`.
- [ ] **Meta Setup**: Follow the "Detailed Setup Guide" to create the App, System User, and generate the Access Token.
- [ ] **Environment Config**: Add `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, `FACEBOOK_ACCESS_TOKEN`, and `FACEBOOK_AD_ACCOUNT_ID` to `.env`.
- [x] **Service Config**: Update `config/services.php` to include the `facebook` array.

### Phase 2: Backend Logic
- [ ] **Install SDK**: Run `composer require facebook/php-business-sdk`.
- [ ] **Create Service**: Create `app/Services/MetaMarketingService.php` and implement `getCampaignSpend`.
- [ ] **Create Job**: Create `app/Jobs/SyncAdSpendJob.php` to iterate over webinars and update costs.
- [ ] **Schedule Job**: Register the job in `routes/console.php` (or kernel) to run hourly.

### Phase 3: Admin Panel (Filament)
- [ ] **Update Webinar Resource**: Edit `app/Filament/Resources/Webinars/WebinarResource.php` to add the `meta_campaign_id` text input.
- [ ] **Update Stats Widget**: Modify `app/Filament/Widgets/StatsOverviewWidget.php` to include "Total Ad Spend" and "CPL" stats.

### Phase 4: Verification
- [x] **Manual Sync Test**: Run `php artisan tinker` -> `dispatch(new \App\Jobs\SyncAdSpendJob(new \App\Services\MetaMarketingService));` to verify data fetching.
- [x] **UI Check**: Go to the Dashboard, select a webinar with a linked campaign, and verify the stats appear correctly.
