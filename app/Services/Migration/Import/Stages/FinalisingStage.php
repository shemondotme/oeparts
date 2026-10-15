<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Support\Facades\Log;

/**
 * The common shape of the stages that finish an import: they change the site, so they refuse to run unless it is closed and the
 * stage before them is finished, and each says in its own flag that it is done, so a resumed run never repeats one.
 */
abstract class FinalisingStage implements ImportStage
{
    public function __construct(protected readonly ImportMarker $marker) {}

    /** The flag the stage before this one set when it finished. */
    abstract protected function after(): string;

    /** This stage's own flag. */
    abstract protected function flag(): string;

    public function destructive(): bool
    {
        return true;
    }

    /** A problem that must not stop the import (a sitemap that could not be built): kept by stage name, shown in the report. */
    protected function warn(ImportState $state, string $message): void
    {
        $state->package['stage_warnings'][$this->key()][] = $message;
    }

    /**
     * Same as warn(), for a caught exception: the report (signed, shown in the installer) gets the fixed sentence only; what the
     * exception said (it can name a host or a path) goes to the migration log.
     */
    protected function warnFailure(ImportState $state, string $sentence, \Throwable $e): void
    {
        Log::channel('migration')->warning("Import {$state->id}, stage {$this->key()}: {$sentence} ".$e->getMessage());
        $this->warn($state, $sentence);
    }

    /** @throws MigrationException the site is not closed, or the stage before has not finished */
    protected function guard(ImportState $state): void
    {
        if (! $this->marker->isActive() || ! $state->flag($this->after())) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => $this->key(), 'why' => 'the stage before it has not finished']);
        }
    }
}
