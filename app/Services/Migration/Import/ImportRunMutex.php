<?php

namespace App\Services\Migration\Import;

/**
 * Only one process may advance a given run at a time. The mutex is a kernel flock() on a file in the run's
 * directory, not a cache lock or a database row: the operating system releases it the instant the holding process
 * dies, so a killed request or a crashed CLI can never leave a run stuck behind a time-to-live, and it does not depend
 * on which cache store the web server and the command line happen to resolve (nor on a database that is being
 * replaced). The loser simply reports "busy" and tries again at the next poll.
 */
final class ImportRunMutex
{
    public function __construct(private readonly ImportStateStore $store) {}

    /**
     * @return resource|null the held lock, or null when somebody else holds it
     */
    public function acquire(string $runId, string $name = 'run'): mixed
    {
        $path = $this->store->directory($runId).DIRECTORY_SEPARATOR.$name.'.lock';
        $handle = @fopen($path, 'c');

        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /** @param  resource  $handle */
    public function release(mixed $handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Run something while holding the lock. Returns false when the lock could not be had (the callback did not run).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return array{ran: bool, result: T|null}
     */
    public function withLock(string $runId, callable $callback, string $name = 'run'): array
    {
        $handle = $this->acquire($runId, $name);

        if ($handle === null) {
            return ['ran' => false, 'result' => null];
        }

        try {
            return ['ran' => true, 'result' => $callback()];
        } finally {
            $this->release($handle);
        }
    }
}
