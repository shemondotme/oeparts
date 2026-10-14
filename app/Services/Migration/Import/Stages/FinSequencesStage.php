<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Instance\InstanceState;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\SequenceFloor;
use App\Services\Migration\Import\TargetFreshness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 19. A number that was issued is never issued again: every series' counter is raised to the highest number already in
 * the orders and invoices (never lowered). Then the starting point of THIS site is written to the instance ledger: where each
 * counter stood, and the highest id in every table that holds business activity. Going live discards what a staging copy
 * created, and "created here" means "above these ids".
 */
final class FinSequencesStage extends FinalisingStage
{
    public function __construct(ImportMarker $marker, private readonly SequenceFloor $floor, private readonly InstanceState $ledger)
    {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_sequences';
    }

    protected function after(): string
    {
        return 'fin.origin.done';
    }

    protected function flag(): string
    {
        return 'fin.sequences.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The numbering is in order.');
        }

        $this->guard($state);

        $series = $this->floor->apply();
        $watermarks = [];

        foreach (TargetFreshness::BUSINESS_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $watermarks[$table] = (int) (DB::table($table)->max('id') ?? 0);
            }
        }

        $this->ledger->update(function (array $ledger) use ($series): array {
            $ledger['sequence_baseline'] = ['at' => now()->toIso8601String(), 'series' => $series];

            return $ledger;
        });
        $this->ledger->setWatermarks($watermarks);

        $state->package['sequences'] = array_map(static fn (array $s): ?int => $s['raised_from'], $series);
        $state->setFlag($this->flag());

        return ImportStepResult::done(count(array_filter($state->package['sequences'], static fn (?int $v): bool => $v !== null)).' counter(s) raised.');
    }
}
