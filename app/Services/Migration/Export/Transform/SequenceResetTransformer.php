<?php

namespace App\Services\Migration\Export\Transform;

/**
 * For a new store that reuses the catalog: order, invoice and credit-note numbering starts again from
 * the beginning instead of continuing the old shop's numbers.
 */
final class SequenceResetTransformer implements RowTransformer
{
    public function tables(): array
    {
        return ['sequences'];
    }

    public function transform(string $table, array $row): array
    {
        $row['current_value'] = 0;
        $row['last_reset_month'] = null;

        return $row;
    }
}
