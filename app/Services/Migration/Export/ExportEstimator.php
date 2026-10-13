<?php

namespace App\Services\Migration\Export;

use Illuminate\Support\Facades\DB;

/**
 * Sizes and durations for an export before it starts, so a person is told "about 40 to 120 minutes,
 * 25 GB" before the shop is frozen, not after.
 *
 * Rows come from ONE query (MySQL's information_schema, an estimate, flagged approximate) or exact counts
 * (SQLite). Files come from FileCounter. Durations come from throughput constants in
 * config('migration.export.throughput'), which are starting values until rehearsal Gate A measures the
 * real ones; they are always reported as a range, never a single promise.
 */
final class ExportEstimator
{
    /** @var \Closure(array<int, string>): array<string, array{rows: int, approximate: bool, bytes: int|null}>|null */
    private readonly ?\Closure $rowCounts;

    /**
     * @param  \Closure(array<int, string>): array<string, array{rows: int, approximate: bool, bytes: int|null}>|null  $rowCounts  only tests pass this
     */
    public function __construct(?\Closure $rowCounts = null)
    {
        $this->rowCounts = $rowCounts;
    }

    /**
     * @param  array{count: int, bytes: int, complete: bool}|null  $files  from FileCounter; null when no count has been made
     */
    public function estimate(Resolution $resolution, ?array $files = null): ExportEstimate
    {
        $config = (array) config('migration.export.throughput', []);
        $counts = $this->rowCounts !== null ? ($this->rowCounts)($resolution->tables) : $this->countRows($resolution->tables);

        $tables = [];
        $totalRows = 0;
        $bytes = 0;
        $approximate = false;
        $averageRow = (int) ($config['avg_row_bytes'] ?? 400);

        foreach ($resolution->tables as $table) {
            $entry = $counts[$table] ?? ['rows' => 0, 'approximate' => false, 'bytes' => null];
            $tables[$table] = ['rows' => (int) $entry['rows'], 'approximate' => (bool) $entry['approximate']];
            $totalRows += (int) $entry['rows'];
            $bytes += (int) ($entry['bytes'] ?? ((int) $entry['rows'] * $averageRow));
            $approximate = $approximate || (bool) $entry['approximate'];
        }

        $files ??= ['count' => 0, 'bytes' => 0, 'complete' => false];
        $dbCompressed = (int) ceil($bytes * (float) ($config['db_compress_ratio'] ?? 0.2));
        $fileCompressed = (int) ceil($files['bytes'] * (float) ($config['file_compress_ratio'] ?? 0.97));

        $seconds = $totalRows / max(1, (int) ($config['db_rows_per_second'] ?? 2500))
            + $files['bytes'] / max(1, (int) ($config['file_bytes_per_second'] ?? 25 * 1024 * 1024))
            + $files['count'] / max(1, (int) ($config['files_per_second'] ?? 120));

        $low = max(1, (int) ceil($seconds * (float) ($config['estimate_low_factor'] ?? 0.6) / 60));
        $high = max($low, (int) ceil($seconds * (float) ($config['estimate_high_factor'] ?? 1.8) / 60));

        $export = (array) config('migration.export', []);
        $required = (int) ceil((float) ($export['free_space_factor'] ?? 1.15) * ($dbCompressed + $fileCompressed))
            + (int) ($export['volume_bytes'] ?? 512 * 1024 * 1024)
            + (int) ($export['min_free_bytes'] ?? 1024 * 1024 * 1024);

        return new ExportEstimate($tables, $totalRows, $approximate, $bytes, $dbCompressed, $files, $fileCompressed, $low, $high, $required);
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, array{rows: int, approximate: bool, bytes: int|null}>
     */
    private function countRows(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        $connection = DB::connection();

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $marks = implode(',', array_fill(0, count($tables), '?'));
            $rows = $connection->select(
                "SELECT TABLE_NAME AS name, TABLE_ROWS AS n, DATA_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$marks})",
                $tables
            );

            $counts = [];
            foreach ($rows as $row) {
                $counts[(string) $row->name] = ['rows' => (int) $row->n, 'approximate' => true, 'bytes' => (int) $row->bytes];
            }

            return $counts;
        }

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = ['rows' => (int) $connection->table($table)->count(), 'approximate' => false, 'bytes' => null];
        }

        return $counts;
    }
}
