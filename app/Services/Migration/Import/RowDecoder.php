<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Export\RowEncoder;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * Reads the rows the exporter wrote (see RowEncoder) back into the values a database insert wants.
 *
 * A segment is text: a header line that names the table and its columns, then one JSON array per row. A cell is null,
 * a number, a string, or {"$b": "<base64>"} for bytes (a binary column, or text that was not valid UTF-8): those come
 * back as the exact original bytes. Nothing is guessed, "fixed" or dropped. A line that is not a row, or has the wrong
 * number of cells, stops the load: the package is damaged or was not made by this application.
 */
final class RowDecoder
{
    /** @var array<int, string> */
    private array $columns;

    private function __construct(public readonly string $table, array $columns)
    {
        $this->columns = array_values(array_map('strval', $columns));
    }

    /**
     * @throws MigrationException package_corrupt when the line is not a row-format header
     */
    public static function fromHeader(string $line): self
    {
        $header = json_decode($line, true);

        if (! is_array($header) || ($header['$'] ?? null) !== RowEncoder::FORMAT || ! is_string($header['table'] ?? null)
            || ! is_array($header['columns'] ?? null) || $header['columns'] === []) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'row header']);
        }

        foreach ($header['columns'] as $column) {
            if (! is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'column name']);
            }
        }

        return new self($header['table'], $header['columns']);
    }

    /** @return array<int, string> */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * One row line as the values to insert, in column order.
     *
     * @return array<int, mixed>
     *
     * @throws MigrationException package_corrupt
     */
    public function decode(string $line): array
    {
        $cells = json_decode($line, true);

        if (! is_array($cells) || ! array_is_list($cells) || count($cells) !== count($this->columns)) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'row of '.$this->table]);
        }

        foreach ($cells as $position => $cell) {
            if (is_array($cell)) {
                $bytes = isset($cell['$b']) && is_string($cell['$b']) && count($cell) === 1 ? base64_decode($cell['$b'], true) : false;

                if ($bytes === false) {
                    throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'cell of '.$this->table]);
                }

                $cells[$position] = $bytes;
            }
        }

        return $cells;
    }
}
