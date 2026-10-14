<?php

namespace Tests\Feature;

use App\Services\Migration\Import\ImportStageRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\KillMatrix;
use Tests\TestCase;

/**
 * The guard behind the kill matrix of plan section 11.4: no stage may exist without a row saying how it survives
 * being killed, and no row may linger for a stage that is neither implemented nor named as "not landed yet".
 */
class KillMatrixCoverageTest extends TestCase
{
    /** @return array<int, string> "side:stage" of every stage that really exists */
    private function realStages(): array
    {
        $real = [];

        foreach (app(ImportStageRegistry::class)->keys('import') as $key) {
            $real[] = KillMatrix::IMPORT.':'.$key;
        }

        foreach ((array) config('backup.stages.export', []) as $class) {
            $real[] = KillMatrix::EXPORT.':'.app($class)->key();
        }

        return $real;
    }

    #[Test]
    public function every_stage_that_exists_has_a_row_in_the_matrix(): void
    {
        $missing = array_values(array_diff($this->realStages(), KillMatrix::keys()));

        $this->assertSame([], $missing, 'A stage exists with no kill-matrix row. Add it to tests/Support/KillMatrix.php and write its kill test.');
    }

    #[Test]
    public function every_row_is_a_stage_that_exists_or_is_named_as_not_landed_yet(): void
    {
        $exempt = array_map(
            static fn (array $row): string => $row['side'].':'.$row['stage'],
            array_filter(KillMatrix::rows(), static fn (array $row): bool => $row['side'] === KillMatrix::PSEUDO),
        );

        $orphans = array_values(array_diff(KillMatrix::keys(), $this->realStages(), KillMatrix::allowedMissing(), $exempt));

        $this->assertSame([], $orphans, 'A matrix row names a stage that does not exist and is not on the not-landed-yet list.');
    }

    #[Test]
    public function the_not_landed_yet_list_only_names_rows_that_exist_and_stages_that_do_not_exist_yet(): void
    {
        $real = $this->realStages();

        foreach (KillMatrix::allowedMissing() as $key) {
            $this->assertContains($key, KillMatrix::keys(), "{$key} is on the allowed-missing list but has no row");
            $this->assertNotContains($key, $real, "{$key} has landed: take it off the allowed-missing list");
        }
    }

    #[Test]
    public function every_row_says_what_a_kill_looks_like_what_resuming_does_and_what_is_asserted(): void
    {
        $seen = [];

        foreach (KillMatrix::rows() as $row) {
            foreach (['kill', 'resume', 'assert'] as $field) {
                $this->assertNotSame('', trim($row[$field]), "{$row['side']}:{$row['stage']} has no {$field}");
            }

            $this->assertNotContains($row['side'].':'.$row['stage'], $seen, 'a row appears twice');
            $seen[] = $row['side'].':'.$row['stage'];
        }
    }

    #[Test]
    public function the_two_sides_share_stage_names_without_clashing(): void
    {
        $keys = KillMatrix::keys();

        $this->assertContains('export:verify', $keys);
        $this->assertContains('import:verify', $keys);
        $this->assertContains('export:files', $keys);
        $this->assertContains('import:files', $keys);
    }
}
