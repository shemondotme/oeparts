<?php

namespace App\Services\Migration\Export;

use Illuminate\Support\Facades\Schema;

/**
 * Turns database rows into the cells of the package's row format, losslessly.
 *
 * A table's rows are written as newline-delimited JSON ARRAYS in the order of the header's column list
 * (an array per row is far smaller than an object per row, and a million products is a lot of rows):
 *
 *     {"$":"oe-rows/1","table":"products","slice":null,"columns":["id","name"],"types":["int","json"],"pk":["id"],"est_rows":1000000}
 *     [1,"{\\"en\\":\\"Brake pad\\"}"]
 *
 * Cell rules: NULL stays null; integers stay integers; DECIMAL is a string (a float would round it);
 * FLOAT/DOUBLE stay numbers (PHP's shortest round-trip form); JSON columns are the JSON text as a string;
 * dates and times are strings (the connection is in UTC, so a TIMESTAMP reads the same on both sides).
 * Binary columns, and any text that is not valid UTF-8, are written as {"$b":"<base64>"}: bytes are never
 * dropped, never "fixed" and never guessed at. Generated columns are skipped: the database recomputes them.
 */
final class RowEncoder
{
    public const FORMAT = 'oe-rows/1';

    /** @var array<int, string> */
    private array $names = [];

    /** @var array<int, string> */
    private array $tags = [];

    /** @param  array<int, array{name: string, tag: string}>  $columns */
    public function __construct(private readonly array $columns)
    {
        foreach ($columns as $column) {
            $this->names[] = $column['name'];
            $this->tags[] = $column['tag'];
        }
    }

    public static function forTable(string $table, ?string $connection = null): self
    {
        $columns = [];

        foreach (Schema::connection($connection)->getColumns($table) as $column) {
            if (($column['generation'] ?? null) !== null) {
                continue;
            }

            $columns[] = ['name' => $column['name'], 'tag' => self::tagOf((string) $column['type_name'])];
        }

        if ($columns === []) {
            throw new \InvalidArgumentException("Table {$table} has no columns to export.");
        }

        return new self($columns);
    }

    /** @return array<int, string> */
    public function columns(): array
    {
        return $this->names;
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return $this->tags;
    }

    /**
     * @param  array<int, string>  $primaryKey
     */
    public function header(string $table, ?string $slice, array $primaryKey, int $estimatedRows): string
    {
        return $this->json([
            '$' => self::FORMAT, 'table' => $table, 'slice' => $slice, 'columns' => $this->names, 'types' => $this->tags,
            'pk' => $primaryKey, 'est_rows' => $estimatedRows,
        ]);
    }

    /**
     * One row as a line of JSON (without the newline).
     *
     * @param  array<string, mixed>  $row
     */
    public function line(array $row): string
    {
        $cells = [];

        foreach ($this->names as $position => $name) {
            $cells[] = $this->cell($this->tags[$position], $row[$name] ?? null);
        }

        return $this->json($cells);
    }

    public function cell(string $tag, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return match ($tag) {
            'int' => $this->integer($value),
            'float' => is_numeric($value) ? (float) $value : (string) $value,
            'binary' => ['$b' => base64_encode((string) $value)],
            default => $this->text((string) $value),
        };
    }

    /** @return int|string an integer when it fits one, else its digits (an unsigned BIGINT above PHP_INT_MAX) */
    private function integer(mixed $value): int|string
    {
        if (is_int($value)) {
            return $value;
        }

        $text = (string) $value;

        if (preg_match('/^-?\d+$/', $text) === 1 && (string) (int) $text === $text) {
            return (int) $text;
        }

        return $text;
    }

    /** @return string|array{'$b': string} */
    private function text(string $value): string|array
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : ['$b' => base64_encode($value)];
    }

    /** @param  array<mixed>  $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function tagOf(string $typeName): string
    {
        $name = strtolower($typeName);

        return match (true) {
            in_array($name, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'boolean', 'bool', 'year'], true) => 'int',
            in_array($name, ['decimal', 'numeric', 'dec', 'fixed'], true) => 'decimal',
            in_array($name, ['float', 'double', 'real', 'double precision'], true) => 'float',
            $name === 'json' => 'json',
            in_array($name, ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob', 'bit'], true) => 'binary',
            in_array($name, ['date', 'time', 'datetime', 'timestamp'], true) => 'datetime',
            default => 'string',
        };
    }
}
