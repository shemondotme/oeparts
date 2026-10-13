<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;

/**
 * Turns "these groups" into "these groups, plus what they cannot do without", using the foreign keys
 * of the real database.
 *
 *  - A STRONG reference (every referencing column NOT NULL) pulls the parent's group in, and the
 *    person is told why ("cart_items.product_id -> products.id is NOT NULL").
 *  - A WEAK reference (a column that may be NULL) does not. The reference is cut in the copy: the
 *    column is exported as NULL, and the people are shown how many rows that touches.
 *  - A file group (product pictures) requires the group whose rows point at its files.
 *
 * Tables come out in an order a target can load: parents before the rows that point at them. An export
 * reads them the other way round (children first), so a parent row created while the export runs is
 * simply not yet referenced by anything already read.
 */
final class DependencyResolver
{
    public function __construct(
        private readonly GroupRegistry $registry,
        private readonly FkGraph $graph,
    ) {}

    /**
     * @param  array<int, GroupKey>  $selection
     */
    public function resolve(array $selection): Resolution
    {
        $requested = $this->normalise($selection);
        $selected = array_fill_keys(array_map(static fn (GroupKey $k): string => $k->value, $requested), true);
        $added = [];

        do {
            $changed = $this->addRequiredFileOwners($selected, $added);
            $changed = $this->addStrongParents($selected, $added) || $changed;
        } while ($changed);

        $groups = array_values(array_filter(
            array_keys($this->registry->all()),
            static fn (string $k): bool => isset($selected[$k])
        ));
        $groups = array_map(static fn (string $k): GroupKey => GroupKey::from($k), $groups);

        $tables = $this->registry->tablesOf($groups);
        [$order, $broken] = $this->order($tables);

        return new Resolution($requested, $groups, $added, $this->weakEdgesToCut($tables), $order, $broken);
    }

    /**
     * @param  array<int, GroupKey>  $selection
     * @return array<int, GroupKey>
     */
    private function normalise(array $selection): array
    {
        foreach ($selection as $key) {
            if ($key === GroupKey::System) {
                throw new \InvalidArgumentException('System data cannot be selected: it belongs to the server, not to the shop.');
            }
        }

        $wanted = array_unique([GroupKey::Core->value, ...array_map(static fn (GroupKey $k): string => $k->value, $selection)]);

        return array_values(array_map(
            static fn (string $k): GroupKey => GroupKey::from($k),
            array_filter(array_keys($this->registry->all()), static fn (string $k): bool => in_array($k, $wanted, true))
        ));
    }

    /**
     * @param  array<string, true>  $selected
     * @param  array<string, array<int, Reason>>  $added
     */
    private function addRequiredFileOwners(array &$selected, array &$added): bool
    {
        $changed = false;

        foreach ($this->registry->all() as $group) {
            if (! isset($selected[$group->key->value])) {
                continue;
            }

            foreach ($group->requires as $needed) {
                if (! isset($selected[$needed->value])) {
                    $selected[$needed->value] = true;
                    $added[$needed->value][] = Reason::requires($needed, $group->key);
                    $changed = true;
                }
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, true>  $selected
     * @param  array<string, array<int, Reason>>  $added
     */
    private function addStrongParents(array &$selected, array &$added): bool
    {
        $changed = false;

        foreach ($this->registry->all() as $group) {
            if (! isset($selected[$group->key->value])) {
                continue;
            }

            foreach ($group->allTables() as $table) {
                foreach ($this->graph->edgesFrom($table) as $edge) {
                    if (! $edge->strong) {
                        continue;
                    }

                    $parent = $this->groupOfParent($edge);

                    if (! isset($selected[$parent->value])) {
                        $selected[$parent->value] = true;
                        $added[$parent->value][] = Reason::foreignKey($parent, $group->key, $edge);
                        $changed = true;
                    }
                }
            }
        }

        return $changed;
    }

    /**
     * Optional references from exported tables to tables that are not exported.
     *
     * @param  array<int, string>  $tables
     * @return array<int, FkEdge>
     */
    private function weakEdgesToCut(array $tables): array
    {
        $exported = array_flip($tables);
        $cut = [];

        foreach ($tables as $table) {
            foreach ($this->graph->edgesFrom($table) as $edge) {
                if (! $edge->strong && ! isset($exported[$edge->parent])) {
                    $cut[] = $edge;
                }
            }
        }

        return $cut;
    }

    private function groupOfParent(FkEdge $edge): GroupKey
    {
        $owner = $this->registry->ownerOf($edge->parent);

        if ($owner === null) {
            throw new \LogicException("{$edge->child} references {$edge->parent}, which belongs to no single data group: classify it in GroupRegistry.");
        }

        if ($owner === GroupKey::System) {
            throw new \LogicException("{$edge->child} references the system table {$edge->parent}: system data never travels, so this reference cannot be honoured.");
        }

        return $owner;
    }

    /**
     * Parents first (Kahn's algorithm, ties broken by name so the result is stable). Tables that only
     * reference each other through optional references are ordered by ignoring those references; a cycle
     * of NOT NULL references would be unloadable and is reported as an error.
     *
     * @param  array<int, string>  $tables
     * @return array{0: array<int, string>, 1: array<int, FkEdge>} children-first order, and the optional edges that had to be ignored
     */
    private function order(array $tables): array
    {
        $inSet = array_flip($tables);
        $edges = array_values(array_filter(
            $this->graph->edges(),
            static fn (FkEdge $e): bool => isset($inSet[$e->child]) && isset($inSet[$e->parent])
        ));

        $sorted = $this->kahn($tables, $edges);
        if ($sorted !== null) {
            return [array_reverse($sorted), []];
        }

        $strongOnly = array_values(array_filter($edges, static fn (FkEdge $e): bool => $e->strong));
        $sorted = $this->kahn($tables, $strongOnly);

        if ($sorted === null) {
            throw new \LogicException('The tables have a cycle of NOT NULL references; they cannot be loaded in any order.');
        }

        return [array_reverse($sorted), array_values(array_filter($edges, static fn (FkEdge $e): bool => ! $e->strong))];
    }

    /**
     * @param  array<int, string>  $tables
     * @param  array<int, FkEdge>  $edges
     * @return array<int, string>|null null when there is a cycle
     */
    private function kahn(array $tables, array $edges): ?array
    {
        $waiting = array_fill_keys($tables, 0);
        $children = [];

        foreach ($edges as $edge) {
            $waiting[$edge->child]++;
            $children[$edge->parent][] = $edge->child;
        }

        $ready = array_keys(array_filter($waiting, static fn (int $n): bool => $n === 0));
        sort($ready);
        $sorted = [];

        while ($ready !== []) {
            $table = array_shift($ready);
            $sorted[] = $table;

            foreach ($children[$table] ?? [] as $child) {
                if (--$waiting[$child] === 0) {
                    $ready[] = $child;
                    sort($ready);
                }
            }
        }

        return count($sorted) === count($tables) ? $sorted : null;
    }
}
