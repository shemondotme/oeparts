<?php

namespace App\Services\Migration\Export\Stages;

use App\Models\BackupRun;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\FenceProbe;
use App\Services\Migration\Groups\GroupRegistry;

/**
 * After the last row and the last file: take the "after" picture, reconcile what was written with what the
 * database holds, and decide how consistent the copy is.
 *
 *  - live copy: the verdict is always "eventual"; drift is reported as information.
 *  - freeze: "consistent" only if nothing at all changed between the pictures and every exact row count
 *    matches what was written; otherwise "not_consistent", and the report says which tables moved.
 *
 * Then the freeze is lifted, unless the plan holds it until the package is sealed.
 */
class ExportVerifyStage implements BackupStage
{
    public function __construct(
        private readonly ExportFreeze $freeze,
        private readonly FenceProbe $probe,
    ) {}

    public function key(): string
    {
        return 'verify';
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        $plan = ExportPlan::fromArray((array) ($run->meta['export_plan'] ?? throw new \RuntimeException('This export run has no plan.')));
        $report = (array) ($run->meta['export_report'] ?? []);

        $after = $this->probe->snapshot($plan->tables, $plan->estimate->tables);
        $before = (array) ($report['fence']['before'] ?? []);
        $drift = FenceProbe::drift($before, $after);
        $written = (array) ($report['db']['rows_by_table'] ?? []);

        // Exact counts only: a table too big to count in the probe is checked by its key and last update instead.
        $mismatches = [];
        foreach ($written as $table => $rows) {
            // A shared table that travels in part (the SEO rows of products only) has fewer rows written than it holds.
            if ($table === GroupRegistry::SHARED_TABLE && $plan->seoSlice !== ExportPlan::SEO_ALL) {
                continue;
            }

            $count = $after[$table]['count'] ?? null;

            if ($count !== null && $count !== (int) $rows) {
                $mismatches[$table] = ['written' => (int) $rows, 'in_database' => $count];
            }
        }

        $verdict = match (true) {
            ! $plan->isFreeze() => 'eventual',
            $drift === [] && $mismatches === [] => 'consistent',
            default => 'not_consistent',
        };

        $meta = $run->meta ?? [];
        $meta['export_report'] = array_merge($report, [
            'consistency' => $verdict,
            'fence' => array_merge((array) ($report['fence'] ?? []), ['after' => $after, 'drift' => $drift]),
            'reconciliation' => ['mismatches' => $mismatches, 'tables_checked' => count($written)],
        ]);
        $run->meta = $meta;

        // A held freeze stays until the package is sealed and the hold timer takes over (see PackageAssembleStage).
        if ($plan->isFreeze() && ! $plan->hold) {
            $this->freeze->release('export finished');
        }

        return StageStepResult::complete(null, "Verified: {$verdict}.");
    }
}
