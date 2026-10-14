<?php

namespace App\Services\Instance\Readiness;

use App\Services\Instance\SafeMode;
use App\Services\Migration\MigrationQueues;
use App\Support\AtomicJsonFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A job that, when a worker picks it up, writes down what THAT worker process believes: which mode it runs in and which
 * installation it thinks it is. A worker reads .env once, when it starts, so after a mode change a worker that was not
 * restarted keeps the old answer, and this is the only honest way to find out (a mail dropped by a stale quarantine worker after
 * going live would be silent). It goes to a customer queue, so a worker line that never gained the migration queue still answers.
 */
class InstanceQueueProbeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $nonce)
    {
        $this->onQueue(MigrationQueues::probe());
    }

    public function handle(): void
    {
        self::file()->write([
            'nonce' => $this->nonce,
            'at' => now()->toIso8601String(),
            'pid' => getmypid(),
            'mode' => app(SafeMode::class)->mode()->value,
            'instance_id' => (string) config('instance.id'),
            'queue' => $this->job?->getQueue(),
            'connection' => $this->job?->getConnectionName(),
        ]);
    }

    public static function file(): AtomicJsonFile
    {
        return new AtomicJsonFile(storage_path('app/instance/queue-probe.json'));
    }
}
