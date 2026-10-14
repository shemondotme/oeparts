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

        return new LedgerComparison(
            packageCount: count($package),
            targetCount: count($target),
            missingInTarget: array_values(array_diff($package, $target)),
            missingInPackage: array_values(array_diff($target, $package)),
            packageVersion: $packageVersion,
            targetVersion: $targetVersion,
        );
    }
}
