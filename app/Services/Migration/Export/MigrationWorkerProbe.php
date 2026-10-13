<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\MigrationQueues;
use App\Support\AtomicJsonFile;
use Carbon\CarbonImmutable;

/**
 * Proof that a queue worker really serves the migration queue.
 *
 * `queue.default` being something other than `sync` does not prove any worker is running, and a worker
 * running on an old command line (`critical,default,low`) never touches the migration queue. So a worker is
 * proven, never assumed: run() puts a probe job on the migration queue, and isFresh() is true only when that
 * job has been picked up recently (the probe job writes a stamp when it runs). A freeze export that is to be
 * driven by a queue worker is accepted only while the proof is fresh; otherwise the operator is shown the
 * exact worker command to fix, or runs the export from the command line, which needs no worker at all.
 */
final class MigrationWorkerProbe
{
    public function __construct(private readonly ?\Closure $clock = null) {}

    public static function file(): AtomicJsonFile
    {
        return MigrationQueueProbeJob::file();
    }

    /**
     * Ask a worker to prove itself. Returns false when nothing can be proven because the default connection is
     * `sync` (the job would run right here, in this very process, and say nothing about any worker).
     */
    public function run(): bool
    {
        if ($this->isSync()) {
            return false;
        }

        MigrationQueueProbeJob::dispatch();

        return true;
    }

    /**
     * Ask, then wait up to `$maxSeconds` for the answer.
     */
    public function runAndWait(int $maxSeconds = 15, ?\Closure $sleep = null): bool
    {
        $before = $this->lastSeen()['at'] ?? null;

        if (! $this->run()) {
            return false;
        }

        $sleep ??= static fn (int $ms) => usleep($ms * 1000);
        $deadline = microtime(true) + $maxSeconds;

        while (microtime(true) < $deadline) {
            $seen = $this->lastSeen();

            if ($seen !== null && ($seen['at'] ?? null) !== $before && $this->isFresh()) {
                return true;
            }

            $sleep(500);
        }

        return false;
    }

    /** A worker has answered on the migration queue within `probe_fresh_seconds`. */
    public function isFresh(): bool
    {
        if ($this->isSync()) {
            return false;
        }

        $seen = $this->lastSeen();

        if ($seen === null || ($seen['connection'] ?? 'sync') === 'sync' || ($seen['queue'] ?? null) !== MigrationQueues::migration()) {
            return false;
        }

        try {
            $at = CarbonImmutable::parse((string) ($seen['at'] ?? ''));
        } catch (\Throwable) {
            return false;
        }

        $age = $this->now()->getTimestamp() - $at->getTimestamp();

        return $age >= -5 && $age <= max(1, (int) config('migration.queues.probe_fresh_seconds', 600));
    }

    /** @return array<string, mixed>|null */
    public function lastSeen(): ?array
    {
        try {
            return self::file()->read();
        } catch (\RuntimeException) {
            return null; // an unreadable stamp proves nothing
        }
    }

    private function isSync(): bool
    {
        return config('queue.default') === 'sync';
    }

    private function now(): CarbonImmutable
    {
        return $this->clock !== null ? CarbonImmutable::instance(($this->clock)()) : CarbonImmutable::now();
    }
}
