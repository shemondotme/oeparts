<?php

namespace App\Services\Migration\Import;

/** Where an import run is in its life. */
enum ImportStatus: string
{
    /** Stages are running, one budgeted step per advance. */
    case Running = 'running';

    /** Everything that could be checked without changing anything has been; waiting for a person to say go. */
    case AwaitingConfirmation = 'awaiting_confirmation';

    /** A destructive stage failed: the recovery point is being put back. */
    case RollingBack = 'rolling_back';

    /** The old site is back exactly as it was. */
    case RolledBack = 'rolled_back';

    /** The recovery point could not be put back: the site stays closed and a person must act. */
    case RollbackFailed = 'rollback_failed';

    /** Stopped before anything was changed (or the environment was undone): there is nothing to roll back. */
    case Failed = 'failed';

    /** Done. */
    case Complete = 'complete';

    /** Nothing more will happen to this run by itself. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Complete, self::Failed, self::RolledBack], true);
    }

    /** The run may still own the site (marker, lock): the gate and the janitors must treat it as live. */
    public function mayHoldTheSite(): bool
    {
        return ! $this->isTerminal();
    }

    /** A person has to do something before anything else can happen. */
    public function needsAttention(): bool
    {
        return in_array($this, [self::AwaitingConfirmation, self::RollbackFailed], true);
    }
}
