<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\MigrationPaths;
use App\Services\Migration\MigrationQueues;
use App\Support\AtomicJsonFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A job that does exactly one thing: when a queue worker picks it up from the migration queue, it writes
 * a small file saying so. That file is the proof, and the only proof, that a worker serves the migration
 * queue (see MigrationWorkerProbe). Nothing is ever assumed from `queue.default` or from a worker "being up".
 */
class MigrationQueueProbeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct()
    {
        $this->onQueue(MigrationQueues::migration());
    }

    public function handle(): void
    {
        self::file()->write([
            'at' => now()->toIso8601String(),
            'pid' => getmypid(),
            'queue' => $this->job?->getQueue(),
            'connection' => $this->job?->getConnectionName(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // A probe that fails proves nothing: leave the old stamp as it is (stale) rather than refreshing it.
    }

    public static function stampPath(): string
    {
        return MigrationPaths::root().DIRECTORY_SEPARATOR.'worker-probe.json';
    }

    public static function file(): AtomicJsonFile
    {
        return new AtomicJsonFile(self::stampPath());
    }
}
