<?php

namespace App\Services\Migration\Import\Contracts;

/**
 * What the runner page (and the command line, and the watchdog) ask of an import run: how is it going, and do one
 * more budgeted piece of work. The engine that implements it comes with the import manager; until then the page and
 * its routes are served by a driver that only reports the state file.
 */
interface RunDriver
{
    /**
     * Do at most about `$budgetSeconds` of work on the run, then report.
     *
     * @return array<string, mixed> the progress document (see StatusOnlyRunDriver for its shape)
     */
    public function advance(string $runId, float $budgetSeconds): array;

    /**
     * Report without doing anything.
     *
     * @return array<string, mixed>
     */
    public function status(string $runId): array;
}
