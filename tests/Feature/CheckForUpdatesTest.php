<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Updates\UpdateChecker;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `oeparts:update:check` (Module 21, Chunk 1.2/1.4) — the scheduled/manual
 * update check, and the admin-notification side effect it triggers on a
 * newly-discovered version.
 */
class CheckForUpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, RolesSeeder::class]);

        config()->set('updates.enabled', true);
        config()->set('updates.channel', 'stable');
        config()->set('updates.check.catalog_url', 'https://updates.test/releases.json');
        config()->set('updates.check.manifest_url', 'https://updates.test/version.json');

        Cache::forget(UpdateChecker::CACHE_KEY);
        Cache::forget('oe_updates.notified_version');
    }

    private function fakeAvailableRelease(): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                [
                    'version' => '9.9.9',
                    'min_version_to_update_from' => '0.0.0',
                    'security' => false,
                    'download_url' => 'https://x/oeparts.zip',
                    'sha256' => str_repeat('a', 64),
                ],
            ]], 200),
            '*' => Http::response('', 500),
        ]);
    }

    /**
     * QUEUE_CONNECTION=sync (a valid, documented default for a fresh install
     * with no Redis) runs NotifyAdminsOfUpdate inline — an unconfigured or
     * failing SMTP server previously crashed THIS ENTIRE command with an
     * uncaught mail exception, even though update detection itself (the
     * command's actual job) had already succeeded. Confirmed live against a
     * real fresh install with the shipped .env.example's placeholder SMTP
     * host. This matters most for the scheduled daily run (routes/console.php)
     * — a crashed scheduled command reads as "the update check is broken",
     * not "mail isn't configured yet".
     */
    #[Test]
    public function a_failing_notification_email_does_not_crash_the_command(): void
    {
        $this->fakeAvailableRelease();

        $super = Admin::factory()->create(['is_active' => true, 'email' => 'super@oeparts.test']);
        $super->assignRole('super_admin');

        Mail::shouldReceive('to')->andReturnUsing(fn () => new class
        {
            public function send($mailable): void
            {
                throw new \RuntimeException('SMTP failure (simulated)');
            }
        });

        $this->artisan('oeparts:update:check')->assertSuccessful();
    }

    #[Test]
    public function a_failed_notification_is_not_marked_as_sent_so_the_next_run_retries(): void
    {
        $this->fakeAvailableRelease();

        $super = Admin::factory()->create(['is_active' => true, 'email' => 'super@oeparts.test']);
        $super->assignRole('super_admin');

        Mail::shouldReceive('to')->andReturnUsing(fn () => new class
        {
            public function send($mailable): void
            {
                throw new \RuntimeException('SMTP failure (simulated)');
            }
        });

        $this->artisan('oeparts:update:check')->assertSuccessful();

        $this->assertNull(Cache::get('oe_updates.notified_version'), 'a failed send must not be recorded as notified');
    }

    #[Test]
    public function a_successful_notification_is_marked_sent_and_not_repeated(): void
    {
        $this->fakeAvailableRelease();
        Mail::fake();

        $super = Admin::factory()->create(['is_active' => true, 'email' => 'super@oeparts.test']);
        $super->assignRole('super_admin');

        $this->artisan('oeparts:update:check')->assertSuccessful();

        $this->assertSame('9.9.9', Cache::get('oe_updates.notified_version'));
    }
}
