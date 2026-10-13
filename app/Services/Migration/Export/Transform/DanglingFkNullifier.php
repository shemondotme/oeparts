<?php

namespace App\Services\Migration\Export\Transform;

/**
 * Cuts the optional references to data that is not travelling: the column is exported as NULL, so the
 * copy never holds a key that points at nothing. (A NOT NULL reference never gets here: it pulls the
 * parent's group in instead.)
 */
final class DanglingFkNullifier implements RowTransformer
{
    /** @param  array<string, array<int, string>>  $columnsByTable  table => columns to set to NULL */
    public function __construct(private readonly array $columnsByTable) {}

    public function tables(): array
    {
        return array_keys($this->columnsByTable);
    }

    public function transform(string $table, array $row): array
    {
        foreach ($this->columnsByTable[$table] ?? [] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = null;
            }
        }

        return $row;
    }
}
