<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;

/**
 * The last stage of a dry run: what an import WOULD do, drawn from the package's own table of contents. Rows per table,
 * files and bytes, and which groups it would fill. Changes nothing and reads nothing but the package.
 */
final class DryRunPlanStage implements ImportStage
{
    public function __construct(private readonly Kdf $kdf) {}

    public function key(): string
    {
        return 'dry_run_plan';
    }

    public function destructive(): bool
    {
        return false;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        $entry = $context->escrow->get(ImportContext::escrowId($state->id));

        if ($entry === null) {
            return ImportStepResult::needsPassphrase('The key for this package has expired: type the passphrase again.');
        }

        try {
            $reader = PackageReader::openWithKeys((string) $state->package['path'], $entry->keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            $files = 0;
            $bytes = 0;
            $rows = 0;

            foreach ($reader->entries() as $e) {
                if (($e['kind'] ?? '') === 'db') {
                    $rows += (int) ($e['rows'] ?? 0);
                }

                if (($e['kind'] ?? '') === 'files') {
                    $bytes += (int) ($e['raw_len'] ?? 0);
                }
            }

            $files = (int) ($state->package['files_in_index'] ?? 0);
            $state->package['plan'] = [
                'groups' => $state->package['groups'] ?? [], 'tables' => $state->package['tables'] ?? [],
                'rows' => $rows, 'files' => $files, 'file_bytes' => $bytes,
                'rejected_paths' => $state->package['rejected_paths'] ?? [],
                'source' => $reader->meta()['source'] ?? [], 'app_version' => $reader->meta()['app_version'] ?? null,
            ];
        } finally {
            $reader->close();
        }

        return ImportStepResult::done('The plan is ready. Nothing was changed.');
    }
}
