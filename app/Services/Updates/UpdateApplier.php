<?php

namespace App\Services\Updates;

use App\Models\BackupRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Backup\RestoreManager;
use App\Services\Backup\RestoreOptions;
use App\Services\SettingsService;
use App\Services\Updates\Exceptions\UpdateException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * UpdateApplier (Module 21, Chunk 3.5) — the one-click apply orchestration FSM.
 *
 * A chunked, poll-driven state machine that wires 3.1–3.4 + the pre-update backup
 * into one resumable flow (mirrors the BackupManager FSM): start() gates on
 * pre-flight, takes the shared lock for the WHOLE apply, enters maintenance, and
 * writes an UpdateHistory row; each advance() runs ONE step and checkpoints
 * (status/step/step_index) so a killed poll resumes. Because each advance() is a
 * separate HTTP poll = a separate request, `finalize` naturally runs in a FRESH
 * request after `swap` (rule #46 — opcache holds OLD classes in the swap request).
 *
 * Failure handling follows the failure/rollback matrix: a failure BEFORE the swap
 * just cleans up (no changes to reverse); a failure AFTER the swap reverses the
 * swap and restores the DB from the pre-update backup (→ rolled_back). Post-up
 * verification + the auto-rollback smoke tests are enriched in Chunk 3.6.
 *
 * do*() steps resolve their collaborators via the container so they can be
 * overridden in tests without wiring every dependency.
 */
class UpdateApplier
{
    /** Ordered apply steps for a standard (shared-hosting-safe) install; `complete` runs once the index passes the last. */
    public const STEPS = ['backup', 'download', 'extract', 'swap', 'finalize', 'verify'];

    /**
     * Ordered apply steps for a git-managed install (PreflightService::
     * checkDeploymentType() detects .git) — git fetch/checkout + composer
     * install replace download/extract/swap; finalize/verify are identical
     * either way (they operate on whatever's on disk, not on how it got there).
     */
    public const GIT_STEPS = ['backup', 'git_checkout', 'composer_install', 'finalize', 'verify'];

    /* ---- Preview -------------------------------------------------------- */

    public function preview(array $manifest): UpdatePreview
    {
        return new UpdatePreview(
            fromVersion: app(UpdateChecker::class)->currentVersion(),
            toVersion: (string) ($manifest['version'] ?? 'unknown'),
            security: (bool) ($manifest['security'] ?? false),
            sizeBytes: isset($manifest['size_bytes']) ? (int) $manifest['size_bytes'] : null,
            migrationCount: (int) ($manifest['migration_count'] ?? 0),
            breakingChanges: (array) ($manifest['breaking_changes'] ?? []),
            etaSeconds: $this->estimateEta($manifest),
            preflight: app(PreflightService::class)->run($manifest),
            preUpdateNotes: $manifest['pre_update_notes'] ?? null,
        );
    }

    /* ---- Lifecycle ----------------------------------------------------- */

    public function start(array $manifest, ?int $initiatedBy = null): UpdateHistory
    {
        $this->gate($manifest);

        $version = (string) ($manifest['version'] ?? 'unknown');
        app(BackupLock::class)->acquire('update:'.$version);

        try {
            $this->enterMaintenance();

            $history = UpdateHistory::create([
                'from_version' => app(UpdateChecker::class)->currentVersion(),
                'to_version'   => $version,
                'channel'      => (string) ($manifest['channel'] ?? config('updates.channel', 'stable')),
                'status'       => UpdateHistory::STATUS_BACKING_UP,
                'step'         => $this->steps()[0],
                'initiated_by' => $initiatedBy,
                'started_at'   => now(),
                'meta'         => ['manifest' => $manifest, 'step_index' => 0],
            ]);

            // Open the recovery window: arm the app-independent Recovery Console for
            // the duration of the apply (auto-disarmed on success / rollback, Chunk 4.1).
            $this->armRecovery($history);

            Log::channel(config('updates.log_channel', 'stack'))->notice('update.start', [
                'history' => $history->getKey(), 'to' => $version, 'admin' => $initiatedBy,
            ]);

            return $history;
        } catch (\Throwable $e) {
            $this->exitMaintenance();
            app(BackupLock::class)->release();
            throw $e;
        }
    }

    /** Perform ONE step (one poll). Returns the refreshed history. */
    public function advance(UpdateHistory $history): UpdateHistory
    {
        if ($history->isTerminal()) {
            return $history;
        }

        $steps = $this->steps();
        $index = $history->stepIndex();

        if ($index >= count($steps)) {
            return $this->complete($history);
        }

        $step = $steps[$index];

        try {
            $this->{'do'.Str::studly($step)}($history);
        } catch (\Throwable $e) {
            return $this->fail($history, $step, $e->getMessage());
        }

        $next = $index + 1;
        $history->setStepIndex($next);
        $history->step   = $steps[$next] ?? 'complete';
        $history->status = $this->statusFor($history->step);
        $history->save();

        return $history;
    }

    /** Drive to a terminal state (CLI / sync / tests). The web UI polls advance(). */
    public function run(UpdateHistory $history): UpdateHistory
    {
        $guard = 0;
        while (! $history->isTerminal() && $guard++ < 100) {
            $this->advance($history->refresh());
        }

        return $history->refresh();
    }

    /* ---- Steps (overridable) ------------------------------------------- */

    /** The step list for THIS install — git-managed installs get GIT_STEPS. */
    protected function steps(): array
    {
        return $this->isGitMode() ? self::GIT_STEPS : self::STEPS;
    }

    protected function isGitMode(): bool
    {
        return app(GitUpdater::class)->isGitManaged();
    }

    protected function gate(array $manifest): void
    {
        $report = app(PreflightService::class)->run($manifest);
        if (! $report->canProceed()) {
            $first = $report->failures()[0] ?? null;
            throw new UpdateException('Pre-flight failed: '.($first?->message ?? 'update cannot proceed.'));
        }
    }

    protected function doBackup(UpdateHistory $history): void
    {
        // The updater already owns the shared lock — don't re-acquire it (rule #48).
        $run = app(BackupManager::class)->start(
            BackupRun::PROFILE_UPDATE_SAFETY, BackupRun::TRIGGER_PRE_UPDATE, [], acquireLock: false
        );
        $run = app(BackupManager::class)->run($run);

        if ($run->status !== BackupRun::STATUS_SUCCESS) {
            throw new UpdateException('Pre-update backup failed: '.$run->error);
        }

        $history->backup_run_id = $run->getKey();
        $history->save();
    }

    protected function doDownload(UpdateHistory $history): void
    {
        $path = app(UpdateDownloader::class)->download($history->manifest());
        $history->putMeta('download_path', $path);
        $history->save();
    }

    protected function doExtract(UpdateHistory $history): void
    {
        $dir = app(UpdateExtractor::class)->extract(
            (string) ($history->meta['download_path'] ?? ''), $history->to_version
        );
        $history->putMeta('staging_dir', $dir);
        $history->save();
    }

    protected function doSwap(UpdateHistory $history): void
    {
        $map = app(UpdateSwapper::class)->swap(
            (string) ($history->meta['staging_dir'] ?? ''), $history->to_version
        );
        $history->putMeta('swap_completed', (bool) ($map['completed'] ?? false));
        $history->save();
    }

    /** Git path only (GIT_STEPS): fetch + check out the target release's tag. */
    protected function doGitCheckout(UpdateHistory $history): void
    {
        $manifest = $history->manifest();
        app(GitUpdater::class)->checkout($history->to_version, $manifest['git_commit_sha'] ?? null);
        $history->putMeta('git_checkout_completed', true);
        $history->save();
    }

    /** Git path only (GIT_STEPS): install the new tag's dependencies. */
    protected function doComposerInstall(UpdateHistory $history): void
    {
        app(GitUpdater::class)->composerInstall();
        $history->putMeta('composer_install_completed', true);
        $history->save();
    }

    protected function doFinalize(UpdateHistory $history): void
    {
        $report = app(UpdateFinalizer::class)->run(); // migrate --force is critical → throws on fail
        $history->putMeta('finalize', $report->toArray());
        $history->save();
    }

    protected function doVerify(UpdateHistory $history): void
    {
        // Post-up verification (Chunk 3.6): schema + referential + smoke. Any
        // failure throws → fail() rolls back (needsRollback('verify') is true).
        $report = app(PostUpdateVerifier::class)->verify();
        $history->putMeta('verify', $report->toArray());
        $history->save();

        if (! $report->ok()) {
            throw new UpdateException('Post-update verification failed: '.$report->firstFailure());
        }
    }

    protected function complete(UpdateHistory $history): UpdateHistory
    {
        $history->status      = UpdateHistory::STATUS_SUCCESS;
        $history->step        = 'complete';
        $history->finished_at = now();
        $history->save();

        $this->exitMaintenance();
        $this->disarmRecovery(); // install is known-good → close the recovery window
        app(BackupLock::class)->release();

        Log::channel(config('updates.log_channel', 'stack'))->notice('update.success', [
            'history' => $history->getKey(), 'to' => $history->to_version,
        ]);

        return $history;
    }

    /* ---- Failure + rollback -------------------------------------------- */

    protected function fail(UpdateHistory $history, string $step, string $error): UpdateHistory
    {
        Log::channel(config('updates.log_channel', 'stack'))
            ->error('Update apply failed at ['.$step.']: '.$error);

        $rolledBack = false;
        if ($this->needsRollback($step)) {
            // rollback() now reports whether it actually succeeded — previously
            // this was hardcoded true the moment rollback() was attempted, so a
            // rollback that itself failed (e.g. git checkout to an unresolvable
            // tag) was still reported and treated as a successful rollback,
            // which disarms the Recovery Console below at exactly the moment an
            // operator needs it most.
            $rolledBack = $this->rollback($history);
        }

        $history->status      = $rolledBack ? UpdateHistory::STATUS_ROLLED_BACK : UpdateHistory::STATUS_FAILED;
        $history->error       = Str::limit('['.$step.'] '.$error, 2000, '');
        $history->finished_at = now();
        $history->save();

        $this->exitMaintenance();
        // A completed rollback restored a known-good install → close the recovery
        // window. A hard failure (no rollback) may have left the app unbootable, so
        // KEEP it armed — that is exactly when an operator needs the console (rule #47).
        if ($rolledBack) {
            $this->disarmRecovery();
        }
        app(BackupLock::class)->release();

        return $history;
    }

    /**
     * A failure after the swap (or, git path, after composer install) must
     * reverse files + restore the DB.
     *
     * @return bool true only if BOTH the file rollback and the DB restore
     *              actually succeeded — the caller uses this to decide
     *              whether the install is genuinely back to a known-good
     *              state (and so safe to disarm the Recovery Console), not
     *              just whether a rollback was attempted.
     */
    protected function rollback(UpdateHistory $history): bool
    {
        $filesOk = true;

        try {
            if ($this->isGitMode()) {
                // Check out the PREVIOUS tag and reinstall ITS dependencies — a
                // composer-install failure partway through must never leave
                // vendor/ matching neither the old nor the new release.
                app(GitUpdater::class)->rollbackTo($history->from_version);
            } else {
                app(UpdateSwapper::class)->rollback(); // reverse the dir-rename swap (reads last-swap.json)
            }
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))->error('File rollback failed: '.$e->getMessage());
            $filesOk = false;
        }

        $dbOk = true;

        if ($history->backup_run_id && ($run = BackupRun::find($history->backup_run_id))) {
            try {
                app(RestoreManager::class)->restore($run, RestoreOptions::databaseOnly());
            } catch (\Throwable $e) {
                Log::channel(config('updates.log_channel', 'stack'))->error('DB restore during rollback failed: '.$e->getMessage());
                $dbOk = false;
            }
        }

        return $filesOk && $dbOk;
    }

    protected function needsRollback(string $step): bool
    {
        if ($this->isGitMode()) {
            // composer_install can fail partway through (network drop mid-install)
            // and leave vendor/ inconsistent, unlike the zip path's atomic
            // per-directory rename — so it needs the same rollback as a
            // post-swap failure, one step earlier than the zip path's list.
            return in_array($step, ['composer_install', 'finalize', 'verify'], true);
        }

        // Only steps AFTER a successful swap require reversing files + DB.
        return in_array($step, ['finalize', 'verify'], true);
    }

    /* ---- Maintenance --------------------------------------------------- */

    protected function enterMaintenance(): void
    {
        app(SettingsService::class)->set('maintenance.enabled', true);
    }

    protected function exitMaintenance(): void
    {
        app(SettingsService::class)->set('maintenance.enabled', false);
    }

    /* ---- Recovery window (arm-flag lifecycle, Chunk 4.1) --------------- */

    protected function armRecovery(UpdateHistory $history): void
    {
        // deployment_type lets the Recovery Console (public/oe-recovery.php)
        // tell a git-managed install apart from a zip-swap one. Its only
        // destructive action, rollback_files, is zip-swap-specific (reads
        // last-swap.json, which a git-managed update never writes) — without
        // this, the console silently reported "No interrupted file swap to
        // roll back" for a genuinely broken git-mode update, reading as a
        // false all-clear instead of the truth: there's nothing this
        // particular action can do for THIS deployment type, here's what to
        // do manually instead.
        app(RecoveryWindowFlag::class)->arm([
            'history_id'      => $history->getKey(),
            'from_version'    => $history->from_version,
            'to_version'      => $history->to_version,
            'deployment_type' => $this->isGitMode() ? 'git' : 'zip',
        ]);
    }

    protected function disarmRecovery(): void
    {
        app(RecoveryWindowFlag::class)->disarm();
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function statusFor(string $step): string
    {
        return match ($step) {
            'backup'           => UpdateHistory::STATUS_BACKING_UP,
            'download'         => UpdateHistory::STATUS_DOWNLOADING,
            'extract'          => UpdateHistory::STATUS_EXTRACTING,
            'swap'             => UpdateHistory::STATUS_SWAPPING,
            'git_checkout'     => UpdateHistory::STATUS_PULLING,
            'composer_install' => UpdateHistory::STATUS_INSTALLING,
            'finalize'         => UpdateHistory::STATUS_MIGRATING,
            'verify'           => UpdateHistory::STATUS_FINALIZING,
            default            => UpdateHistory::STATUS_FINALIZING,
        };
    }

    private function estimateEta(array $manifest): int
    {
        $migrations = (int) ($manifest['migration_count'] ?? 0) * 3;

        if ($this->isGitMode()) {
            // git fetch is typically fast (a tag's diff, not a full clone), but
            // `composer install` over the network is comfortably the slowest
            // step of this path (its own timeout is 300s) — this was never
            // accounted for, so the "don't close this window" progress screen
            // showed an estimate based purely on zip-download bandwidth for
            // every git-mode update, regardless of how long composer actually
            // took.
            return 60 + 45 /* git fetch/checkout */ + 90 /* typical composer install */ + $migrations;
        }

        // Zip path: download (assume ~2 MB/s) + a fixed backup/migrate/swap budget.
        $size = (int) ($manifest['size_bytes'] ?? 0);
        $download = $size > 0 ? (int) ceil($size / (2 * 1024 * 1024)) : 30;

        return 60 + $download + $migrations; // seconds
    }
}
