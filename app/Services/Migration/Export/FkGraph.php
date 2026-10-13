<?php

namespace App\Services\Migration\Export;

use Illuminate\Support\Facades\Schema;

/**
 * The foreign keys of the live database, read from the database itself (Laravel's schema inspection:
 * information_schema on MySQL, pragma on SQLite), never from a hand-kept list. A foreign key added by
 * next month's migration therefore changes the picture without anyone editing this class.
 *
 * Self-references (a category with a parent category) are not edges: they never decide which groups
 * travel together.
 */
final class FkGraph
{
    /** @var array<int, FkEdge>|null */
    private ?array $edges = null;

    /**
     * @param  array<int, string>|null  $tables  limit to these tables (as children); null = the whole database
     */
    public function __construct(private readonly ?array $tables = null) {}

    /**
     * A graph made of the given edges instead of read from the database. For tests that need a reference
     * the real schema does not have (a planted NOT NULL key, a cycle).
     *
     * @param  array<int, FkEdge>  $edges
     */
    public static function fromEdges(array $edges): self
    {
        $graph = new self;
        usort($edges, static fn (FkEdge $a, FkEdge $b): int => strcmp($a->signature(), $b->signature()));
        $graph->edges = $edges;

        return $graph;
    }

    /**
     * @return array<int, FkEdge> sorted, so two reads of the same schema are identical
     */
    public function edges(): array
    {
        if ($this->edges !== null) {
            return $this->edges;
        }

        $connection = Schema::getConnection();
        $schema = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null;
        $tables = $this->tables ?? array_values(array_filter(
            Schema::getTableListing($schema, schemaQualified: false),
            static fn (string $t): bool => ! str_starts_with($t, 'sqlite_')
        ));

        $edges = [];

        foreach ($tables as $table) {
            $nullable = [];
            foreach (Schema::getColumns($table) as $column) {
                $nullable[$column['name']] = (bool) $column['nullable'];
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if ($foreignKey['foreign_table'] === $table) {
                    continue;
                }

                $columns = array_values($foreignKey['columns']);
                $strong = true;
                foreach ($columns as $column) {
                    $strong = $strong && ($nullable[$column] ?? true) === false;
                }

                $edges[] = new FkEdge($table, $columns, $foreignKey['foreign_table'], array_values($foreignKey['foreign_columns']), $strong);
            }
        }

        usort($edges, static fn (FkEdge $a, FkEdge $b): int => strcmp($a->signature(), $b->signature()));

        return $this->edges = $edges;
    }

    /** @return array<int, FkEdge> the edges whose child is $table */
    public function edgesFrom(string $table): array
    {
        return array_values(array_filter($this->edges(), static fn (FkEdge $e): bool => $e->child === $table));
    }

    /** @return array<int, string> the canonical signatures, for comparing with a stored snapshot */
    public function signatures(): array
    {
        return array_map(static fn (FkEdge $e): string => $e->signature(), $this->edges());
    }
}
