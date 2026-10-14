<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\MigrationPaths;

/**
 * A plain text file left on the server for the person who finds the site closed in the middle of an import and cannot open the
 * admin panel (it is being replaced): what is going on and the exact commands that finish it or take it back. Written when the site
 * is closed, removed when the import ends. The same text is shown by the command line whenever a run fails.
 */
final class RecoveryNote
{
    public static function path(): string
    {
        return MigrationPaths::root().DIRECTORY_SEPARATOR.'RECOVERY.txt';
    }

    public static function text(string $runId): string
    {
        return "An import of this site (run {$runId}) is in progress or stopped half way.\n"
            ."Nothing is lost: a recovery point was made before the first change.\n\n"
            ."From the server, in the folder of the site:\n"
            ."  php artisan oeparts:migrate:import --resume --run={$runId}      carry on where it stopped\n"
            ."  php artisan oeparts:migrate:import --rollback --run={$runId}    put the site back as it was\n\n"
            ."Until one of these finishes the site shows a maintenance page.\n";
    }

    public static function write(string $runId): void
    {
        MigrationPaths::ensureDirectory(MigrationPaths::root());
        @file_put_contents(self::path(), self::text($runId));
    }

    public static function remove(): void
    {
        if (is_file(self::path())) {
            @unlink(self::path());
        }
    }
}
