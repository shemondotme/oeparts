<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands — OeParts
|--------------------------------------------------------------------------
|
| All scheduled commands for OeParts. These run via the Laravel scheduler
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

// Dispatch newsletter campaigns whose scheduled send time has arrived —
// this is what makes the admin's "Scheduled Send Date" actually fire.
Schedule::command('oeparts:newsletter:send-due')->everyFiveMinutes();

// Auto-complete shipped orders after the operator-configured window —
// this is what makes OrdersSettings' "Auto-Complete Fulfillment" real.
Schedule::command('oeparts:orders:auto-complete')->dailyAt('02:30');

// Backup Engine (Module 21) — daily full encrypted backup + GFS prune.
// Supersedes the old db:backup / mysqldump command (kept for now, no longer scheduled).
Schedule::command('oeparts:backup --trigger=scheduled')
    ->dailyAt(config('backup.schedule.time', '01:00'))
    ->when(fn () => (bool) config('backup.schedule.enabled', true) && (bool) config('backup.enabled', true));

// Reclaim backup runs abandoned mid-progress (e.g. an admin navigates away
// while "Run backup now" is still polling) and release the stale shared
// lock they hold — otherwise it blocks every future backup AND update
// indefinitely with nothing to auto-recover it. Matches
// config('backup.stale_after_seconds')'s default 1-hour threshold.
Schedule::command('oeparts:backup:cleanup-stale')->hourly();

// Check for available updates — daily at 6 AM (warms the update-check cache)
Schedule::command('oeparts:update:check')->dailyAt('06:00');

// Unattended SECURITY-update auto-apply — opt-in only (OE_UPDATE_AUTO_SECURITY),
// off by default. Runs after the check above so its own admin-notification
// email lands separately, not racing the "update available" one.
Schedule::command('oeparts:update:auto-apply')
    ->dailyAt('06:15')
    ->when(fn () => (bool) config('updates.auto_apply_security', false));

// Scheduler heartbeat — every minute (for health monitoring)
Schedule::command('scheduler:heartbeat')->everyMinute();

// Purge customer refund evidence photos long after the refund was resolved —
// GDPR data minimization (default 180 days after processed_at).
Schedule::command('oeparts:refunds:clean-images')->dailyAt('04:15');

// Purge the (disposable, regenerate-on-demand) invoice PDF cache — default 30 days.
Schedule::command('oeparts:invoices:clean-cache')->dailyAt('04:20');
