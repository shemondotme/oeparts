<?php

namespace App\Services\Migration\Groups;

/**
 * One data group: its tables, the folders of files it owns, and the groups it cannot be moved without.
 */
final class Group
{
    /**
     * @param  array<int, string>  $tables  every table the group owns in full
     * @param  array<string, string>  $slices  table => slice name, for the one table that several groups share
     *                                         (`seo_meta` is split by what the row describes: a product, or anything else)
     * @param  array<int, FileRoot>  $files
     * @param  array<int, GroupKey>  $requires  groups that must come along (a file group needs the group that points at its files)
     */
    public function __construct(
        public readonly GroupKey $key,
        public readonly bool $defaultOn,
        public readonly array $tables,
        public readonly array $slices = [],
        public readonly array $files = [],
        public readonly array $requires = [],
    ) {}

    public function isMandatory(): bool
    {
        return $this->key === GroupKey::Core;
    }

    /** The system group belongs to the server, not the shop: it is never chosen and never exported as rows. */
    public function isSelectable(): bool
    {
        return $this->key !== GroupKey::System;
    }

    /** @return array<int, string> every table this group touches, shared slices included */
    public function allTables(): array
    {
        return array_values(array_unique([...$this->tables, ...array_keys($this->slices)]));
    }

    public function owns(string $table): bool
    {
        return in_array($table, $this->tables, true) || isset($this->slices[$table]);
    }
}
