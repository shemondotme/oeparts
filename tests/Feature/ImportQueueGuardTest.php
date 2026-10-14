<?php

namespace Tests\Feature;

use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportQueueGuard;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 6.6b: the workers that serve customers stand still while an import replaces the data, whatever happens to the cache.
 */
class ImportQueueGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new ImportMarker)->remove();
        config(['queue.connections.migrationtest' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]]);
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        app(ImportQueueGuard::class)->resume();

        parent::tearDown();
    }

    #[Test]
    public function the_application_uses_the_real_guard(): void
    {
        $this->assertInstanceOf(ImportQueueGuard::class, app(QueueGuard::class));
    }

    #[Test]
    public function holding_pauses_every_customer_queue_but_never_the_migration_queue_and_resuming_lifts_it(): void
    {
        $queues = app(QueueManager::class);
        $guard = app(ImportQueueGuard::class);

        $guard->hold();

        foreach (['critical', 'default', 'low'] as $queue) {
            $this->assertTrue($queues->isPaused('migrationtest', $queue), $queue);
        }
        $this->assertFalse($queues->isPaused('migrationtest', 'migration'), 'the queue the import work runs on is never paused');

        $guard->resume();

        foreach (['critical', 'default', 'low'] as $queue) {
            $this->assertFalse($queues->isPaused('migrationtest', $queue), $queue);
        }
    }

    #[Test]
    public function with_no_import_running_every_worker_is_left_alone(): void
    {
        $guard = app(ImportQueueGuard::class);

        foreach (['default', 'critical,default,low', 'migration', 'migration,default'] as $served) {
            $this->assertNull($guard->mayWork(new Looping('database', $served)), $served);
        }
    }

    #[Test]
    public function while_an_import_runs_only_a_worker_that_serves_exactly_the_migration_queue_may_work(): void
    {
        (new ImportMarker)->write('abc123abc123abc1');
        $guard = app(ImportQueueGuard::class);

        foreach (['default', 'critical,default,low', 'migration,default', 'low'] as $served) {
            $this->assertFalse($guard->mayWork(new Looping('database', $served)), "{$served} must stand still");
        }
        $this->assertNull($guard->mayWork(new Looping('database', 'migration')));
        $this->assertNull($guard->mayWork(new Looping('database', ' migration ')), 'whitespace does not change which queue it is');
    }

    #[Test]
    public function the_hold_survives_the_cache_being_flushed_because_the_marker_is_a_file(): void
    {
        (new ImportMarker)->write('abc123abc123abc1');
        app(ImportQueueGuard::class)->hold();
        Cache::flush();

        $this->assertFalse(app(QueueManager::class)->isPaused('migrationtest', 'default'), 'the pause itself was lost with the cache');
        $this->assertFalse(Event::until(new Looping('database', 'default')), 'but the worker is still told to stand still');
        $this->assertNull(Event::until(new Looping('database', 'migration')));
    }

    #[Test]
    public function the_workers_are_free_again_as_soon_as_the_marker_is_removed(): void
    {
        $marker = new ImportMarker;
        $marker->write('abc123abc123abc1');
        $this->assertFalse(Event::until(new Looping('database', 'default')));

        $marker->remove();

        $this->assertNull(Event::until(new Looping('database', 'default')));
    }

    #[Test]
    public function a_long_lived_worker_lets_go_when_another_process_removes_the_marker(): void
    {
        $marker = new ImportMarker;
        $marker->write('abc123abc123abc1');
        $guard = app(ImportQueueGuard::class);
        $this->assertFalse($guard->mayWork(new Looping('database', 'default')));

        // The import's own process removes the file, not this one (a worker lives for days and cannot rely on what it saw before).
        exec('rm -f '.escapeshellarg($marker->path()));

        $this->assertFalse($marker->isActive());
        $this->assertNull($guard->mayWork(new Looping('database', 'default')));
    }
}
