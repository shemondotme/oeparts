<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Transport\TransferManager;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/** Lists the downloads that are running or finished and the packages waiting in the import folder. */
class MigrateTransfers extends Command
{
    use EmitsJson;

    protected $signature = 'oeparts:migrate:list {--json : Print newline-delimited JSON events on standard output}';

    protected $description = 'List package downloads and the packages waiting in the import folder';

    public function handle(TransferManager $manager, ImportInbox $inbox): int
    {
        $transfers = array_map(static fn ($state): array => $manager->progress($state), $manager->store()->all());
        $packages = $inbox->list();

        foreach ($transfers as $t) {
            $this->event('transfer', $t);
        }

        foreach ($packages as $p) {
            $this->event('package', $p);
        }

        if (! $this->jsonOutput()) {
            $this->line('Downloads:');
            $this->table(['Id', 'Status', 'From', 'File', 'Done', 'Note'], array_map(static fn (array $t): array => [
                $t['id'], $t['status'], $t['host'], $t['name'],
                $t['percent'] === null ? Number::fileSize($t['bytes']) : $t['percent'].'%', $t['message'],
            ], $transfers));

            $this->line('Packages in the import folder:');
            $this->table(['Name', 'Size', 'Modified'], array_map(static fn (array $p): array => [$p['name'], Number::fileSize($p['size']), date('Y-m-d H:i', $p['modified'])], $packages));
        }

        return $this->finish(ExitCode::Ok, ['transfers' => count($transfers), 'packages' => count($packages)]);
    }
}
