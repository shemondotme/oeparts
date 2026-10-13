<?php

namespace App\Services\Migration\Export;

/**
 * One foreign key: rows of `$child` point at rows of `$parent`.
 *
 * It is STRONG when every child column is NOT NULL: a child row cannot exist without its parent row,
 * so the parent's data must travel with the child. It is WEAK when at least one child column may be
 * NULL: the reference can be cut (set to NULL in the copy) without breaking the child row.
 */
final class FkEdge
{
    /**
     * @param  array<int, string>  $childColumns
     * @param  array<int, string>  $parentColumns
     */
    public function __construct(
        public readonly string $child,
        public readonly array $childColumns,
        public readonly string $parent,
        public readonly array $parentColumns,
        public readonly bool $strong,
    ) {}

    public function isSelfReference(): bool
    {
        return $this->child === $this->parent;
    }

    /** A canonical one-line form, the same on SQLite and MySQL, for fixtures and messages. */
    public function signature(): string
    {
        return $this->child.'.'.implode('+', $this->childColumns).'->'.$this->parent.'.'.implode('+', $this->parentColumns).':'.($this->strong ? 'strong' : 'weak');
    }

    /** The sentence shown to a person when this edge pulls a data group in. */
    public function describe(): string
    {
        $from = $this->child.'.'.implode(', ', $this->childColumns);
        $to = $this->parent.'.'.implode(', ', $this->parentColumns);

        return "{$from} -> {$to} is ".($this->strong ? 'NOT NULL' : 'optional');
    }
}
