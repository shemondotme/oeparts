<?php

namespace Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Phase 18 (Infrastructure/Ops Resilience). Queue worker crash/restart
 * recovery relies entirely on ONE invariant: the redis connection's
 * retry_after (deploy/supervisor/oeparts-queue-worker.conf's worker is
 * autorestart=true, so a crashed/SIGKILLed worker comes back on its own —
 * the risk is a still-genuinely-running job's reservation expiring too
 * EARLY and getting double-processed by the replacement worker) must
 * exceed every job's own $timeout. config/queue.php's comment used to
 * justify this by naming one specific job (ProcessCsvImport) that no
 * longer exists anywhere in the codebase — the invariant still held
 * numerically by coincidence, but nothing would have caught it silently
 * breaking if a new, longer-running job were ever added. This scans every
 * real ShouldQueue job class instead of trusting a comment.
 */
class QueueWorkerCrashRecoveryTest extends TestCase
{
    #[Test]
    public function redis_queue_retry_after_exceeds_every_jobs_own_timeout(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');
        $this->assertGreaterThan(0, $retryAfter);

        $longest = 0;
        $longestJob = null;

        foreach (glob(app_path('Jobs/*.php')) as $file) {
            $class = 'App\\Jobs\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(ShouldQueue::class)) {
                continue;
            }

            if (! $reflection->hasProperty('timeout')) {
                continue; // Laravel's own default (60s) applies — well under any real floor here.
            }

            $timeout = $reflection->getProperty('timeout')->getDefaultValue();

            if (is_int($timeout) && $timeout > $longest) {
                $longest = $timeout;
                $longestJob = $class;
            }
        }

        $this->assertGreaterThan(
            0,
            $longest,
            'Expected to find at least one job declaring $timeout — none matched, check the glob/reflection logic itself.'
        );

        $this->assertGreaterThan(
            $longest,
            $retryAfter,
            "REDIS_QUEUE_RETRY_AFTER ({$retryAfter}s) must exceed {$longestJob}'s \$timeout ({$longest}s), ".
            'or a genuinely still-running job can be double-processed by another worker after a crash/restart.'
        );
    }
}
