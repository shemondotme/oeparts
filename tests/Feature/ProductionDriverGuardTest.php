<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Redis is recommended, not required, for production cache/queue/session
 * drivers (reversed from a hard boot-time RuntimeException — that guard fired
 * on every single request, including the web installer's own first screen,
 * so a fresh deploy without Redis available couldn't even load /install).
 * HealthStrip's admin dashboard checks surface a non-fatal "Redis recommended"
 * warning instead; the app must keep booting on file/database/sync drivers.
 */
class ProductionDriverGuardTest extends TestCase
{
    private function boot(): void
    {
        (new AppServiceProvider($this->app))->boot();
    }

    #[Test]
    public function production_with_all_redis_drivers_does_not_throw(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'redis', 'queue.default' => 'redis', 'session.driver' => 'redis']);

        $this->boot();

        $this->assertTrue(true);
    }

    #[Test]
    public function production_with_database_cache_driver_does_not_throw(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'database', 'queue.default' => 'redis', 'session.driver' => 'redis']);

        $this->boot();

        $this->assertTrue(true);
    }

    #[Test]
    public function production_with_sync_queue_connection_does_not_throw(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'redis', 'queue.default' => 'sync', 'session.driver' => 'redis']);

        $this->boot();

        $this->assertTrue(true);
    }

    #[Test]
    public function production_with_file_session_driver_does_not_throw(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'redis', 'queue.default' => 'redis', 'session.driver' => 'file']);

        $this->boot();

        $this->assertTrue(true);
    }

    #[Test]
    public function production_with_no_redis_anywhere_does_not_throw(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'file', 'queue.default' => 'sync', 'session.driver' => 'file']);

        $this->boot();

        $this->assertTrue(true);
    }

    #[Test]
    public function testing_environment_with_non_redis_drivers_does_not_throw(): void
    {
        $this->app['env'] = 'testing';
        config(['cache.default' => 'array', 'queue.default' => 'sync', 'session.driver' => 'array']);

        $this->boot();

        $this->assertTrue(true);
    }
}
