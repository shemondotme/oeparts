<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands — OEMHub
|--------------------------------------------------------------------------
|
| All scheduled commands for OEMHub. These run via the Laravel scheduler
| which should be triggered by a system cron job every minute:
|
|   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
|
*/

// Sitemap generation — daily at 2 AM
Schedule::command('sitemap:generate')->dailyAt('02:00');

// Clean expired OTPs — hourly
Schedule::command('otp:clean')->hourly();

// Clean expired carts — daily at 3 AM
Schedule::command('cart:clean')->dailyAt('03:00');

// Clean old logs for GDPR compliance — daily at 4 AM (90-day retention)
Schedule::command('logs:clean')->dailyAt('04:00');

// Process abandoned carts — hourly at minute 5
Schedule::command('abandoned-cart:process')->hourlyAt(5);

// Database backup — daily at 1 AM
Schedule::command('db:backup')->dailyAt('01:00');

// Scheduler heartbeat — every minute (for health monitoring)
Schedule::command('scheduler:heartbeat')->everyMinute();
