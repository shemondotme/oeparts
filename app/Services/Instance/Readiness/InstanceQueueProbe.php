<?php

namespace App\Services\Instance\Readiness;

/**
 * Asks a queue worker what it believes (see InstanceQueueProbeJob) and waits for the answer to THIS question, recognised by a
 * nonce, so an old answer is never mistaken for a new one.
 *
 * On the `sync` queue the job would run here, in this very process, and prove nothing about any worker: asking then returns null
 * and the checks say so in plain words instead of passing.
 */
class InstanceQueueProbe
{
    public function __construct(private readonly ?\Closure $sleep = null) {}

    public function available(): bool
    {
        return config('queue.default') !== 'sync';
    }

    /**
     * @return array{nonce: string, at: string, pid: int, mode: string, instance_id: string, queue: string|null, connection: string|null}|null
     *                                                                                                                                         what the worker said, or null when none answered in time (or the queue is `sync`)
     */
    public function ask(?int $waitSeconds = null): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $waitSeconds ??= (int) config('instance.readiness.worker_wait_seconds', 10);
        $nonce = bin2hex(random_bytes(8));

        InstanceQueueProbeJob::dispatch($nonce);

        $sleep = $this->sleep ?? static fn (int $ms) => usleep($ms * 1000);
        $deadline = microtime(true) + $waitSeconds;

        do {
            $seen = $this->read();

            if ($seen !== null && ($seen['nonce'] ?? null) === $nonce) {
                return $seen;
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            $sleep(300);
        } while (true);

        return null;
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        try {
            return InstanceQueueProbeJob::file()->read();
        } catch (\RuntimeException) {
            return null;
        }
    }
}
