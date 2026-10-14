<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Services\Migration\ExitCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\TransferManager;
use Illuminate\Console\Command;

/** Stops a package download and throws away what it had received. */
class MigrateCancel extends Command
{
    use EmitsJson;

    protected $signature = 'oeparts:migrate:cancel {id : The id of the download} {--json : Print newline-delimited JSON events on standard output}';

    protected $description = 'Cancel a package download';

    public function handle(TransferManager $manager): int
    {
        try {
            $state = $manager->cancel((string) $this->argument('id'));
        } catch (MigrationException $e) {
            $this->reportError($e);

            return $this->finish(ExitCode::Usage, ['error' => $e->errorCode->value]);
        }

        $this->human('Download '.$state->id.': '.$state->status->value.'.');

        return $this->finish(ExitCode::Ok, ['transfer_id' => $state->id, 'status' => $state->status->value]);
    }
}
