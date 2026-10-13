<?php

namespace App\Services\Migration\Export\Stages;

use App\Models\BackupRun;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\FenceProbe;

/**
 * The first step of an export. In freeze mode the freeze engages HERE, in the first advance of whatever
 * drives the run, not in the request that started it: a driver that never starts (no worker is running)
 * therefore never freezes the shop. Then the fence probe takes its "before" picture.
 *
 * Idempotent: a driver that dies after engaging and steps again finds the freeze already in place.
 */
class ExportPlanStage implements BackupStage
{
    public function __construct(
        private readonly ExportFreeze $freeze,
        private readonly FenceProbe $probe,
    ) {}

    public function key(): string
    {
        return 'plan';
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        $plan = ExportPlan::fromArray((array) ($run->meta['export_plan'] ?? throw new \RuntimeException('This export run has no plan.')));

        if ($plan->isFreeze()) {
            $this->freeze->engage((int) $run->getKey(), $plan->freezeCapMinutes);
        }

        $meta = $run->meta ?? [];
        $meta['export_report'] = array_merge((array) ($meta['export_report'] ?? []), [
            'consistency_requested' => $plan->consistency,
            'fence' => ['before' => $this->probe->snapshot($plan->tables, $plan->estimate->tables), 'taken_at' => now()->toIso8601String()],
        ]);
        $run->meta = $meta;

        return StageStepResult::complete(null, $plan->isFreeze() ? 'The shop is frozen; copying can begin.' : 'Ready to copy.');
    }
}
