<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts decoded rows into a table, the one way every part of the import does it (loading the package, and putting a
 * recovery point back): bound-parameter multi-row INSERTs in batches of at most 500 rows or about a megabyte, in the
 * order the rows arrive, into a table whose columns are exactly the ones the rows carry.
 *
 * Nothing is validated row by row beyond the column count (the package was proven intact before anything started):
 * the database's own constraints are off for the load (the whole set is consistent or the audit says so afterwards),
 * and strict mode stays on so a value that does not fit is an error, never silently cut.
 */
class RowLoader
{
    public const MAX_ROWS = 500;

    public const MAX_BYTES = 1024 * 1024;

    public function __construct(private readonly ?string $connection = null) {}

    /**
     * Put the database session in the state a bulk load needs. A new request is a new connection, so this is called on
     * EVERY advance of a loading stage, not once.
     */
    public function prepareSession(): void
    {
        $db = $this->db();

        match ($db->getDriverName()) {
            'mysql', 'mariadb' => $this->prepareMysql($db),
            'sqlite' => $this->prepareSqlite($db),
            default => null,
        };
    }

    /**
     * @throws MigrationException package_corrupt when the rows' columns are not the table's columns
     */
    public function assertColumns(RowDecoder $decoder): void
    {
        $actual = [];

        foreach (Schema::connection($this->connection)->getColumns($decoder->table) as $column) {
            if (($column['generation'] ?? null) === null) {
                $actual[] = (string) $column['name'];
            }
        }

        if ($actual !== $decoder->columns()) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'columns of '.$decoder->table]);
        }
    }

    /**
     * Cut a segment's row lines into the batches that are inserted: at most MAX_ROWS rows or about MAX_BYTES bytes each.
     * The cut is a pure function of the lines, so a run that resumes cuts the same batches the first one did, which is
     * what lets a resumed load tell "this batch was already inserted" from "this batch never ran".
     *
     * @param  iterable<int, string>  $lines  the row lines of one segment, header NOT included
     * @return \Generator<int, array<int, array<string, mixed>>> each batch as rows keyed by column
     */
    public function batches(RowDecoder $decoder, iterable $lines): \Generator
    {
        $batch = [];
        $bytes = 0;
        $columns = $decoder->columns();

        foreach ($lines as $line) {
            $batch[] = array_combine($columns, $decoder->decode($line));
            $bytes += strlen($line);

            if (count($batch) >= self::MAX_ROWS || $bytes >= self::MAX_BYTES) {
                yield $batch;
                $batch = [];
                $bytes = 0;
            }
        }

        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * @param  iterable<int, string>  $lines  the row lines of one segment, header NOT included
     * @param  (callable(int): void)|null  $afterBatch  told how many rows were inserted so far in this call, after each batch commits
     * @return int rows inserted
     */
    public function load(RowDecoder $decoder, iterable $lines, ?callable $afterBatch = null): int
    {
        $total = 0;

        foreach ($this->batches($decoder, $lines) as $batch) {
            $this->insert($decoder->table, $batch);
            $total += count($batch);

            if ($afterBatch !== null) {
                $afterBatch($total);
            }
        }

        return $total;
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    public function insert(string $table, array $rows): void
    {
        $this->db()->table($table)->insert($rows);
    }

    /** Empty a table without a row-by-row delete, with the session's constraint checks off. */
    public function truncate(string $table): void
    {
        $db = $this->db();

        if (in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            $db->statement('SET FOREIGN_KEY_CHECKS=0');
            $db->table($table)->truncate();

            return;
        }

        $db->table($table)->delete();

        if ($db->getDriverName() === 'sqlite') {
            // Start the next id from 1 again, as TRUNCATE does on MySQL.
            try {
                $db->statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
            } catch (\Throwable) {
                // No table has an auto-increment column yet, so there is no sequence table to reset.
            }
        }
    }

    private function prepareMysql(Connection $db): void
    {
        $db->statement('SET NAMES utf8mb4');
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        $db->statement('SET UNIQUE_CHECKS=0');
        $db->statement("SET time_zone='+00:00'");
        $db->statement("SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,NO_AUTO_VALUE_ON_ZERO'");
    }

    private function prepareSqlite(Connection $db): void
    {
        // foreign_keys cannot change inside a transaction (only the tests run in one); the deferral works in both.
        $db->statement('PRAGMA foreign_keys = OFF');
        $db->statement('PRAGMA defer_foreign_keys = ON');
    }

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }
}
