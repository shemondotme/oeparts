<?php

namespace App\Console\Commands\Migration;

use App\Security\PassphraseThrottle;
use App\Services\Migration\Export\ExportJanitor;
use App\Services\Migration\Transport\TransferJanitor;
use Illuminate\Console\Command;

/**
 * Hourly clean-up of everything the migration feature leaves behind: dead export runs and what they held,
 * orphaned package ledgers, packages past their retention, expired keys and old wrong-passphrase counters.
 */
class MigrateJanitor extends Command
{
    protected $signature = 'oeparts:migrate:janitor';

    protected $description = 'Clean up dead exports, expired packages, old keys and counters';

    public function handle(ExportJanitor $janitor, TransferJanitor $transfers): int
    {
        $report = $janitor->run();
        $report['throttle'] = PassphraseThrottle::forApplication()->prune();
        $downloads = $transfers->run();

        $this->line(sprintf(
            'Janitor: %d dead export(s) closed, %d never-started export(s) closed, %d orphaned ledger(s), %d expired package(s), %d old key file(s), %d old counter(s) removed.',
            $report['dead'], $report['unpicked'], $report['orphans'], $report['expired'], $report['escrow'], $report['throttle']
        ));
        $this->line(sprintf('Janitor: %d abandoned download(s) closed, %d old download record(s) removed.', $downloads['abandoned'], $downloads['removed']));

        return self::SUCCESS;
    }
}
