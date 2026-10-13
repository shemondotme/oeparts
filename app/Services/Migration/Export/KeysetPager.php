<?php

namespace App\Services\Migration\Export;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads a table in pages ordered by its key, each page starting strictly after the last key of the
 * previous one. There is no OFFSET anywhere: OFFSET makes the database walk past every earlier row on
 * every page, so a million-row table gets slower with each page; a key comparison goes straight to the
 * place in the index.
 *
 * Composite keys use the expanded lexicographic form,
 *     (a > ?) OR (a = ? AND b > ?) OR (a = ? AND b = ? AND c > ?)
 * which the database can satisfy from the primary-key index (a row-value comparison `(a,b,c) > (?,?,?)` is
 * not used because MySQL did not optimise it well for a long time).
 *
 * A page is capped by rows AND by bytes (a table of wide rows must not build a 200 MB page), but always
 * holds at least one row so a single enormous row can still go through.
 */
final class KeysetPager
{
    public function __construct(private readonly ?string $connection = null) {}

    /**
     * The columns to page by: the primary key, else the first unique index whose columns are all NOT NULL.
     *
     * @return array<int, string>
     *
     * @throws \RuntimeException when the table has neither (it cannot be paged without OFFSET)
     */
    public function keyOf(string $table): array
    {
        $schema = Schema::connection($this->connection);
        $nullable = [];
        foreach ($schema->getColumns($table) as $column) {
            $nullable[$column['name']] = (bool) $column['nullable'];
        }

        $unique = null;

        foreach ($schema->getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }

            if ($unique === null && $index['unique'] && array_filter($index['columns'], static fn (string $c): bool => ($nullable[$c] ?? true)) === []) {
                $unique = $index['columns'];
            }
        }

        return $unique ?? throw new \RuntimeException("Table {$table} has no primary key and no NOT NULL unique index: it cannot be read page by page.");
    }

    /**
     * @param  array<int, string>  $key  ordered key columns (see keyOf)
     * @param  array<int, string>  $columns  columns to read; ['*'] for all
     * @param  array<string, mixed>|null  $after  the `last` of the previous page, or null for the first page
     * @param  array<string, mixed>|null  $upTo  an inclusive upper bound on the FIRST key column (the high-water mark of a table
     *                                           with a single integer key, so rows inserted after the export began are not chased)
     * @return array{rows: array<int, array<string, mixed>>, last: array<string, mixed>|null}
     */
    public function page(string $table, array $key, array $columns, ?array $after, int $maxRows, int $maxBytes, ?array $upTo = null): array
    {
        if ($key === []) {
            throw new \InvalidArgumentException('A page needs at least one key column.');
        }

        if ($columns !== ['*'] && array_diff($key, $columns) !== []) {
            throw new \InvalidArgumentException('The columns read must include every key column, or there is nothing to continue after.');
        }

        $query = DB::connection($this->connection)->table($table)->select($columns);

        if ($after !== null) {
            $query->where(function ($outer) use ($key, $after): void {
                foreach ($key as $position => $column) {
                    $outer->orWhere(function ($branch) use ($key, $after, $position, $column): void {
                        for ($earlier = 0; $earlier < $position; $earlier++) {
                            $branch->where($key[$earlier], '=', $after[$key[$earlier]]);
                        }

                        $branch->where($column, '>', $after[$column]);
                    });
                }
            });
        }

        if ($upTo !== null) {
            $query->where($key[0], '<=', $upTo[$key[0]]);
        }

        foreach ($key as $column) {
            $query->orderBy($column);
        }

        $rows = [];
        $bytes = 0;

        foreach ($query->limit(max(1, $maxRows))->get() as $row) {
            $row = (array) $row;
            $rows[] = $row;
            $bytes += $this->sizeOf($row);

            if ($bytes >= $maxBytes) {
                break;
            }
        }

        if ($rows === []) {
            return ['rows' => [], 'last' => null];
        }

        $lastRow = $rows[count($rows) - 1];

        return ['rows' => $rows, 'last' => array_intersect_key($lastRow, array_flip($key))];
    }

    /** @param  array<string, mixed>  $row */
    private function sizeOf(array $row): int
    {
        $size = 0;

        foreach ($row as $value) {
            $size += is_string($value) ? strlen($value) : 8;
        }

        return $size + count($row);
    }
}
