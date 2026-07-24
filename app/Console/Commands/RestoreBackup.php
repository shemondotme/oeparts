<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\Backup\RestoreManager;
use App\Services\Backup\RestoreOptions;
use Illuminate\Console\Command;

/**
 * Restore a backup — same-server (by run ID) or cross-server (by importing
 * an unencrypted manifest.json TOC copied from another install's backup
 * disk — App\Services\Backup\RestoreManager::importManifest(), built for
 * exactly this but never wired to anything reachable until now). CLI-only,
 * no queue worker, no admin panel required (rule #41) — this is exactly the
 * tool an operator needs when moving to a new host or recovering a wiped
 * database, situations where a web UI (or even the old install) may not be
 * reachable at all. See README's "Moving to a new server" section.
 *
 * Deliberately CLI-only for now, not a Filament admin page: a "Restore"
 * button belongs behind the same re-auth + confirmation + audit trail this
 * project already requires for backup download (rule #45) — building that
 * safely is a separate, more careful piece of work than this command.
 */
class RestoreBackup extends Command
{
    protected $signature = 'oeparts:backup:restore
        {--run= : backup_runs.id on THIS server to restore}
        {--import-manifest= : Path to manifest.json on --disk, from a backup copied from another server}
        {--disk=local : Disk --import-manifest lives on}
        {--database : Restore the database (default when neither this nor --files is given: restore both)}
        {--files : Restore files}
        {--files-to= : Target directory for restored files (default: storage/app/restore/run-{id})}
        {--table= : Restore a single database table only}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Restore a backup, same-server or cross-server (see --import-manifest)';

    public function handle(RestoreManager $manager): int
    {
        $run = $this->resolveRun($manager);
        if (! $run) {
            return self::FAILURE;
        }

        $options = $this->buildOptions();

        $this->warn('Backup run #'.$run->getKey().' — profile: '.$run->profile.', taken: '.($run->started_at ?? 'unknown').'.');
        if ($options->database) {
            $this->warn('This OVERWRITES tables in the CURRENT database with data from the backup.');
        }

        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            $this->info('Cancelled — nothing was changed.');

            return self::SUCCESS;
        }

        foreach ($manager->validateVersion($run, $options->strictVersion) as $warning) {
            $this->warn($warning);
        }

        $report = $manager->restore($run, $options);

        foreach ($report->warnings as $warning) {
            $this->warn($warning);
        }
        foreach ($report->errors as $error) {
            $this->error($error);
        }

        $this->info('Statements run: '.$report->statementsRun.'; tables restored: '.count($report->tablesRestored).'.');
        $this->info('Files restored: '.$report->filesRestored.' (verified: '.$report->filesVerified.').');

        return $report->ok() ? self::SUCCESS : self::FAILURE;
    }

    private function resolveRun(RestoreManager $manager): ?BackupRun
    {
        if ($manifestPath = $this->option('import-manifest')) {
            try {
                return $manager->importManifest((string) $this->option('disk'), $manifestPath);
            } catch (\Throwable $e) {
                $this->error('Could not import manifest: '.$e->getMessage());

                return null;
            }
        }

        $runId = $this->option('run');
        if (! $runId) {
            $this->error('Pass --run=<id> for a same-server restore, or --import-manifest=<path> --disk=<disk> for a cross-server one.');

            return null;
        }

        $run = BackupRun::find($runId);
        if (! $run) {
            $this->error('Backup run #'.$runId.' not found.');

            return null;
        }

        return $run;
    }

    private function buildOptions(): RestoreOptions
    {
        $wantsDatabase = (bool) $this->option('database');
        $wantsFiles = (bool) $this->option('files');
        $table = $this->option('table');

        // Neither --database nor --files given → restore both (the common case).
        if (! $wantsDatabase && ! $wantsFiles && ! $table) {
            $wantsDatabase = true;
            $wantsFiles = true;
        }

        return new RestoreOptions(
            database: $wantsDatabase || $table !== null,
            files: $wantsFiles,
            table: $table,
            targetRoot: $this->option('files-to'),
        );
    }
}
