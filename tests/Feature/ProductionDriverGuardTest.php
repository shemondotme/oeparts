<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
    public function production_with_database_cache_driver_throws(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'database', 'queue.default' => 'redis', 'session.driver' => 'redis']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cache.default');

        $this->boot();
    }

    #[Test]
    public function production_with_sync_queue_connection_throws(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'redis', 'queue.default' => 'sync', 'session.driver' => 'redis']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('queue.default');

        $this->boot();
    }

    #[Test]
    public function production_with_file_session_driver_throws(): void
    {
        $this->app['env'] = 'production';
        config(['cache.default' => 'redis', 'queue.default' => 'redis', 'session.driver' => 'file']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('session.driver');

        $this->boot();
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
