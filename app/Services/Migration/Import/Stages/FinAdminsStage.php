<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportAdminPolicy;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * Stage 21. Somebody must be able to sign in. The package's administrators are the administrators of the new site; if an extra
 * one was asked for it is created now, and then at least one super administrator with a real password must exist, or the run
 * stops (and, the site being changed, rolls back) rather than leave a site nobody can enter.
 */
final class FinAdminsStage extends FinalisingStage
{
    public function __construct(ImportMarker $marker, private readonly ImportAdminPolicy $policy)
    {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_admins';
    }

    protected function after(): string
    {
        return 'fin.caches.done';
    }

    protected function flag(): string
    {
        return 'fin.admins.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('Someone can sign in.');
        }

        $this->guard($state);

        $extra = $this->policy->ensureExtra($state->options);
        $usable = $this->policy->usable();

        if ($usable < 1) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => $this->key(), 'why' => 'nobody would be able to sign in']);
        }

        $state->package['admins'] = ['usable' => $usable, 'extra_created' => $extra !== null];
        $state->setFlag($this->flag());

        return ImportStepResult::done($usable.' administrator(s) can sign in.');
    }
}
