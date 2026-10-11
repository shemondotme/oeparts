<?php

namespace App\Services\Cleanup\Targets;

use App\Services\Cleanup\ChunkedDeleter;
use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\TargetRegistry;
use App\Services\Cleanup\WindowedCounter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Base class for every "delete rows older than N days from one table" target — the declarative
 * 95% case. A concrete target only says WHICH table and column, its retention default/floor and
 * any extra predicate; counting, chunked deleting, resuming, archiving and verification are all
 * handled here, once, so every table gets identical (and identically tested) behaviour.
 *
 * Qualifying rows = `age_column < cutoff` AND scope() AND id <= the id ceiling frozen when the
 * item started AND id not in the keep-list. The SAME predicate is used to count before, to delete,
 * and to recount after — that is what makes the verification meaningful.
 *
 * A row whose age column is NULL never qualifies: unknown age is treated as "keep".
 *
 * Checkpoint (all small, JSON-safe): max_id, keep[], plan{cursor,count}, del{cursor,chunk,deleted,
 * failed,errors,archive_rows}, ver{cursor,count}.
 */
abstract class DbPruneTarget implements CleanupTarget
{
    /** Longest keep-list a target may hold in its checkpoint. */
    protected const MAX_KEEP_IDS = 200;

    public function __construct()
    {
        // A business table can never be a cleanup target, not even by a coding mistake.
        TargetRegistry::assertTableAllowed($this->table());
    }

    abstract protected function table(): string;

    /**
     * Cheap size facts for the page (no COUNT(*) on a million-row table): estimated rows and bytes from the
     * database's own statistics (MySQL), and the age column of the FIRST row by primary key — for append-only
     * logs that is the oldest record, and it is one indexed lookup.
     *
     * @return array{rows:int|null, bytes:int|null, oldest:string|null}
     */
    public function sizeInfo(): array
    {
        $rows = $bytes = null;

        try {
            if (DB::getDriverName() === 'mysql') {
                $row = DB::selectOne(
                    'SELECT TABLE_ROWS AS r, (DATA_LENGTH + INDEX_LENGTH) AS b FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$this->table()],
                );
                $rows = $row?->r !== null ? (int) $row->r : null;
                $bytes = $row?->b !== null ? (int) $row->b : null;
            } else {
                $rows = DB::table($this->table())->count();
            }
        } catch (Throwable $e) {
            Log::channel('cleanup')->warning("Could not read the size of {$this->table()}: ".$e->getMessage());
        }

        $oldest = null;
        try {
            $value = DB::table($this->table())->orderBy($this->primaryKey())->value($this->ageColumn());
            $oldest = $value !== null ? (string) $value : null;
        } catch (Throwable $e) {
            Log::channel('cleanup')->warning("Could not read the oldest row of {$this->table()}: ".$e->getMessage());
        }

        return ['rows' => $rows, 'bytes' => $bytes, 'oldest' => $oldest];
    }

    /** The timestamp column the retention applies to. */
    abstract protected function ageColumn(): string;

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** Extra predicate (e.g. only finished runs, only already-read notifications). */
    protected function scope(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Ids to keep whatever their age (e.g. the newest snapshot per check) — computed once when the item
     * starts and stored in the checkpoint, so it must stay small.
     *
     * @return list<int>
     */
    protected function keepIds(CleanupContext $ctx): array
    {
        return [];
    }

    public function kind(): string
    {
        return 'db';
    }

    public function group(): string
    {
        return 'logs';
    }

    public function reportOnly(): bool
    {
        return false;
    }

    public function truncatable(): bool
    {
        return false;
    }

    public function autoByDefault(): bool
    {
        return true;
    }

    public function needsBackupLock(): bool
    {
        return false;
    }

    public function totalRows(): ?int
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                $rows = DB::selectOne(
                    'SELECT TABLE_ROWS AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$this->table()],
                );

                return $rows?->n !== null ? (int) $rows->n : null;
            }

