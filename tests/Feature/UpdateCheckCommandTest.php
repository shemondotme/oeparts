<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfUpdate;
use App\Services\Updates\UpdateChecker;
use App\Services\Updates\UpdateStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update & Recovery System (Module 21) — Chunk 1.2 scheduled/manual check command.
 */
class UpdateCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('updates.enabled', true);
        config()->set('updates.channel', 'stable');
        config()->set('updates.check.catalog_url', 'https://updates.test/releases.json');
        config()->set('updates.check.manifest_url', 'https://updates.test/version.json');

        Cache::forget(UpdateChecker::CACHE_KEY);
        Cache::forget('oe_updates.notified_version');
        Queue::fake();
    }

    #[Test]
    public function it_warms_the_cache_when_an_update_is_available(): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                ['version' => '9.9.9', 'min_version_to_update_from' => '0.0.0', 'security' => true,
                 'download_url' => 'https://x/oeparts.zip'],
            ]], 200),
            '*' => Http::response('', 500), // block any unexpected real network
        ]);

        $this->artisan('oeparts:update:check')->assertSuccessful();

        $cached = Cache::get(UpdateChecker::CACHE_KEY);
        $this->assertInstanceOf(UpdateStatus::class, $cached);
        $this->assertTrue($cached->updateAvailable);
        $this->assertSame('9.9.9', $cached->latestVersion);
        $this->assertTrue($cached->security);
    }

    #[Test]
    public function it_supports_json_output(): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [['version' => '9.9.9']]], 200),
            '*' => Http::response('', 500),
        ]);

        $this->artisan('oeparts:update:check', ['--json' => true])->assertSuccessful();
    }

    #[Test]
    public function it_succeeds_even_when_the_server_is_unreachable(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        // A transient outage must not make the scheduled job report failure.
        $this->artisan('oeparts:update:check')->assertSuccessful();
    }

    #[Test]
    public function it_is_registered_in_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->assertSuccessful()
            ->expectsOutputToContain('oeparts:update:check');
    }

    #[Test]
    public function it_notifies_super_admins_once_per_new_version(): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                ['version' => '9.9.9', 'min_version_to_update_from' => '0.0.0', 'security' => true, 'download_url' => 'https://x/z.zip'],
            ]], 200),
            '*' => Http::response('', 500),
        ]);

        $this->artisan('oeparts:update:check')->assertSuccessful();
        $this->artisan('oeparts:update:check')->assertSuccessful(); // same version — must not re-notify

        Queue::assertPushed(NotifyAdminsOfUpdate::class, 1);
    }

    #[Test]
    public function it_does_not_notify_when_up_to_date(): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                ['version' => '0.0.1'], // older than the installed version
            ]], 200),
            '*' => Http::response('', 500),
        ]);

        $this->artisan('oeparts:update:check')->assertSuccessful();

        Queue::assertNotPushed(NotifyAdminsOfUpdate::class);
    }
}
