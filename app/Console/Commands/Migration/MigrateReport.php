<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Import\ImportReportBuilder;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\MigrationLedger;
use Illuminate\Console\Command;

/**
 * Shows the report of an import run: what was asked, checked and loaded, and what needs a person's eye. A finished run has
 * its report written already; for one that is still going (or ended before reports existed) it is built from the run's state
 * on the spot. With no run id it lists the runs. It never changes anything but, with --write, the report file.
 */
class MigrateReport extends Command
{
    use EmitsJson;

    protected $signature = 'oeparts:migrate:report
        {run? : The id of an import run (leave out to list the runs)}
        {--write : Write the report file next to the run if there is none yet}
        {--strict : Exit with a non-zero code when the report holds warnings}
        {--json : Print newline-delimited JSON events on standard output}';

    protected $description = 'Show the report of an import run, or list the runs';

    public function handle(MigrationLedger $ledger, ImportReportBuilder $reports, ImportStateStore $store): int
    {
        $id = $this->argument('run');

        if ($id === null) {
            $runs = $ledger->runs();

            foreach ($runs as $run) {
                $this->human(sprintf('%s  %-14s %-9s %s%s', $run['id'], $run['status'], $run['kind'], $run['started_at'], $run['has_report'] ? '  (report)' : ''));
            }

            if ($runs === []) {
                $this->human('There are no import runs on this site.');
            }

            return $this->finish(ExitCode::Ok, ['runs' => $runs]);
        }

        if (! ImportStateStore::validId((string) $id) || ($state = $store->find((string) $id)) === null) {
            $this->human('There is no import run with that id.');

            return $this->finish(ExitCode::Usage, ['error' => 'run_not_found']);
        }

        $report = $ledger->report($state->id) ?? $reports->build($state);

        if ($this->option('write') && $ledger->report($state->id) === null) {
            $reports->write($state);
            $this->human('Report written to '.$reports->path($state->id));
        }

        $this->human('Import '.$state->id.': '.$report['run']['status']);
        $this->human('  '.$report['database']['tables_loaded'].' table(s), '.$report['database']['rows_loaded'].' row(s) loaded'.($report['files'] === null ? '.' : '; '.($report['files']['written'] ?? 0).' file(s) written.'));

        foreach ($report['warnings'] as $warning) {
            $this->humanWarn('  - '.$warning);
        }

        return $this->finish($report['warnings'] !== [] && $this->option('strict') ? ExitCode::FinishedWithWarnings : ExitCode::Ok, ['report' => $report], $report['warnings']);
    }
}
