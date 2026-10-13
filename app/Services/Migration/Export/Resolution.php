<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\Groups\GroupKey;

/**
 * The outcome of resolving a selection of groups against the real foreign keys.
 */
final class Resolution
{
    /**
     * @param  array<int, GroupKey>  $requested  what the person asked for (core included)
     * @param  array<int, GroupKey>  $groups  what will be exported: requested plus everything a strong reference pulled in
     * @param  array<string, array<int, Reason>>  $added  group value => why it was added
     * @param  array<int, FkEdge>  $nullified  optional references to data that is not travelling; the column is set to NULL in the copy
     * @param  array<int, string>  $tables  every table exported, children first
     * @param  array<int, FkEdge>  $brokenCycles  optional references ignored when ordering tables because they form a cycle
     */
    public function __construct(
        public readonly array $requested,
        public readonly array $groups,
        public readonly array $added,
        public readonly array $nullified,
        public readonly array $tables,
        public readonly array $brokenCycles = [],
    ) {}

    /** @return array<int, string> the order a target loads them in: parents before the rows that point at them */
    public function importOrder(): array
    {
        return array_reverse($this->tables);
    }

    public function includes(GroupKey $key): bool
    {
        return in_array($key, $this->groups, true);
    }

    public function wasAdded(GroupKey $key): bool
    {
        return isset($this->added[$key->value]);
    }

    /**
     * Columns to set to NULL while exporting, by table.
     *
     * @return array<string, array<int, string>>
     */
    public function nullifiedColumns(): array
    {
        $byTable = [];

        foreach ($this->nullified as $edge) {
            foreach ($edge->childColumns as $column) {
                $byTable[$edge->child][] = $column;
            }
        }

        foreach ($byTable as $table => $columns) {
            $byTable[$table] = array_values(array_unique($columns));
        }

        ksort($byTable);

        return $byTable;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'requested' => array_map(static fn (GroupKey $k): string => $k->value, $this->requested),
            'groups' => array_map(static fn (GroupKey $k): string => $k->value, $this->groups),
            'added' => array_map(static fn (array $reasons): array => array_map(static fn (Reason $r): array => $r->toArray(), $reasons), $this->added),
            'nullified' => array_map(static fn (FkEdge $e): string => $e->signature(), $this->nullified),
            'tables' => $this->tables,
        ];
    }
}
