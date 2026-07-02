<?php

namespace App\Services\Backup;

/** Outcome of a restore run (Module 14/21, Chunk 2.5). */
class RestoreReport
{
    /** @var string[] */
    public array $tablesRestored = [];
    public int $statementsRun = 0;
    public int $filesRestored = 0;
    public int $filesVerified = 0;
    /** @var string[] non-fatal notes (e.g. version skew). */
    public array $warnings = [];
    /** @var string[] verification failures encountered but survived. */
    public array $errors = [];

    public function ok(): bool
    {
        return $this->errors === [];
    }

    public function toArray(): array
    {
        return [
            'ok'              => $this->ok(),
            'tables_restored' => $this->tablesRestored,
            'statements_run'  => $this->statementsRun,
            'files_restored'  => $this->filesRestored,
            'files_verified'  => $this->filesVerified,
            'warnings'        => $this->warnings,
            'errors'          => $this->errors,
        ];
    }
}
