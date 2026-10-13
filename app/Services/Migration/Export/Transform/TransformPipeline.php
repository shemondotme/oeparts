<?php

namespace App\Services\Migration\Export\Transform;

/**
 * The rules for one export, compiled per table. Rules run in the order they were given.
 */
final class TransformPipeline
{
    /** @var array<string, array<int, RowTransformer>> */
    private array $byTable = [];

    /** @param  array<int, RowTransformer>  $transformers */
    public function __construct(array $transformers)
    {
        foreach ($transformers as $transformer) {
            foreach ($transformer->tables() as $table) {
                $this->byTable[$table][] = $transformer;
            }
        }
    }

    /**
     * The function to run over each row of $table, or null when no rule applies (the common case: the
     * caller then skips the call altogether).
     *
     * @return (\Closure(array<string, mixed>): array<string, mixed>)|null
     */
    public function forTable(string $table): ?\Closure
    {
        $rules = $this->byTable[$table] ?? [];

        if ($rules === []) {
            return null;
        }

        return static function (array $row) use ($table, $rules): array {
            foreach ($rules as $rule) {
                $row = $rule->transform($table, $row);
            }

            return $row;
        };
    }

    /** @return array<int, string> tables that have at least one rule */
    public function tablesWithRules(): array
    {
        $tables = array_keys($this->byTable);
        sort($tables);

        return $tables;
    }
}
