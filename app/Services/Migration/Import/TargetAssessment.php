<?php

namespace App\Services\Migration\Import;

/** The answer of TargetFreshness: the class, and the numbers behind it so a person can see what would be replaced. */
final class TargetAssessment
{
    /**
     * @param  array<string, int>  $businessRows  table => rows, only the tables whose rows are business activity
     * @param  array<string, int>  $tableRows  table => rows for every non-system table that has any
     */
    public function __construct(
        public readonly TargetClass $class,
        public readonly array $businessRows,
        public readonly array $tableRows,
        public readonly int $totalRows,
        public readonly int $nonCoreRows,
        public readonly int $rowCap,
    ) {}

    /** More rows than the recovery point can hold: a recovery point would be refused, so the import is too. */
    public function exceedsRecoveryCap(): bool
    {
        return $this->totalRows > $this->rowCap;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'class' => $this->class->value, 'business_rows' => $this->businessRows, 'total_rows' => $this->totalRows,
            'non_core_rows' => $this->nonCoreRows, 'row_cap' => $this->rowCap, 'exceeds_recovery_cap' => $this->exceedsRecoveryCap(),
            'tables' => $this->tableRows,
        ];
    }
}
