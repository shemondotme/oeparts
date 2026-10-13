<?php

namespace Tests\Feature;

use App\Jobs\PushIndexNow;
use App\Models\IndexNowPushLog;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PushIndexNow swallows two kinds of secondary failure on purpose (the bell
 * notification and the activity-log row) so they never mask the push itself.
 * Swallowing without a trace hid real bugs elsewhere in this codebase, so each
 * one must leave a log line.
 */
class PushIndexNowLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->set('seo.indexnow_enabled', 'true');
        app(SettingsService::class)->set('seo.indexnow_api_key', 'abc123');
    }

    /** AdminNotifier reads the admins table; without it every notification throws. */
    private function breakNotifications(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::drop('admins');
        Schema::enableForeignKeyConstraints();
    }

    #[Test]
    public function a_broken_success_notification_is_logged_and_does_not_fail_the_job(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
        Log::spy();
        $this->breakNotifications();

        (new PushIndexNow(['https://example.test/a']))->handle();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($m) => str_contains($m, 'IndexNow: the success notification could not be sent'))
            ->once();
        $this->assertSame('success', IndexNowPushLog::first()->status);
    }

    #[Test]
    public function a_failed_activity_log_write_is_logged_and_does_not_mask_the_push(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
        Log::spy();
        Schema::drop('indexnow_push_logs');

        (new PushIndexNow(['https://example.test/a']))->handle();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($m) => str_contains($m, "IndexNow: could not record the 'success' push"))
            ->once();
    }

    #[Test]
    public function a_broken_failure_notification_is_logged_together_with_the_original_failure(): void
    {
        Log::spy();
        $this->breakNotifications();

        (new PushIndexNow(['https://example.test/a']))->failed(new \RuntimeException('IndexNow push failed with status 500'));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($m) => str_contains($m, 'IndexNow: the failure notification could not be sent') && str_contains($m, 'status 500'))
            ->once();
        $this->assertSame('failed', IndexNowPushLog::first()->status);
    }
}
