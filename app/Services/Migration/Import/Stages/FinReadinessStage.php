<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\Readiness\ReadinessRunner;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * Stage 27. The launch checks that need nothing but the data and the settings (the "static" ones: row counts, references, files,
 * schema, language, identity, numbering, secrets, mode, runtime state). Whatever the report finds is saved and listed in the run's
 * report; but only a failure in the DATA, IDENTITY or SECRETS groups stops the run, and so, the site being changed, rolls it
 * back: those are not matters of taste, the site would be broken. The checks that need the site to answer over HTTP or a worker to
 * pick up a job wait until the site is open (they cannot run while it is closed).
 */
final class FinReadinessStage extends FinalisingStage
{
    /** A failure in one of these groups is a reason to take the import back. */
    private const BLOCKING_GROUPS = ['data', 'identity', 'secrets'];

    public function __construct(ImportMarker $marker, private readonly ReadinessRunner $runner)
    {
        parent::__construct($marker);
    }

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

        $report = $this->runner->forPhases([ReadinessProbe::STATIC])->run(ReadinessContext::make($state));
        $blocking = array_values(array_filter($report->failures(), static fn (ReadinessCheck $c): bool => in_array($c->group, self::BLOCKING_GROUPS, true)));

        $state->package['readiness'] = [
            'counts' => $report->counts(),
            'failed' => array_map(static fn (ReadinessCheck $c): string => $c->key, $report->failures()),
            'blocking' => array_map(static fn (ReadinessCheck $c): string => $c->key, $blocking),
        ];

        if ($blocking !== []) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => $this->key(), 'why' => 'the launch checks found a problem in the data: '.implode(', ', $state->package['readiness']['blocking'])]);
        }

        $state->setFlag($this->flag());

        return ImportStepResult::done('The launch checks ran: '.$report->counts()[ReadinessCheck::FAIL].' failed, '.$report->counts()[ReadinessCheck::WARN].' to look at.');
    }
}
