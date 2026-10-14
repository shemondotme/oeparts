<?php

namespace App\Services\Instance;

use App\Services\Instance\Readiness\InstanceQueueProbe;

/**
 * Changes the mode of this installation (quarantine to production or staging, staging to production) and proves that the queue
 * workers followed.
 *
 * The mode lives in .env, which a worker reads once when it starts. So the order matters and is fixed: InstanceEnv tells the workers
 * to restart FIRST (they find that signal in the cache under the prefix they started with), then writes the new value and reloads
 * the configuration of this process. After that the switch asks a worker, through a probe job, what mode it now believes, and does
 * not call the switch done until a worker agrees: a worker that kept the old mode would go on dropping (or sending) customer mail
 * the wrong way, silently.
 */
class InstanceModeSwitcher
{
    public const WORKER_CONFIRMED = 'confirmed';

    public const WORKER_STALE = 'stale';

    public const WORKER_NONE = 'none_answered';

    public const WORKER_SKIPPED = 'skipped';

    public function __construct(
        private readonly InstanceEnv $env,
        private readonly InstanceQueueProbe $probe,
        private readonly SafeMode $safe,
    ) {}

    /**
     * @return array{from: string, to: string, worker: string, wrote: bool}
     *                                                                      `worker` says what the workers did: confirmed (one reported the new mode), stale (one answered with the old one and kept
     *                                                                      doing so for the whole wait), none_answered (no worker answered), skipped (the queue is `sync`, there is no worker)
     */
    public function switch(InstanceMode $to, string $by, int $waitSeconds = 30): array
    {
        $from = $this->safe->mode();
        $wrote = false;

        if ($from !== $to) {
            $this->env->apply(['OE_MODE' => $to->value], $by);
            $wrote = true;
        }

        return ['from' => $from->value, 'to' => $to->value, 'worker' => $this->confirmWorker($to, $waitSeconds), 'wrote' => $wrote];
    }

    /** Ask until a worker reports the wanted mode (and this installation's identity) or the time is up. */
    private function confirmWorker(InstanceMode $to, int $waitSeconds): string
    {
        if (! $this->probe->available()) {
            return self::WORKER_SKIPPED;
        }

        $deadline = microtime(true) + $waitSeconds;
        $answered = false;

        do {
            $seen = $this->probe->ask(min(5, max(1, $waitSeconds)));

            if ($seen !== null) {
                $answered = true;

                if ($seen['mode'] === $to->value && $seen['instance_id'] === (string) config('instance.id')) {
                    return self::WORKER_CONFIRMED;
                }
            }
        } while (microtime(true) < $deadline);

        return $answered ? self::WORKER_STALE : self::WORKER_NONE;
    }
}
