<?php

namespace App\Services\Backup;

use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\Exceptions\BackupException;

/**
 * StageRegistry (Module 14/21, Chunk 2.1) — resolves the ordered list of stages
 * for a backup profile from config('backup.stages'). Entries may be class-string
 * names (resolved through the container, so stages can have dependencies) or
 * already-instantiated BackupStage objects (handy for tests).
 *
 * Chunk 2.1 ships this seam with empty stage lists; the DB (2.2) and file (2.3)
 * stages register their classes into config as they land.
 */
class StageRegistry
{
    /**
     * @return BackupStage[]
     */
    public function forProfile(string $profile): array
    {
        $map     = (array) config('backup.stages', []);
        $entries = (array) ($map[$profile] ?? []);

        return array_map(function ($entry) {
            $stage = $entry instanceof BackupStage ? $entry : app($entry);

            if (! $stage instanceof BackupStage) {
                throw new BackupException(
                    'Backup stage '.(is_object($entry) ? $entry::class : (string) $entry)
                    .' must implement '.BackupStage::class.'.'
                );
            }

            return $stage;
        }, array_values($entries));
    }
}
