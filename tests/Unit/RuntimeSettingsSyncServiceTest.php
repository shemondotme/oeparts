<?php

namespace Tests\Unit;

use App\Enums\SettingType;
use App\Models\Setting;
use App\Services\RuntimeSettingsSyncService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * See RuntimeSettingsSyncService's class docblock: this used to be
 * ServiceProvider::boot()-only logic, which is stale for the entire
 * lifetime of any process that boots once and serves many
 * requests/jobs — extracted so it can be re-invoked from more than one
 * boundary (an HTTP middleware, a queue job-processing hook, in addition
 * to boot() itself).
 */
class RuntimeSettingsSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function calling_sync_again_after_a_settings_change_updates_config_without_a_process_restart(): void
    {
        Setting::create(['group' => 'email', 'key' => 'smtp_host', 'value' => 'smtp.first.test', 'type' => SettingType::String]);

        $service = new RuntimeSettingsSyncService();
        $service->sync(app(SettingsService::class));

        $this->assertSame('smtp.first.test', config('mail.mailers.smtp.host'));

        // Simulate an admin editing the setting mid-process — SettingsService::set()
        // busts the group cache, so a fresh sync() call must see the new value
        // immediately, not whatever boot() saw once at process start.
        app(SettingsService::class)->set('email.smtp_host', 'smtp.updated.test');

        $service->sync(app(SettingsService::class));

        $this->assertSame('smtp.updated.test', config('mail.mailers.smtp.host'));
    }

    #[Test]
    public function it_swallows_a_database_failure_instead_of_throwing(): void
    {
        \Illuminate\Support\Facades\Schema::drop('settings');

        $service = new RuntimeSettingsSyncService();
        $service->sync(app(SettingsService::class));

        $this->assertTrue(true, 'sync() did not throw even though the settings table is gone.');
    }
}
