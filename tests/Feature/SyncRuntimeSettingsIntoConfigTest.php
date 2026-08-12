<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncRuntimeSettingsIntoConfigTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_http_request_re_applies_a_settings_change_that_happened_after_the_process_booted(): void
    {
        // Simulate the actual staleness bug: config already holds a value
        // from whenever this process's ServiceProvider::boot() last ran,
        // and an admin has since changed the underlying setting — a plain
        // PHP-FPM request booting fresh wouldn't need this middleware at
        // all, but this proves the request path itself corrects a stale
        // value rather than only boot() doing so once.
        config(['mail.mailers.smtp.host' => 'stale-from-a-previous-boot.test']);
        app(SettingsService::class)->set('email.smtp_host', 'fresh-from-settings.test');

        $this->get('/up');

        $this->assertSame('fresh-from-settings.test', config('mail.mailers.smtp.host'));
    }

    #[Test]
    public function a_queued_job_re_applies_settings_before_it_runs(): void
    {
        config(['mail.mailers.smtp.host' => 'stale-from-a-previous-boot.test']);
        app(SettingsService::class)->set('email.smtp_host', 'fresh-from-settings.test');

        dispatch(new NoopSyncTestJob());

        $this->assertSame('fresh-from-settings.test', config('mail.mailers.smtp.host'));
    }
}

/**
 * A trivial named job — PHP disallows serializing anonymous classes, which
 * a real queued job always goes through even on the `sync` connection.
 */
class NoopSyncTestJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        //
    }
}
