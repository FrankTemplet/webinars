<?php

use App\Jobs\SyncAdSpendJob;
use App\Jobs\SyncAttendeesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncAdSpendJob)->hourly();
Schedule::job(new SyncAttendeesJob)->hourly();
