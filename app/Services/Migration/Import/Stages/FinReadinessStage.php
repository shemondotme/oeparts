<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;

/**
 * Stage 27. The launch checks. Until the readiness framework exists this stage always passes and says so, so the list of stages
 * has no hole between the stages before it and the last one; the real checks replace its body.
 */
final class FinReadinessStage extends FinalisingStage
{
    public function key(): string
    {
        return 'fin_readiness';
    }

    protected function after(): string
    {
        return 'fin.backup_key.done';
    }

    protected function flag(): string
    {
        return 'fin.readiness.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The launch checks are recorded.');
        }

        $this->guard($state);

        $state->package['readiness'] = ['status' => 'not_implemented'];
        $state->setFlag($this->flag());

        return ImportStepResult::done('The launch checks are not part of this version yet.');
    }
}
