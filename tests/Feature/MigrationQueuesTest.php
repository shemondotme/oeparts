<?php

namespace Tests\Feature;

use App\Services\Migration\Export\MigrationQueueProbeJob;
use App\Services\Migration\Export\MigrationWorkerProbe;
use App\Services\Migration\MigrationQueues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.10c: the dedicated migration queue and the proof that a worker serves it. A freeze pauses the
 * customer queues, so the job that drives an export has to live on a queue it does not pause, and "a worker
 * is running" is proven by a worker answering, never assumed.
 */
class MigrationQueuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearStamp();
        MigrationQueues::forgetWarning();
        config(['queue.default' => 'database']);
    }

    protected function tearDown(): void
    {
        $this->clearStamp();
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function clearStamp(): void
    {
        $path = MigrationQueueProbeJob::stampPath();
        foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
            @unlink($file);
        }
    }

    /** One real worker pass over the given queue list. */
    private function work(string $queues): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => $queues, '--sleep' => 0]);
    }

    private function writeStamp(array $override = []): void
    {
        MigrationQueueProbeJob::file()->write($override + ['at' => now()->toIso8601String(), 'pid' => 1, 'queue' => 'migration', 'connection' => 'database']);
    }

    // ---- the queue names ----------------------------------------------------------------------------

    #[Test]
    public function the_defaults_are_the_three_customer_queues_and_one_migration_queue(): void
    {
        $this->assertSame('migration', MigrationQueues::migration());
        $this->assertSame(['critical', 'default', 'low'], MigrationQueues::customer());
        $this->assertSame('default', MigrationQueues::probe());
    }

    #[Test]
    public function the_migration_queue_can_never_be_among_the_customer_queues(): void
    {
        $channel = \Mockery::mock();
        $channel->shouldReceive('warning')->once()->with(\Mockery::pattern('/migration.*removed/'));
        Log::shouldReceive('channel')->with('migration')->andReturn($channel);
        config(['migration.queues.customer' => ['critical', 'migration', 'default', 'low']]);

        $this->assertSame(['critical', 'default', 'low'], MigrationQueues::customer());
        MigrationQueues::customer(); // a second look at the same mistake does not log again

        config(['migration.queues.customer' => ['migration']]);
        $this->assertSame(['default'], MigrationQueues::customer(), 'a list of nothing but the migration queue falls back to the default queue');
    }

    #[Test]
    public function the_probe_queue_is_always_one_of_the_customer_queues(): void
    {
        config(['migration.queues.probe' => 'low']);
        $this->assertSame('low', MigrationQueues::probe());

        config(['migration.queues.probe' => 'migration']);
        $this->assertSame('critical', MigrationQueues::probe(), 'not a customer queue: the first customer queue instead');

        config(['migration.queues.probe' => 'nonsense', 'migration.queues.customer' => ['bulk', 'default']]);
        $this->assertSame('bulk', MigrationQueues::probe());
    }

    #[Test]
    public function whitespace_and_blanks_in_the_lists_are_ignored(): void
    {
        config(['migration.queues.customer' => [' critical ', '', 'default', 'default'], 'migration.queues.migration' => ' migration ']);

        $this->assertSame(['critical', 'default'], MigrationQueues::customer());
        $this->assertSame('migration', MigrationQueues::migration());
    }

    // ---- the worker proof -----------------------------------------------------------------------------

    #[Test]
    public function with_the_sync_queue_nothing_can_be_proven_and_nothing_is_dispatched(): void
    {
        config(['queue.default' => 'sync']);
        $this->writeStamp(['connection' => 'sync']);

        $probe = new MigrationWorkerProbe;

        $this->assertFalse($probe->run());
        $this->assertFalse($probe->isFresh(), 'even a stamp on disk does not count while the queue is sync');
    }

    #[Test]
    public function without_a_stamp_or_with_an_old_or_foreign_one_there_is_no_proof(): void
    {
        $probe = new MigrationWorkerProbe;
        $this->assertFalse($probe->isFresh(), 'no stamp');

        $this->writeStamp(['at' => now()->subMinutes(11)->toIso8601String()]);
        $this->assertFalse($probe->isFresh(), 'older than probe_fresh_seconds (600)');

        $this->writeStamp(['queue' => 'default']);
        $this->assertFalse($probe->isFresh(), 'answered on some other queue: that proves nothing about the migration queue');

        $this->writeStamp(['connection' => 'sync']);
        $this->assertFalse($probe->isFresh());

        $this->writeStamp();
        $this->assertTrue($probe->isFresh());
    }

    #[Test]
    public function a_stamp_goes_stale_after_the_configured_time_on_a_fake_clock(): void
    {
        $probe = new MigrationWorkerProbe(fn () => Carbon::now());
        $this->writeStamp();

        $this->assertTrue($probe->isFresh());

        Carbon::setTestNow(now()->addSeconds(599));
        $this->assertTrue($probe->isFresh());

        Carbon::setTestNow(now()->addSeconds(2));
        $this->assertFalse($probe->isFresh());

        config(['migration.queues.probe_fresh_seconds' => 3600]);
        $this->assertTrue($probe->isFresh(), 'the window is the configured one');
    }

    #[Test]
    public function an_unreadable_stamp_proves_nothing(): void
    {
        file_put_contents(MigrationQueueProbeJob::stampPath(), 'not json');
        @unlink(MigrationQueueProbeJob::stampPath().'.prev');

        $this->assertFalse((new MigrationWorkerProbe)->isFresh());
        $this->assertNull((new MigrationWorkerProbe)->lastSeen());
    }

    #[Test]
    public function a_worker_serving_a_mixed_list_or_a_dedicated_migration_list_proves_itself(): void
    {
        foreach (['migration,critical,default,low', 'migration', 'critical,migration'] as $list) {
            $this->clearStamp();
            DB::table('jobs')->delete();
            $probe = new MigrationWorkerProbe;

            $this->assertFalse($probe->isFresh());
            $this->assertTrue($probe->run());
            $this->assertSame(1, DB::table('jobs')->where('queue', 'migration')->count(), 'the probe waits on the migration queue');

            $this->work($list);

            $this->assertTrue($probe->isFresh(), "worker line '{$list}'");
            $this->assertSame('migration', $probe->lastSeen()['queue']);
            $this->assertSame('database', $probe->lastSeen()['connection']);
        }
    }

    #[Test]
    public function an_old_worker_line_that_never_gained_the_migration_queue_cannot_prove_itself(): void
    {
        $probe = new MigrationWorkerProbe;
        $probe->run();

        $this->work('critical,default,low');

        $this->assertFalse($probe->isFresh(), 'the worker is alive but does not serve the migration queue');
        $this->assertSame(1, DB::table('jobs')->where('queue', 'migration')->count(), 'the probe job is still waiting, unconsumed');
    }

    #[Test]
    public function run_and_wait_returns_true_once_a_worker_answers_and_false_when_none_does(): void
    {
        $probe = new MigrationWorkerProbe;

        $this->assertFalse($probe->runAndWait(1, fn (int $ms) => usleep(100_000)), 'nobody is listening');

        DB::table('jobs')->delete();
        $answered = $probe->runAndWait(10, function (int $ms): void {
            $this->work('migration,critical,default,low');
        });

        $this->assertTrue($answered);
    }

    #[Test]
    public function a_probe_that_was_proven_long_ago_does_not_count_as_an_answer_to_a_new_question(): void
    {
        $this->writeStamp(['at' => now()->subMinutes(5)->toIso8601String()]);
        DB::table('jobs')->delete();

        $probe = new MigrationWorkerProbe;

        $this->assertTrue($probe->isFresh(), 'five minutes ago is still fresh');
        $this->assertFalse($probe->runAndWait(1, fn (int $ms) => usleep(100_000)), 'but waiting for a NEW answer needs a new stamp');
    }
}
