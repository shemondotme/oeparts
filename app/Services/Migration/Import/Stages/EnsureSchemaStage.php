<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;

/**
 * Stage 3. The new site's database structure must be exactly the package's (rule R2), and this stage makes sure it is
 * THIS site's own: on the installer path the database is empty, so the migrations are run (`migrate --force`, which only
 * adds what is missing and can be repeated); on the admin panel and the command line the site is already installed, so
 * nothing is run, and anything still pending is a refusal: update the site first, then import.
 *
 * It creates only empty structure. Not one row of the site changes.
 */
final class EnsureSchemaStage implements ImportStage
{
    public function __construct(private readonly Migrator $migrator) {}

    public function key(): string
    {
        return 'ensure_schema';
    }

    public function destructive(): bool
    {
        return false;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('schema.done')) {
            return ImportStepResult::done('The database structure is ready.');
        }

        if (($state->options['door'] ?? 'cli') === 'installer') {
            $exit = Artisan::call('migrate', ['--force' => true]);

            if ($exit !== 0) {
                throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'ensure_schema']);
            }
        }

        $pending = $this->pending();

        if ($pending !== []) {
            throw new MigrationException(MigrationErrorCode::MigrationSetDiffers, ['pending' => count($pending)]);
        }

        $state->setFlag('schema.done');

        return ImportStepResult::done('The database structure is ready.');
    }

    /** @return array<int, string> the migrations whose files exist and that have not been run */
    private function pending(): array
    {
        $repository = $this->migrator->getRepository();
        $ran = $repository->repositoryExists() ? $repository->getRan() : [];
        $files = array_keys($this->migrator->getMigrationFiles(array_merge($this->migrator->paths(), [database_path('migrations')])));

        return array_values(array_diff($files, $ran));
    }
}