            return DB::table($this->table())->count();
        } catch (Throwable $e) {
            // Unknown size: the over-delete guard then simply cannot judge. Say so rather than hide it.
            Log::channel('cleanup')->warning("Could not estimate the size of {$this->table()}: ".$e->getMessage());

            return null;
        }
    }

    /** Average row size in bytes (MySQL only), used to estimate the space a delete makes reusable. */
    protected function averageRowBytes(): ?int
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                $row = DB::selectOne(
                    'SELECT AVG_ROW_LENGTH AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$this->table()],
                );

                return $row?->n ? (int) $row->n : null;
            }
        } catch (Throwable $e) {
            Log::channel('cleanup')->warning("Could not read the average row size of {$this->table()}: ".$e->getMessage());
        }

        return null;
    }

    public function begin(CleanupContext $ctx): array
    {
        if (! Schema::hasTable($this->table())) {
            throw new \RuntimeException("Table '{$this->table()}' does not exist.");
        }

        return [
            'max_id' => (int) DB::table($this->table())->max($this->primaryKey()),
            'keep' => array_slice($this->keepIds($ctx), 0, self::MAX_KEEP_IDS),
        ];
    }

    public function plan(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['plan'] ??= [];

        $done = (new WindowedCounter)->step($this->candidates($ctx, $cp), $this->primaryKey(), (int) $cp['max_id'], $cp['plan'], $deadline);

        if ($done) {
            $cp['targeted'] = (int) ($cp['plan']['count'] ?? 0);
        }

        return $done;
    }

    public function delete(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['del'] ??= [];

        $archiver = $ctx->archiver;
        $beforeDelete = $archiver === null ? null : function (array $ids) use ($archiver): int {
            return $archiver->append(DB::table($this->table())->whereIn($this->primaryKey(), $ids)->get());
        };

        $done = (new ChunkedDeleter)->step(
            $this->table(),
            $this->primaryKey(),
            $this->candidates($ctx, $cp),
            (int) $cp['max_id'],
            $cp['del'],
            $deadline,
            $ctx->maxChunk,
            $ctx->sleepMs,
            $beforeDelete,
            fn () => $ctx->cancelRequested(),
        );

        $cp['deleted'] = (int) ($cp['del']['deleted'] ?? 0);
        $cp['failed'] = (int) ($cp['del']['failed'] ?? 0);
        if ($archiver !== null) {
            $cp['archive_rows'] = (int) ($cp['del']['archive_rows'] ?? 0);
            $cp['archive_path'] = $archiver->path();
        }

        return $done;
    }

    public function verify(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['ver'] ??= [];
        $counter = new WindowedCounter;

        $done = $counter->step($this->candidates($ctx, $cp), $this->primaryKey(), (int) $cp['max_id'], $cp['ver'], $deadline);

        if ($done) {
            $cp['remaining'] = (int) ($cp['ver']['count'] ?? 0);
            $cp['late_rows'] = $counter->late($this->candidates($ctx, $cp), $this->primaryKey(), (int) $cp['max_id']);

            // Rows deleted from InnoDB make space reusable INSIDE the tablespace; the file only shrinks after
            // an OPTIMIZE. Report it as an estimate and let the UI say exactly that.
            $avg = $this->averageRowBytes();
            if ($avg !== null && ($cp['deleted'] ?? 0) > 0) {
                $cp['bytes_apparent'] = (int) $cp['deleted'] * $avg;
            }
        }

        return $done;
    }

    /**
     * @param  array<string, mixed>  $cp
     * @return \Closure():Builder
     */
    private function candidates(CleanupContext $ctx, array $cp): \Closure
    {
        $table = $this->table();
        $column = $this->ageColumn();
        $pk = $this->primaryKey();
        $keep = array_map('intval', (array) ($cp['keep'] ?? []));
        $cutoff = $ctx->cutoff;

        return function () use ($table, $column, $pk, $keep, $cutoff): Builder {
            $query = DB::table($table)->where($column, '<', $cutoff);

            if ($keep !== []) {
                $query->whereNotIn($pk, $keep);
            }

            return $this->scope($query);
        };
    }
}
