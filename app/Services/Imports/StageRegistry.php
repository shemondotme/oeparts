<?php

namespace App\Services\Imports;

use App\Services\Imports\Contracts\ImportStage;
use App\Services\Imports\Exceptions\ImportException;

/**
 * Resolves the ordered list of stages for an import profile from
 * config('imports.stages'). Entries may be class-string names (resolved
 * through the container) or already-instantiated ImportStage objects
 * (handy for tests).
 */
class StageRegistry
{
    /**
     * @return ImportStage[]
     */
    public function forProfile(string $profile): array
    {
        $map     = (array) config('imports.stages', []);
        $entries = (array) ($map[$profile] ?? []);

        return array_map(function ($entry) {
            $stage = $entry instanceof ImportStage ? $entry : app($entry);

            if (! $stage instanceof ImportStage) {
                throw new ImportException(
                    'Import stage '.(is_object($entry) ? $entry::class : (string) $entry)
                    .' must implement '.ImportStage::class.'.'
                );
            }

            return $stage;
        }, array_values($entries));
    }
}
