<?php

namespace App\Services\Migration\Export;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A tripwire around an export. Before the first row is copied and after the last one, it records for
 * each exported table: the row count (exact for modest tables; skipped for huge ones, where a count is
 * a full scan), the largest key and the most recent `updated_at`. Anything that changed in between is
 * "drift": in a live copy it is information ("12 orders arrived while the copy ran"), in a freeze it
 * means the freeze leaked (an admin edited through the panel, which maintenance mode lets through, or a
 * payment webhook slipped in) and the copy is reported as not consistent.
 */
final class FenceProbe
{
    /** Counting a table this big is a scan; the largest key and last update tell nearly as much. */
    public const COUNT_BELOW_ROWS = 200_000;

    public function __construct(private readonly KeysetPager $pager = new KeysetPager) {}

    /**
     * @param  array<int, string>  $tables
     * @param  array<string, array{rows: int, approximate: bool}>  $estimate  the plan's row estimates, to decide which tables are cheap to count
     * @return array<string, array{count: int|null, max_key: string|int|null, max_updated: string|null}>
     */
    public function snapshot(array $tables, array $estimate = []): array
    {
        $snapshot = [];

        foreach ($tables as $table) {
            $key = $this->pager->keyOf($table);
            $rows = (int) ($estimate[$table]['rows'] ?? 0);

            $snapshot[$table] = [
                'count' => $rows < self::COUNT_BELOW_ROWS ? (int) DB::table($table)->count() : null,
                'max_key' => count($key) === 1 ? $this->scalar(DB::table($table)->max($key[0])) : null,
                'max_updated' => Schema::hasColumn($table, 'updated_at') ? $this->scalar(DB::table($table)->max('updated_at')) : null,
            ];
        }

        return $snapshot;
    }

    /**
     * What changed between two snapshots, by table.
     *
     * @param  array<string, array<string, mixed>>  $before
     * @param  array<string, array<string, mixed>>  $after
     * @return array<string, array<string, array{0: mixed, 1: mixed}>>
     */
    public static function drift(array $before, array $after): array
    {
        $drift = [];

        foreach ($before as $table => $was) {
            $now = $after[$table] ?? null;

            if ($now === null) {
                continue;
            }

            foreach (['count', 'max_key', 'max_updated'] as $field) {
                if (($was[$field] ?? null) !== ($now[$field] ?? null)) {
                    $drift[$table][$field] = [$was[$field] ?? null, $now[$field] ?? null];
                }
            }
        }

        return $drift;
    }

    private function scalar(mixed $value): string|int|null
    {
        return $value === null ? null : (is_int($value) ? $value : (string) $value);
    }
}
