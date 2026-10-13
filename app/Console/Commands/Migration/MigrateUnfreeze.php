<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Models\BackupRun;
use App\Security\PackageAudit;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\FreezeState;
use Illuminate\Console\Command;

/**
 * Reopens a shop that an export froze: puts maintenance mode back to exactly what it was and lets the
 * customer queues run again. If the export is still running it is stopped (a copy taken while the shop is
 * open again would not be consistent); if it is finished and the freeze was only being held, nothing is lost.
 */
class MigrateUnfreeze extends Command
{
    use EmitsJson;

    protected $signature = 'oeparts:migrate:unfreeze {--json : Print newline-delimited JSON events on standard output}';

    protected $description = 'Reopen a shop that a freeze export is holding in maintenance';

    public function handle(ExportFreeze $freeze, ExportManager $manager, PackageAudit $audit): int
    {
        $state = FreezeState::read();

        if ($state === null) {
            $freeze->release('nothing was frozen'); // also clears a stray unreadable file
            $this->human('The shop is not frozen.');

            return $this->finish(ExitCode::Ok, ['was_frozen' => false]);
        }

        $run = BackupRun::query()->exports()->whereKey($state->runId)->first();

        if ($run !== null && ! $run->isTerminal()) {
            // Stopping the run releases the freeze too (ExportManager::fail), and says why in the run's history.
            $manager->fail($run, 'Unfrozen by the operator while the export was running: the copy is not consistent.');
            $this->human('The export that was still running has been stopped.');
        }

        $freeze->release('operator');
        $audit->record('export.unfrozen', (string) $state->runId, ['by' => 'command line']);

        $this->human('The shop is open again.');

        return $this->finish(ExitCode::Ok, ['was_frozen' => true, 'run_id' => $state->runId]);
    }
}
