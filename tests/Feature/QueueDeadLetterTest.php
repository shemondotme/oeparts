<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 15 (Email/Notification & Queue Failure Handling). FailedJobsPageTest
 * thoroughly covers the ADMIN UI over the failed_jobs table, but every one
 * of its fixtures is a manually-inserted row — nothing anywhere actually
 * dispatched a real job, let it genuinely fail, and confirmed Laravel's own
 * retry/backoff/dead-letter machinery lands it in failed_jobs the way this
 * app is actually configured (the test env's default 'sync' queue
 * connection runs a job inline with no retry semantics at all, so this
 * had never been exercised). Switches to the 'database' driver and drives
 * the job through its real $tries/$backoff cycle via queue:work --once,
 * time-traveling past each backoff delay exactly the way a real queue
 * worker polling over minutes would. SendWelcomeEmail is representative —
 * this is a framework-level guarantee once $tries/$backoff are set (see
 * the other 4 fixes this same phase for jobs that were missing them),
 * not per-job app logic, so one thorough end-to-end proof stands in for
 * all 20 Send-/Notify-prefixed jobs rather than repeating this per job.
 */
class QueueDeadLetterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
    }

    private function mockMailToAlwaysThrow(): void
    {
        $pendingMail = \Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')->andThrow(new \RuntimeException('Connection refused by upstream mail host'));
        Mail::shouldReceive('to')->andReturn($pendingMail);
    }

    #[Test]
    public function a_job_that_keeps_failing_lands_in_failed_jobs_only_after_exhausting_its_real_retry_and_backoff_cycle(): void
    {
        $this->mockMailToAlwaysThrow();
        $user = User::factory()->create();

        dispatch(new SendWelcomeEmail($user));
        $this->assertSame(1, DB::table('jobs')->count(), 'the job is queued, not run inline (sync would already be gone)');
        $this->assertSame(0, DB::table('failed_jobs')->count());

        // SendWelcomeEmail runs on the 'critical' queue — queue:work without
        // --queue only listens on 'default' and silently finds nothing to
        // do, so every call below must say --queue=critical explicitly.
        $work = fn () => $this->artisan('queue:work', ['--once' => true, '--tries' => 3, '--queue' => 'critical'])->run();

        // Attempt 1 of 3 — fails, but must NOT be dead-lettered yet: SendWelcomeEmail's
        // own $tries=3 still has 2 attempts left.
        $work();
        $this->assertSame(1, DB::table('jobs')->count(), 'still queued for a retry, not dead-lettered after only 1 of 3 attempts');
        $this->assertSame(0, DB::table('failed_jobs')->count());

        // The job isn't available again until its $backoff[0]=60s has passed —
        // a --once run right now must find nothing to do, so attempts stays at 1.
        $work();
        $this->assertSame(1, DB::table('jobs')->first()->attempts, 'backoff not elapsed yet — the retry must not have run early');

        // Attempt 2 of 3.
        $this->travel(61)->seconds();
        $work();
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(2, DB::table('jobs')->first()->attempts);
        $this->assertSame(0, DB::table('failed_jobs')->count());

        // Attempt 3 of 3 — this is the one that exhausts $tries.
        $this->travel(181)->seconds();
        $work();

        $this->assertSame(0, DB::table('jobs')->count(), 'exhausted — must be gone from the active queue');
        $this->assertSame(1, DB::table('failed_jobs')->count(), 'and land in failed_jobs, not just vanish');

        $failed = DB::table('failed_jobs')->first();
        // Backslashes are JSON-escaped in the stored payload column —
        // decode rather than substring-matching the raw class name.
        $this->assertSame(SendWelcomeEmail::class, json_decode($failed->payload, true)['displayName']);
        $this->assertStringContainsString('Connection refused by upstream mail host', $failed->exception);
    }
}
