<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfAutoUpdate;
use App\Services\Updates\UpdateApplier;
use App\Services\Updates\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unattended security-update auto-apply (config('updates.auto_apply_security')).
 * Drives the SAME FakeUpdateApplier used by UpdateApplierTest (tests/Feature/
 * UpdateApplierTest.php) — proves this command is just a scheduled trigger
 * around the existing, already-tested apply FSM, not a separate code path.
 */
class AutoApplySecurityUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-auto-apply-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);

        config()->set('updates.enabled', true);
        config()->set('updates.channel', 'stable');
        config()->set('updates.check.catalog_url', 'https://updates.test/releases.json');
        config()->set('updates.check.manifest_url', 'https://updates.test/version.json');

        Cache::forget(UpdateChecker::CACHE_KEY);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->state.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->state);

        parent::tearDown();
    }

    private function fakeSecurityRelease(): void
    {
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                [
                    'version' => '9.9.9',
                    'min_version_to_update_from' => '0.0.0',
                    'security' => true,
                    'download_url' => 'https://x/oeparts.zip',
                    'sha256' => str_repeat('a', 64),
                ],
            ]], 200),
            '*' => Http::response('', 500),
        ]);
    }

    #[Test]
    public function it_does_nothing_when_auto_apply_is_disabled(): void
    {
        config(['updates.auto_apply_security' => false]);
        $this->fakeSecurityRelease();

        $this->artisan('oeparts:update:auto-apply')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_does_nothing_when_no_update_is_available(): void
    {
        config(['updates.auto_apply_security' => true]);
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [['version' => '0.0.1']]], 200),
            '*' => Http::response('', 500),
        ]);

        $this->artisan('oeparts:update:auto-apply')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_does_nothing_when_the_available_update_is_not_security(): void
    {
        config(['updates.auto_apply_security' => true]);
        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                [
                    'version' => '9.9.9',
                    'min_version_to_update_from' => '0.0.0',
                    'security' => false,
                    'download_url' => 'https://x/oeparts.zip',
                ],
            ]], 200),
            '*' => Http::response('', 500),
        ]);

        $this->artisan('oeparts:update:auto-apply')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_applies_a_pending_security_update_and_notifies_admins_of_success(): void
    {
        config(['updates.auto_apply_security' => true]);
        $this->fakeSecurityRelease();
        $this->app->bind(UpdateApplier::class, fn () => new FakeUpdateApplier);

        $this->artisan('oeparts:update:auto-apply')->assertSuccessful();

        Queue::assertPushed(NotifyAdminsOfAutoUpdate::class, function ($job) {
            return $job->result['success'] === true
                && $job->result['to_version'] === '9.9.9';
        });
    }

    #[Test]
    public function it_notifies_admins_of_failure_when_the_apply_fails(): void
    {
        config(['updates.auto_apply_security' => true]);
        $this->fakeSecurityRelease();
        $fake = new FakeUpdateApplier;
        $fake->failAt = 'download';
        $this->app->bind(UpdateApplier::class, fn () => $fake);

        $this->artisan('oeparts:update:auto-apply')->assertSuccessful();

        Queue::assertPushed(NotifyAdminsOfAutoUpdate::class, function ($job) {
            return $job->result['success'] === false
                && str_contains((string) $job->result['error'], '[download]');
        });
    }

    #[Test]
    public function it_is_registered_in_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->assertSuccessful()
            ->expectsOutputToContain('oeparts:update:auto-apply');
    }
}
