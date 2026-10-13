<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * An order-independent checksum of one table, computed INSIDE the database (MySQL / MariaDB).
 *
 * Why SQL-side: a migration test that reads rows into PHP, hashes them with the same PHP code the
 * exporter used, and compares, proves only that the code agrees with itself. This runs the same
 * statement on the source and the target server over the live tables, with its own idea of the
 * columns (information_schema, not the application's models), so a column the exporter forgot, a
 * cell it encoded lossily or a row it dropped changes the number.
 *
 *  - every non-generated column, in ordinal order, each cell tagged so NULL differs from '';
 *  - binary and BLOB columns are HEX()ed, everything else CAST to text (JSON included);
 *  - the session is put in UTC first so TIMESTAMP columns render the same on both sides;
 *  - rows are combined with a SUM of a 60-bit slice of each row's MD5, so row order does not matter.
 *
 * Within one server product and major version two equal tables give equal results. Comparing a MySQL
 * table with a MariaDB table is not promised (their text renderings of floats and JSON differ).
 */
final class TableChecksum
{
    /**
     * @return array{rows: int, checksum: string, columns: array<int, string>}
     */
    public static function of(string $table, ?string $connection = null): array
    {
        $db = DB::connection($connection);
        $db->statement("SET time_zone = '+00:00'");

        $columns = self::columns($table, $connection);

        if ($columns === []) {
            throw new \InvalidArgumentException("Table {$table} does not exist or has no columns.");
        }

        $cells = array_map(static function (array $column): string {
            $name = '`'.str_replace('`', '``', $column['name']).'`';
            $text = $column['binary'] ? "HEX({$name})" : "CAST({$name} AS CHAR)";

            return "IFNULL(CONCAT('v:', {$text}), 'n:')";
        }, $columns);

        $row = 'CONCAT_WS(CHAR(31), '.implode(', ', $cells).')';
        $quoted = '`'.str_replace('`', '``', $table).'`';

        $result = $db->selectOne(
            "SELECT COUNT(*) AS n, COALESCE(SUM(CAST(CONV(SUBSTRING(MD5({$row}), 1, 15), 16, 10) AS DECIMAL(40, 0))), 0) AS s FROM {$quoted}"
        );

        return [
            'rows' => (int) $result->n,
            'checksum' => md5($result->n.':'.$result->s),
            'columns' => array_column($columns, 'name'),
        ];
    }

    /**
     * A second opinion from the server's own CHECKSUM TABLE. Not order-independent in meaning for every
     * engine and not comparable across products, so it supports the SQL-side checksum, never replaces it.
     */
    public static function native(string $table, ?string $connection = null): int
    {
        $quoted = '`'.str_replace('`', '``', $table).'`';
        $row = (array) DB::connection($connection)->selectOne("CHECKSUM TABLE {$quoted}");

        return (int) ($row['Checksum'] ?? 0);
    }

    /**
     * Checksums of several tables, keyed by table name.
     *
     * @param  array<int, string>  $tables
     * @return array<string, array{rows: int, checksum: string, columns: array<int, string>}>
     */
    public static function many(array $tables, ?string $connection = null): array
    {
        $out = [];

        foreach ($tables as $table) {
            $out[$table] = self::of($table, $connection);
        }

        return $out;
    }

    /** @return array<int, array{name: string, binary: bool}> */
    private static function columns(string $table, ?string $connection): array
    {
        $rows = DB::connection($connection)->select(
            'SELECT COLUMN_NAME AS name, DATA_TYPE AS type, EXTRA AS extra FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table]
        );

        $columns = [];

        foreach ($rows as $row) {
            // A generated column is computed from the others: it adds nothing and may not copy over.
            if (stripos((string) $row->extra, 'GENERATED') !== false) {
                continue;
            }

            $columns[] = [
                'name' => (string) $row->name,
                'binary' => in_array(strtolower((string) $row->type), ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob', 'bit'], true),
            ];
        }

        return $columns;
    }
}
