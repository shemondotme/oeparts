<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * search.log_retention_days was labeled "Audit Log Retention (Days)" on the
 * Search Settings page as if it only governed search-log history, but
 * CleanOldLogs actually uses it to prune login_logs and activity_logs too —
 * the admin panel's security/audit trail. An admin tuning search log
 * retention down (to save DB space) was unknowingly also shortening how
 * long login and admin-action audit history was kept. Moves the row (and
 * any admin-configured value) to the security group, where it belongs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'search')
            ->where('key', 'log_retention_days')
            ->update(['group' => 'security']);

        app(SettingsService::class)->forget('search');
        app(SettingsService::class)->forget('security');
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'security')
            ->where('key', 'log_retention_days')
            ->update(['group' => 'search']);

        app(SettingsService::class)->forget('search');
        app(SettingsService::class)->forget('security');
    }
};
