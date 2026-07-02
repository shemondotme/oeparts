<?php

namespace Tests\Feature;

use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\UpdateFinalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Post-swap boot (Module 21, Chunk 3.4). The plan-shape tests use config only
 * (no execution); the run() tests use a side-effect-free plan (migrate is a
 * no-op on the already-migrated test DB) so the real project isn't mutated.
 */
class UpdateFinalizerTest extends TestCase
{
    use RefreshDatabase;

    private function finalizer(): UpdateFinalizer
    {
        return app(UpdateFinalizer::class);
    }

    /** A plan that only runs migrate (a no-op here) — safe to execute in tests. */
    private function minimalConfig(array $overrides = []): void
    {
        config(['updates.post_swap' => array_merge([
            'artisan'             => [],
            'vendor_publish_tags' => [],
            'seeders'             => [],
            'rebuild_cache'       => false,
            'restart_queue'       => false,
        ], $overrides)]);
    }

    private function keys(): array
    {
        return array_column($this->finalizer()->plan(), 'key');
    }

    /* ---- Plan shape (no execution) ------------------------------------- */

    #[Test]
    public function the_plan_always_starts_with_a_critical_migrate(): void
    {
        $first = $this->finalizer()->plan()[0];

        $this->assertSame('migrate', $first['key']);
        $this->assertTrue($first['critical']);
        $this->assertSame(['--force' => true], $first['params']);
    }

    #[Test]
    public function the_plan_rebuilds_framework_caches_and_skips_queue_restart_under_sync(): void
    {
        config(['queue.default' => 'sync']);
        $this->minimalConfig(['rebuild_cache' => true, 'restart_queue' => true]);

        $keys = $this->keys();

        $this->assertContains('config:clear', $keys);
        $this->assertContains('config:cache', $keys);
        $this->assertContains('event:cache', $keys);
        $this->assertNotContains('queue:restart', $keys, 'sync queue has no workers to restart');
    }

    #[Test]
    public function the_plan_restarts_the_queue_only_for_a_worker_driver(): void
    {
        config(['queue.default' => 'redis']);
        $this->minimalConfig(['restart_queue' => true]);

        $this->assertContains('queue:restart', $this->keys());
    }

    #[Test]
    public function cache_rebuild_can_be_disabled(): void
    {
        $this->minimalConfig(['rebuild_cache' => false]);

        $this->assertNotContains('config:cache', $this->keys());
    }

    #[Test]
    public function seeders_and_publish_tags_expand_into_steps(): void
    {
        $this->minimalConfig([
            'seeders'             => ['Database\\Seeders\\SettingsSeeder'],
            'vendor_publish_tags' => ['laravel-assets'],
        ]);

        $keys = $this->keys();
        $this->assertContains('seed:SettingsSeeder', $keys);
        $this->assertContains('vendor-publish:laravel-assets', $keys);
    }

    /* ---- Execution ----------------------------------------------------- */

    #[Test]
    public function run_executes_migrate(): void
    {
        $this->minimalConfig();

        $report = $this->finalizer()->run();

        $this->assertSame('ok', $report->get('migrate')['status']);
        $this->assertTrue($report->ok());
    }

    #[Test]
    public function a_non_critical_step_failure_is_recorded_but_not_fatal(): void
    {
        $this->minimalConfig(['artisan' => [['command' => 'bogus:command', 'critical' => false]]]);

        $report = $this->finalizer()->run();

        $this->assertSame('fail', $report->get('bogus:command')['status']);
        $this->assertFalse($report->ok());
        $this->assertSame('ok', $report->get('migrate')['status'], 'migrate still ran first');
    }

    #[Test]
    public function a_critical_step_failure_aborts(): void
    {
        $this->minimalConfig(['artisan' => [['command' => 'bogus:command', 'critical' => true]]]);

        $this->expectException(UpdateException::class);
        $this->finalizer()->run();
    }
}
