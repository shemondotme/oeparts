<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Database\Migrations\Migrator;

/**
 * Stage 15. A last look that the structure still matches the data that was put into it: nothing may be pending. The package
 * was only accepted onto a site that had run exactly the same migrations (rule R2), and nothing since has changed that, so a
 * pending migration here means something touched the schema while the site was closed, and the run stops rather than
 * finishing on a database whose structure and rows no longer fit each other.
 */
final class FinCheckMigrationsStage extends FinalisingStage
{
    public function __construct(ImportMarker $marker, private readonly Migrator $migrator)
    {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_check_migrations';
    }

    protected function after(): string
    {
        return 'fin.secrets.done';
    }

    protected function flag(): string
    {
        return 'fin.migrations.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('No migration is pending.');
        }

        $this->guard($state);

        $repository = $this->migrator->getRepository();
        $ran = $repository->repositoryExists() ? $repository->getRan() : [];
        $files = array_keys($this->migrator->getMigrationFiles(array_merge($this->migrator->paths(), [database_path('migrations')])));
        $pending = array_values(array_diff($files, $ran));

        if ($pending !== []) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => $this->key(), 'why' => count($pending).' migration(s) are pending']);
        }

        $state->setFlag($this->flag());

        return ImportStepResult::done('No migration is pending.');
    }
}
