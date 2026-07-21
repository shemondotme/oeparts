<?php

namespace App\Services\Backup\Stages;

use App\Models\BackupPart;
use App\Models\BackupRun;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\StageStepResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DatabaseBackupStage (Module 14/21, Chunk 2.2) — the first concrete
 * {@see BackupStage}: a pure-PHP, shared-hosting-safe DB dump that replaces the
 * old `mysqldump`/`exec` command (rule #41).
 *
 * Resumable, time-budgeted per step (rule #48): each step() writes one or more
 * WHOLE part files — a table's schema, or a keyset page of its rows, never a
 * partial one — until backup.db.batch_seconds elapses, then hands back a
 * checkpoint so a killed/closed run resumes at the exact table + cursor. This
 * lets a site with many small tables clear several per poll instead of being
 * stuck at exactly one table-unit per 2s tick, while staying bounded by TIME
 * (never a fixed unit count) so a step still can't run unboundedly. Rows are
 * paged by the table's single-column primary key (keyset cursor: memory stays
 * flat on huge tables); tables without a usable PK fall back to a
 * deterministic OFFSET walk. Tables in config('backup.db.exclude_table_data')
 * are dumped structure-only (logs / sessions / cache / jobs).
 *
 * Portable output: identifiers are backtick-quoted (accepted by both MySQL and
 * SQLite) and values quoted via PDO, so a dump is restorable on the production
 * MySQL target. The CREATE TABLE DDL is the one driver-specific piece.
 */
class DatabaseBackupStage implements BackupStage
{
    public function key(): string
    {
        return BackupPart::TYPE_DB;
    }

    /**
     * Batches whole table-units (one full schema write, or one full keyset
     * data page — never a partial one) within a soft wall-clock budget
     * (backup.db.batch_seconds), so a site with many small tables clears
     * several per poll instead of exactly one per 2s tick. Bounded by TIME,
     * never by unit count, so a step still can't run unboundedly (rule #48).
     */
    public function step(BackupRun $run, array $state): StageStepResult
    {
        if (! isset($state['tables'])) {
            $state = $this->initialise();
        }

        $tables = $state['tables'];

        // No tables at all, or we've walked past the last one.
        if ((int) $state['ti'] >= count($tables)) {
            return StageStepResult::complete(null, 'Database backup complete.');
        }

        $deadline = microtime(true) + max(0.0, (float) config('backup.db.batch_seconds', 5));
        $parts    = [];
        $notes    = [];

        do {
            $index = (int) $state['ti'];
            $table = (string) $tables[$index];
            $part  = null;

            if ($state['phase'] === 'schema') {
                $part = $this->writeSchemaPart($run, $table);
                $notes[] = "schema: {$table}";

                if ($this->isDataExcluded($table)) {
                    $state = $this->advanceTable($state); // structure-only table
                } else {
                    $state['phase']  = 'data';
                    $state['cursor'] = null;
                    $state['chunk']  = 0;
                    $state['key']    = $this->keyColumn($table); // null → OFFSET fallback
                }
            } else { // data
                [$part, $advance, $cursor, $chunk, $rows] = $this->writeDataChunk($run, $table, $state);
                $notes[] = $rows === 0 ? "data: {$table} (done)" : "data: {$table} +{$rows} rows";

                if ($advance) {
                    $state = $this->advanceTable($state);
                } else {
                    $state['cursor'] = $cursor;
                    $state['chunk']  = $chunk;
                }
            }

            if ($part !== null) {
                $parts[] = $part;
            }
        } while ((int) $state['ti'] < count($tables) && microtime(true) < $deadline);

        $note = count($notes) > 1
            ? count($notes).' units ('.$notes[0].' … '.end($notes).')'
            : ($notes[0] ?? null);

        if ((int) $state['ti'] >= count($tables)) {
            // array_pop keeps the single-$part slot populated too, for any
            // caller still reading only ->part; the rest ride in ->parts.
            return new StageStepResult(done: true, state: [], part: array_pop($parts), message: $note, fraction: 1.0, parts: $parts);
        }

        $fraction = count($tables) > 0 ? ((int) $state['ti']) / count($tables) : 1.0;

        return StageStepResult::progress($state, array_pop($parts), $note, $fraction, $parts);
    }

    /** @return array{tables:array<int,string>,ti:int,phase:string,cursor:mixed,chunk:int} */
    private function initialise(): array
    {
        return [
            'tables' => $this->resolveTables(),
            'ti'     => 0,
            'phase'  => 'schema',
            'cursor' => null,
            'chunk'  => 0,
        ];
    }

    private function advanceTable(array $state): array
    {
        $state['ti']     = (int) $state['ti'] + 1;
        $state['phase']  = 'schema';
        $state['cursor'] = null;
        $state['chunk']  = 0;
        unset($state['key']);

        return $state;
    }

    /* ---- Schema -------------------------------------------------------- */

    private function writeSchemaPart(BackupRun $run, string $table): array
    {
        $sql  = $this->createTableSql($table);
        $path = $this->partPath($run, $table, 'schema');

        return $this->writePart($run, $path, $sql, [
            'type' => BackupPart::TYPE_DB,
            'name' => $table,
            'rows' => null,
            'meta' => ['kind' => 'schema'],
        ]);
    }

    /** Driver-specific CREATE TABLE (the only non-portable piece of a dump). */
    private function createTableSql(string $table): string
    {
        $driver = DB::connection()->getDriverName();
        $header = "DROP TABLE IF EXISTS `{$table}`;\n";

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $row    = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $create = (array) $row;

            return $header.((string) ($create['Create Table'] ?? $create['Create View'] ?? '')).";\n";
        }

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "select sql from sqlite_master where type = 'table' and name = ?",
                [$table]
            );

            return $header.((string) ($row->sql ?? '')).";\n";
        }

        return "-- schema introspection unsupported for driver [{$driver}]\n";
    }

    /* ---- Data (keyset / offset paging) --------------------------------- */

    /**
     * Write one page of rows. Returns:
     *  [partAttrs|null, advanceToNextTable, nextCursor, nextChunkNo, rowsWritten]
     */
    private function writeDataChunk(BackupRun $run, string $table, array $state): array
    {
        $limit = max(1, (int) config('backup.db.chunk_rows', 5000));
        $key   = $state['key'] ?? null;
        $chunk = (int) $state['chunk'];

        $query = DB::table($table);

        if ($key !== null) {
            if ($state['cursor'] !== null) {
                $query->where($key, '>', $state['cursor']);
            }
            $query->orderBy($key);
        } else {
            // No single-column PK — deterministic full-column ordering + offset.
            foreach ($this->columns($table) as $column) {
                $query->orderBy($column);
            }
            $query->offset((int) ($state['cursor'] ?? 0));
        }

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            return [null, true, null, $chunk, 0];
        }

        $columns = $this->columns($table);
        $sql     = $this->insertSql($table, $columns, $rows);
        $path    = $this->partPath($run, $table, 'data.'.$chunk);

        $part = $this->writePart($run, $path, $sql, [
            'type' => BackupPart::TYPE_DB,
            'name' => $table,
            'rows' => $rows->count(),
            'meta' => [
                'kind'       => 'data',
                'chunk'      => $chunk,
                'key'        => $key,
                'consistent' => (bool) (((array) ($run->meta ?? []))['consistent']
                    ?? config('backup.db.consistent', false)),
            ],
        ]);

        $last = $rows->last();
        $nextCursor = $key !== null
            ? ($last->{$key} ?? null)
            : (int) ($state['cursor'] ?? 0) + $rows->count();

        // Fewer rows than a full page ⇒ this table is exhausted.
        $advance = $rows->count() < $limit;

        return [$part, $advance, $nextCursor, $chunk + 1, $rows->count()];
    }

    /** Build a single multi-row INSERT for the page. */
    private function insertSql(string $table, array $columns, $rows): string
    {
        $colList = '`'.implode('`,`', $columns).'`';
        $tuples  = [];

        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $this->quote($row->{$column} ?? null);
            }
            $tuples[] = '('.implode(',', $values).')';
        }

        return "INSERT INTO `{$table}` ({$colList}) VALUES\n".implode(",\n", $tuples).";\n";
    }

    /** SQL-literal a scalar value (portable across MySQL + SQLite via PDO quoting). */
    private function quote($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return DB::getPdo()->quote((string) $value);
    }

    /* ---- Table / key introspection ------------------------------------- */

    /** @return array<int,string> base tables to back up, deterministic order. */
    private function resolveTables(): array
    {
        $tables = array_map(
            fn ($t) => Str::contains($t, '.') ? Str::afterLast($t, '.') : $t,
            Schema::getTableListing()
        );

        $tables = array_values(array_filter(
            $tables,
            fn ($t) => ! Str::startsWith($t, 'sqlite_') // skip engine-internal tables
        ));

        sort($tables);

        return $tables;
    }

    /** @return array<int,string> */
    private function columns(string $table): array
    {
        return Schema::getColumnListing($table);
    }

    /** The single-column primary key to keyset-page by, or null for OFFSET mode. */
    private function keyColumn(string $table): ?string
    {
        try {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['primary'] ?? false) && count($index['columns'] ?? []) === 1) {
                    return (string) $index['columns'][0];
                }
            }
        } catch (\Throwable $e) {
            // Introspection unsupported → OFFSET fallback.
        }

        return null;
    }

    private function isDataExcluded(string $table): bool
    {
        return in_array($table, (array) config('backup.db.exclude_table_data', []), true);
    }

    /* ---- Part I/O ------------------------------------------------------ */

    private function partPath(BackupRun $run, string $table, string $suffix): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_]+/', '_', $table);

        return 'backups/'.$run->getKey().'/db/'.$safe.'.'.$suffix.'.sql.gz';
    }

    /** Compress + persist one part file to LOCAL staging; the transport stage
     *  (2.4) encrypts + ships it afterwards. Returns the attrs the engine records. */
    private function writePart(BackupRun $run, string $path, string $sql, array $attrs): array
    {
        $compressed = (string) gzencode($sql, 6);
        $disk       = (string) config('backup.staging_disk', 'local');

        Storage::disk($disk)->put($path, $compressed);

        return array_merge($attrs, [
            'disk'   => $disk,
            'path'   => $path,
            'bytes'  => strlen($compressed),
            'sha256' => hash('sha256', $compressed),
        ]);
    }
}
