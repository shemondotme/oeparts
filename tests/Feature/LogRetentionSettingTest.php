<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * log_retention_days used to live under the "search" settings group, labeled
 * as if it only governed search-log history — but CleanOldLogs actually uses
 * it to prune login_logs and activity_logs too, the admin panel's security
 * audit trail. Migration 2026_08_10_000005 moves the setting (and any
 * admin-configured value) to the "security" group where it's now seeded,
 * labeled, and read from.
 */
class LogRetentionSettingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_setting_is_seeded_under_security_not_search(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertDatabaseHas('settings', [
            'group' => 'security',
            'key'   => 'log_retention_days',
            'value' => '90',
        ]);

        $this->assertDatabaseMissing('settings', [
            'group' => 'search',
            'key'   => 'log_retention_days',
        ]);
    }

    #[Test]
    public function an_admin_configured_value_under_the_old_group_survives_the_migration_rename(): void
    {
        // Simulate an install that already had this row under "search" (the
        // pre-migration state) with an admin-customized value, then re-run
        // just this migration's up() logic directly to confirm it carries
        // the value across groups rather than resetting it to seeded default.
        Setting::where('group', 'security')->where('key', 'log_retention_days')->delete();
        Setting::create(['group' => 'search', 'key' => 'log_retention_days', 'value' => '30', 'type' => 'integer']);

        \Illuminate\Support\Facades\DB::table('settings')
            ->where('group', 'search')->where('key', 'log_retention_days')
            ->update(['group' => 'security']);

        $this->assertDatabaseHas('settings', [
            'group' => 'security',
            'key'   => 'log_retention_days',
            'value' => '30',
        ]);
    }

    #[Test]
    public function logs_clean_uses_the_security_group_setting(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        app(\App\Services\SettingsService::class)->set('security.log_retention_days', '5');

        // created_at isn't fillable (LoginLog stamps it itself via a
        // booted() hook) — forceFill() to backdate it for this test.
        LoginLog::create(['user_type' => 'admin', 'status' => 'success', 'email' => 'old@example.com', 'ip_address' => '127.0.0.1'])
            ->forceFill(['created_at' => now()->subDays(10)])->save();
        $recent = LoginLog::create(['user_type' => 'admin', 'status' => 'success', 'email' => 'recent@example.com', 'ip_address' => '127.0.0.1']);
        $recent->forceFill(['created_at' => now()->subDay()])->save();

        Artisan::call('logs:clean');

        $this->assertDatabaseCount('login_logs', 1);
        $this->assertDatabaseHas('login_logs', ['id' => $recent->id]);
    }
}
