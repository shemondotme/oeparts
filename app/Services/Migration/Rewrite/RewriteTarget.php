<?php

namespace App\Services\Migration\Rewrite;

/**
 * One table whose columns are rewritten, and how.
 *
 * `tier` is how the work is done: `small` tables are rewritten in one budgeted pass each, `bulk` tables (products,
 * a million rows in production) in a time-budgeted keyset walk that resumes where it stopped.
 */
final class RewriteTarget
{
    public const SMALL = 'small';

    public const BULK = 'bulk';

    /**
     * @param  array<string, RewriteKind>  $columns  column => kind (only kinds that are rewritten)
     * @param  array<string, scalar>  $where  equality conditions a row must meet to be touched (a draft, not a sent campaign)
     */
    public function __construct(
        public readonly string $table,
        public readonly array $columns,
        public readonly string $tier = self::SMALL,
        public readonly array $where = [],
        public readonly string $primaryKey = 'id',
    ) {
        foreach ($columns as $column => $kind) {
            if (! $kind->isRewritten()) {
                throw new \InvalidArgumentException("{$table}.{$column} is registered as a target with the kind {$kind->value}, which is never rewritten.");
            }
        }
    }

    /** @return array<int, string> */
    public function columnNames(): array
    {
        return array_keys($this->columns);
    }
}
