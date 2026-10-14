<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\MigrationQueues;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\QueueManager;

/**
 * Holds every queue worker that serves customers while an import replaces the data they would write to.
 *
 * Two layers, because each alone has a hole:
 *
 *   the pause   the customer queues are paused on every real connection. It lives in the CACHE, which the import changes the
 *               prefix of (and which an operator may flush), so it is applied before the prefix changes and again after;
 *   the listener  every worker asks "may I take a job?" before each loop. While the import marker file exists the answer is
 *               no for every worker except one whose queue list is EXACTLY the migration queue. The marker is a plain file,
 *               independent of any cache or database, so this holds even when the pause is gone.
 *
 * Resuming lifts the pause; the listener lets go the moment the marker is removed, which the caller does right after.
 */
final class ImportQueueGuard implements QueueGuard
{
    public function __construct(
        private readonly QueueManager $queues,
        private readonly ImportMarker $marker,
    ) {}

    public function hold(): void
    {
        $seconds = max(3600, (int) config('migration.import.queue_hold_seconds', 86400));

        foreach ($this->connections() as $connection) {
            foreach (MigrationQueues::customer() as $queue) {
                $this->queues->pauseFor($connection, $queue, $seconds);
            }
        }
    }

    public function resume(): void
    {
        foreach ($this->connections() as $connection) {
            foreach (MigrationQueues::customer() as $queue) {
                $this->queues->resume($connection, $queue);
            }
        }
    }

    /** The answer to a worker's "may I take a job?": false (stop) while an import holds the site, except for a migration-only worker. */
    public function mayWork(Looping $event): ?bool
    {
        if (! $this->marker->isActive()) {
            return null;
        }

        $served = array_values(array_filter(array_map('trim', explode(',', (string) $event->queue)), static fn (string $q): bool => $q !== ''));

        return $served === [MigrationQueues::migration()] ? null : false;
    }

    /** @return array<int, string> the queue connections a worker can serve (not sync, not null) */
    private function connections(): array
    {
        $names = [];

        foreach ((array) config('queue.connections', []) as $name => $config) {
            if (! in_array($config['driver'] ?? null, ['sync', 'null', null], true)) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }
}
