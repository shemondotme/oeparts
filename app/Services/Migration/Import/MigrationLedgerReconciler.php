<?php

namespace App\Services\Migration\Import;

use Illuminate\Support\Facades\DB;

/**
 * Rule R2: rows are only carried to a site whose database was built by exactly the same migrations. Rows written for
 * one schema cannot be poured into another, and a data-backfill migration cannot be replayed over imported rows, so
 * the package and the target must have run the SAME set. A different app version with an identical set is only a
 * warning; ANY difference in the set is a failure with one instruction: make both sites the same release first.
 */
final class MigrationLedgerReconciler
{
    /**
     * @param  array<int, string>  $packageMigrations  the migration names the package says its site had run
     */
    public function compare(array $packageMigrations, ?string $packageVersion, ?string $targetVersion): LedgerComparison
    {
        $package = array_values(array_unique(array_map('strval', $packageMigrations)));
        $target = array_values(array_unique(array_map('strval', DB::table('migrations')->pluck('migration')->all())));

        sort($package);
        sort($target);

        [$missingInTarget, $retired] = $this->splitRetired(array_values(array_diff($package, $target)));

        return new LedgerComparison(
            packageCount: count($package),
            targetCount: count($target),
            missingInTarget: $missingInTarget,
            missingInPackage: array_values(array_diff($target, $package)),
            packageVersion: $packageVersion,
            targetVersion: $targetVersion,
            retired: $retired,
        );
    }

    /**
     * Names the package's site ran that this site cannot run. Only a name with NO migration file here, whose timestamp is not
     * newer than the newest file here, is RETIRED (the release removed or renamed it); anything else stays a real mismatch.
     *
     * @param  array<int, string>  $names
     * @return array{0: array<int, string>, 1: array<int, string>} [still missing, retired]
     */
    private function splitRetired(array $names): array
    {
        if ($names === []) {
            return [[], []];
        }

        $files = array_keys(app('migrator')->getMigrationFiles(array_merge(app('migrator')->paths(), [database_path('migrations')])));
        $newest = '';

        foreach ($files as $file) {
            $newest = max($newest, substr((string) $file, 0, 17));
        }

        $missing = [];
        $retired = [];

        foreach ($names as $name) {
            $isRetired = ! in_array($name, $files, true)
                && preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $name) === 1
                && $newest !== ''
                && substr($name, 0, 17) <= $newest;

            if ($isRetired) {
                $retired[] = $name;
            } else {
                $missing[] = $name;
            }
        }

        return [$missing, $retired];
    }
}
