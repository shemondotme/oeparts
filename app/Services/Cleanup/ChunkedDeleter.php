<?php

namespace App\Services\Cleanup;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Deletes qualifying rows of one table in short, bounded, resumable slices — the
 * only thing in the engine that issues DELETE statements against the database.
 *
 * Why not one `DELETE ... WHERE created_at < ?`: on a table with millions of rows that is
 * a single transaction holding locks for minutes, bloating the binlog in one piece and
 * stalling replicas. Why not `DELETE ... LIMIT n`: it is non-deterministic and unsafe
 * under statement-based replication. And never TRUNCATE: it is DDL (implicit commit, no
 * row count, fails on tables with foreign keys), so "truncate all" is this same loop with
 * a cutoff of "now".
 *
 * The loop walks the primary key in windows (default 50 000 ids) so that even a filter on
 * an UNINDEXED column (failed_jobs.failed_at, notifications.read_at, ...) costs only a
 * bounded range scan per step, never a full-table scan. Inside a window it picks at most
 * `chunk` ids, then deletes exactly those ids and trusts the database's affected-row count.
 *
 * The chunk size adapts to what the database can take right now: a statement that takes
 * longer than a second halves it (down to 200), one that takes under 100 ms grows it by
 * half (up to the configured ceiling).
 *
 * State (all JSON-serialisable, persisted by the caller between ticks):
 *   cursor (last id passed), chunk, deleted, failed, errors, last_error, archive_rows
 */
final class ChunkedDeleter
{
    public const WINDOW = 50_000;

    public const MIN_CHUNK = 200;

    /** Consecutive failed chunks before the whole phase gives up. */
    public const MAX_CONSECUTIVE_ERRORS = 5;

    /** @param  int  $window  ids per primary-key window (overridable so tests can exercise several windows) */
    public function __construct(private readonly int $window = self::WINDOW) {}

    /**
     * Do as much deleting as fits before $deadline.
     *
     * @param  Closure():Builder  $candidates  fresh query carrying every predicate EXCEPT the id window
     * @param  array<string, mixed>  $state
     * @param  (Closure(list<int>):int)|null  $beforeDelete  archive hook; receives the ids about to go, returns rows archived
     * @param  (Closure():bool)|null  $shouldStop  e.g. cancel requested
     * @return bool true when the whole id range up to $maxId has been processed
     */
    public function step(
        string $table,
        string $pk,
        Closure $candidates,
        int $maxId,
        array &$state,
        float $deadline,
        int $maxChunk,
        int $sleepMs,
        ?Closure $beforeDelete = null,
        ?Closure $shouldStop = null,
    ): bool {
        $state += ['cursor' => 0, 'chunk' => min(2000, $maxChunk), 'deleted' => 0, 'failed' => 0, 'errors' => 0, 'archive_rows' => 0];
        $state['chunk'] = max(self::MIN_CHUNK, min((int) $state['chunk'], max(self::MIN_CHUNK, $maxChunk)));

        while ((int) $state['cursor'] < $maxId) {
            if ($shouldStop !== null && $shouldStop()) {
                return false;
            }

            $cursor = (int) $state['cursor'];
            $windowEnd = min($cursor + max(1, $this->window), $maxId);
            $chunk = (int) $state['chunk'];

            /** @var list<int> $ids */
            $ids = $candidates()
                ->where($pk, '>', $cursor)
                ->where($pk, '<=', $windowEnd)
                ->orderBy($pk)
                ->limit($chunk)
                ->pluck($pk)
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($ids === []) {
                $state['cursor'] = $windowEnd; // nothing left in this window

                if (microtime(true) >= $deadline) {
                    return (int) $state['cursor'] >= $maxId;
                }

                continue;
            }

            $started = microtime(true);

            try {
                if ($beforeDelete !== null) {
                    $state['archive_rows'] = (int) $state['archive_rows'] + $beforeDelete($ids);
                }

                $deleted = $this->deleteIds($table, $pk, $ids);
            } catch (Throwable $e) {
                $state['failed'] = (int) $state['failed'] + count($ids);
                $state['errors'] = (int) $state['errors'] + 1;
                $state['last_error'] = mb_substr($e->getMessage(), 0, 500);
                // Skip past the ids that failed so one poisoned row cannot spin the loop forever.
                $state['cursor'] = max($ids);

                Log::channel('cleanup')->warning("Cleanup: could not delete a chunk from {$table}", [
                    'ids' => count($ids), 'error' => $e->getMessage(),
                ]);

                if ((int) $state['errors'] >= self::MAX_CONSECUTIVE_ERRORS) {
                    throw new RuntimeException("Giving up on {$table} after ".self::MAX_CONSECUTIVE_ERRORS." consecutive failed chunks: {$e->getMessage()}", 0, $e);
                }

                continue;
            }

            $state['errors'] = 0;
            $state['deleted'] = (int) $state['deleted'] + $deleted;
            // A short batch means the window is exhausted, otherwise continue after the last id.
            $state['cursor'] = count($ids) < $chunk ? $windowEnd : max($ids);

            $state['chunk'] = $this->adapt($chunk, microtime(true) - $started, $maxChunk);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            // Checked AFTER the work, never before it: every call makes at least one chunk of
            // progress, so a tick whose budget was already spent elsewhere can never stall the run.
            if (microtime(true) >= $deadline) {
                return (int) $state['cursor'] >= $maxId;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $ids
     */
    private function deleteIds(string $table, string $pk, array $ids): int
    {
        try {
            return DB::table($table)->whereIn($pk, $ids)->delete();
        } catch (Throwable $first) {
            // One immediate retry covers the usual transient (lock wait timeout, deadlock).
            usleep(200_000);

            return DB::table($table)->whereIn($pk, $ids)->delete();
        }
    }

    private function adapt(int $chunk, float $seconds, int $maxChunk): int
    {
        if ($seconds > 1.0) {
            return max(self::MIN_CHUNK, intdiv($chunk, 2));
        }

        if ($seconds < 0.1) {
            return min(max(self::MIN_CHUNK, $maxChunk), intdiv($chunk * 3, 2));
        }

        return $chunk;
    }
}
