<?php

namespace App\Services\Cleanup;

use Closure;
use Illuminate\Database\Query\Builder;

/**
 * Counts the rows a cleanup would touch, one primary-key window at a time, so the
 * count is resumable and each query is a bounded range scan — even when the filter is on
 * an unindexed column and the table holds millions of rows. Used for both "how many will
 * go" (plan) and "how many still qualify" (verify): the two MUST use the identical
 * predicate, which is why callers pass the same `$candidates` closure to both.
 *
 * State: cursor (last id counted up to), count.
 */
final class WindowedCounter
{
    /** @param  int  $window  ids per primary-key window (overridable so tests can exercise several windows) */
    public function __construct(private readonly int $window = ChunkedDeleter::WINDOW) {}

    /**
     * @param  Closure():Builder  $candidates  fresh query carrying every predicate EXCEPT the id window
     * @param  array<string, mixed>  $state
     * @return bool true when the whole id range up to $maxId has been counted
     */
    public function step(Closure $candidates, string $pk, int $maxId, array &$state, float $deadline): bool
    {
        $state += ['cursor' => 0, 'count' => 0];

        while ((int) $state['cursor'] < $maxId) {
            $cursor = (int) $state['cursor'];
            $windowEnd = min($cursor + max(1, $this->window), $maxId);

            $state['count'] = (int) $state['count'] + $candidates()
                ->where($pk, '>', $cursor)
                ->where($pk, '<=', $windowEnd)
                ->count();

            $state['cursor'] = $windowEnd;

            // After the work, never before it: each call always makes progress.
            if (microtime(true) >= $deadline) {
                return (int) $state['cursor'] >= $maxId;
            }
        }

        return true;
    }

    /**
     * Rows that qualify but were inserted after the run started (id above the frozen
     * ceiling) — e.g. a backfill with old dates. Informational: they are not part of this
     * run's reconciliation, the next run picks them up.
     *
     * @param  Closure():Builder  $candidates
     */
    public function late(Closure $candidates, string $pk, int $maxId): int
    {
        return $candidates()->where($pk, '>', $maxId)->count();
    }
}
