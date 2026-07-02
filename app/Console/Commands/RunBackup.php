<?php

namespace App\Console\Commands;

use App\Jobs\NotifyAdminsOfBackupFailure;
use App\Models\BackupRun;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * oeparts:backup (Module 21, Chunk 2.6) — runs a full, encrypted Backup Engine
 * backup and prunes old ones (GFS). Scheduled daily; also the single path the
 * "Run backup now" admin action goes through (via RunBackupJob). Supersedes the
 * old db:backup / mysqldump command (rule #41).
 *
 * On failure it alerts super_admins (queued) and returns a non-zero exit — the
 * scheduler / operator sees the failure.
 */
class RunBackup extends Command
{
    protected $signature = 'oeparts:backup
        {--profile=full : Backup profile (full|update_safety)}
        {--trigger=scheduled : What triggered this run (scheduled|manual|pre_update)}
        {--no-prune : Skip GFS retention pruning after a successful backup}';

    protected $description = 'Run an encrypted Backup Engine backup and prune old backups (GFS).';

    public function handle(BackupManager $manager, BackupRetentionService $retention): int
    {
        if (! config('backup.enabled', true)) {
            $this->warn('Backups are disabled (OE_BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        $profile = (string) $this->option('profile');
        $trigger = (string) $this->option('trigger');
        $started = microtime(true);

        try {
            $run = $manager->start($profile, $trigger);
        } catch (\Throwable $e) {
            // Lock held, missing OE_BACKUP_KEY, bad profile, …
            $this->error('Backup could not start: '.$e->getMessage());
            $this->logCron('failed', 0, $e->getMessage());
            dispatch(new NotifyAdminsOfBackupFailure($profile, $e->getMessage(), null, now()->toDateTimeString()));

            return self::FAILURE;
        }

        $manager->run($run);
        $run->refresh();
        $duration = (int) ((microtime(true) - $started) * 1000);

        if ($run->status === BackupRun::STATUS_FAILED) {
            $this->error('Backup #'.$run->getKey().' failed: '.$run->error);
            $this->logCron('failed', $duration, (string) $run->error);
            dispatch(new NotifyAdminsOfBackupFailure(
                $profile, (string) $run->error, (int) $run->getKey(), optional($run->finished_at)->toDateTimeString()
            ));

            return self::FAILURE;
        }

        $this->info('Backup #'.$run->getKey().' complete — '.$run->part_count.' parts, '.$run->total_bytes.' bytes.');

        if (! $this->option('no-prune')) {
            $result = $retention->prune();
            $this->info('Retention: kept '.$result['kept'].', pruned '.$result['pruned'].'.');
        }

        $this->logCron('success', $duration, 'Backup #'.$run->getKey().' ('.$run->part_count.' parts)');

        return self::SUCCESS;
    }

    private function logCron(string $status, int $durationMs, string $output): void
    {
        try {
            DB::table('cron_logs')->insert([
                'job_name'    => 'oeparts:backup',
                'status'      => $status,
                'duration_ms' => $durationMs,
                'output'      => $output,
                'ran_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // cron_logs is best-effort observability; never fail the backup on it.
        }
    }
}
