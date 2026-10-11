<?php

namespace App\Services\Cleanup;

use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Services\Backup\BackupLock;
use App\Services\Backup\Exceptions\BackupLockException;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\Exceptions\CleanupBusyException;
use App\Services\Cleanup\Exceptions\CleanupException;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The Cleanup Engine's chunked, resumable finite state machine — same shape as
 * App\Services\Backup\BackupManager.
 *
 *   start()   creates a RUNNING CleanupRun + one item per target. Refuses when another cleanup is
 *             active (the run row's unique `active_key` makes that a database guarantee) or a
 *             backup / update / restore is running.
 *   advance() does ONE bounded slice of work and persists the checkpoint. Called once per
 *             wire:poll tick (UI) or in a loop by the CLI. Safe to call from any process: a
 *             kernel flock() mutex stops two drivers from advancing the same run.
 *   cancel()  sets a flag; the run finishes the current item cleanly (verify + reconcile) and ends
 *             CANCELLED with the true numbers.
 *
 * Every item moves through   plan -> guard -> delete -> verify -> (settle) -> reconcile
 * (a dry run stops after plan). The verdict comes from numbers measured independently before and
 * after the deletion — see VerificationReconciler — never from "no exception was thrown".
 *
 * No queue worker is required: progress is driven by whoever polls, and a run whose driver
 * disappears is picked up again by CleanupJanitor.
 */
class CleanupManager
{
    private const MAX_STEPS = 1_000_000;

    /** Largest checkpoint a target may persist; bigger means a target is storing data it should not. */
    private const MAX_CHECKPOINT_BYTES = 16384;

    /** A dry-run preview younger than this may be reused so "what was previewed is what is deleted". */
    private const PREVIEW_VALID_SECONDS = 1800;

    private const SETTLE_SECONDS = 3.0;

    public function __construct(
        private readonly TargetRegistry $registry,
        private readonly VerificationReconciler $reconciler,
        private readonly OverDeleteGuard $guard,
        private readonly SystemBusyGuard $busy,
        private readonly DiskUsageService $disk,
        private readonly BackupLock $lock,
        private readonly CleanupAudit $audit,
        private readonly CleanupNotifier $notifier,
        private readonly CleanupWindow $window,
    ) {}

    /* ------------------------------------------------------------------ *
     * Starting
     * ------------------------------------------------------------------ */

    /**
     * @param  list<string>  $targetKeys
     * @param  array{truncate?:bool, archive?:bool|null, preview_run_id?:int|null, resumed_from_id?:int|null, cutoffs?:array<string, CarbonInterface>}  $options
     *
     * @throws CleanupBusyException when something else owns the system right now
     * @throws CleanupException for a request that is not allowed
     * @throws \InvalidArgumentException when a key is not a registered target
     */
    public function start(
        array $targetKeys,
        string $mode = CleanupRun::MODE_EXECUTE,
        string $trigger = CleanupRun::TRIGGER_MANUAL,
        ?int $adminId = null,
        array $options = [],
    ): CleanupRun {
        $keys = array_values(array_unique($targetKeys));
        if ($keys === []) {
            throw new CleanupException('Choose at least one thing to clean.');
        }
        if (! in_array($mode, [CleanupRun::MODE_DRY_RUN, CleanupRun::MODE_EXECUTE], true)) {
            throw new CleanupException("Unknown cleanup mode '{$mode}'.");
        }

        $truncate = (bool) ($options['truncate'] ?? false);
        if ($truncate && count($keys) !== 1) {
            throw new CleanupException('"Truncate all" can only be used on one target at a time.');
        }

        /** @var list<CleanupTarget> $targets */
        $targets = [];
        foreach ($keys as $key) {
            $target = $this->registry->get($key); // throws for anything not on the allow-list
            if ($mode === CleanupRun::MODE_EXECUTE && $target->reportOnly()) {
                throw new CleanupException("'{$target->label()}' is report-only: it can be previewed but never deleted.");
            }
            if ($truncate && ! $target->truncatable()) {
                throw new CleanupException("'{$target->label()}' cannot be wiped all at once; use a retention period instead.");
            }
            $targets[] = $target;
        }

        // A preview only reads, so it may run alongside a backup or update; only a real cleanup waits for an idle system.
        if ($mode === CleanupRun::MODE_EXECUTE && ($reason = $this->busy->reason()) !== null) {
            throw new CleanupBusyException($reason.' Try again when it has finished.');
        }

        $archive = $mode === CleanupRun::MODE_EXECUTE
            && (bool) ($options['archive'] ?? settings('cleanup.export_before_delete', config('cleanup.export_before_delete', false)));

        try {
            $run = DB::transaction(function () use ($keys, $mode, $trigger, $adminId, $options, $targets, $truncate, $archive) {
                $run = CleanupRun::create([
                    'kind' => count($keys) === 1 ? CleanupRun::KIND_SINGLE : CleanupRun::KIND_BATCH,
                    'target' => count($keys) === 1 ? $keys[0] : null,
                    'mode' => $mode,
                    'trigger' => $trigger,
                    'status' => CleanupRun::STATUS_RUNNING,
                    'step' => 'planning',
                    'admin_id' => $adminId,
                    'resumed_from_id' => $options['resumed_from_id'] ?? null,
                    'preview_run_id' => $options['preview_run_id'] ?? null,
                    'started_at' => now(),
                    'active_key' => CleanupRun::ACTIVE_KEY,
                    'bytes_before' => $this->disk->usedBytes(),
                ]);

                $run->meta = ['item_index' => 0, 'truncate' => $truncate, 'archive' => $archive, 'last_progress_at' => now()->toIso8601String()];
                $run->save();

                $previewCutoffs = $this->previewCutoffs($options['preview_run_id'] ?? null);

                foreach ($targets as $target) {
                    $days = $this->registry->retentionDays($target);
                    $cutoff = $truncate
                        ? now()
                        : ($options['cutoffs'][$target->key()] ?? $previewCutoffs[$target->key()] ?? now()->subDays($days));

                    $run->items()->create([
                        'target' => $target->key(),
                        'status' => CleanupRunItem::STATUS_PENDING,
                        'cutoff' => $cutoff,
                        'retention_days' => $truncate ? 0 : $days,
                    ]);
                }

                return $run;
            });
        } catch (UniqueConstraintViolationException) {
            $active = CleanupRun::query()->active()->orderByDesc('id')->first();

            throw new CleanupBusyException(
                'Another cleanup is already in progress'.($active ? " (run #{$active->getKey()})" : '').'. Wait for it to finish.',
                $active?->getKey(),
            );
        }

        // File-family targets also take the shared lock so a backup/update cannot start underneath them.
        if (collect($targets)->contains(fn (CleanupTarget $t) => $t->needsBackupLock()) && $mode === CleanupRun::MODE_EXECUTE) {
            try {
                $this->lock->acquire($run->lockOwner());
                $run->putMeta('holds_lock', true);
                $run->save();
            } catch (BackupLockException $e) {
                $run->items()->delete();
                $run->delete(); // never started: leave no half-created run behind

                throw new CleanupBusyException('A backup, update or restore started a moment ago. Try again when it has finished.');
            }
        }

        $this->audit->record($mode === CleanupRun::MODE_DRY_RUN ? 'preview' : 'start', $run, [
            'targets' => $keys,
            'truncate' => $truncate ?: null,
            'archive' => $archive ?: null,
        ], $adminId);

        return $run;
    }

    /**
     * Start a new run that picks up what an abandoned/partial run left, reusing its frozen cutoffs
     * so the same rows are targeted.
     */
    public function resumeFrom(CleanupRun $previous, ?int $adminId = null): CleanupRun
    {
        $items = $previous->items()->where('status', '!=', CleanupRunItem::STATUS_SUCCESS)->get();
        if ($items->isEmpty()) {
            throw new CleanupException('Nothing is left to resume.');
        }

        $cutoffs = [];
        foreach ($items as $item) {
            if ($item->cutoff !== null) {
                $cutoffs[$item->target] = $item->cutoff;
            }
        }

        return $this->start(
            $items->pluck('target')->all(),
            $previous->mode,
            CleanupRun::TRIGGER_RESUME,
            $adminId,
            ['resumed_from_id' => $previous->getKey(), 'cutoffs' => $cutoffs, 'truncate' => (bool) $previous->getMetaValue('truncate', false)],
        );
    }

    /* ------------------------------------------------------------------ *
     * Driving
     * ------------------------------------------------------------------ */

    /**
     * Advance the run by one bounded slice. Never throws for a problem inside a target: that item
     * is failed and the run moves on.
     */
    public function advance(CleanupRun $run, ?float $budgetSeconds = null): CleanupProgress
    {
        if ($run->isTerminal()) {
            return CleanupProgress::fromRun($run);
        }

        // Only ONE driver may advance a given run (see BackupManager::advance for the war story):
        // a kernel flock() is released by the OS the moment its holder dies, so a killed request
        // can never leave a run stuck behind a TTL.
        $mutex = $this->acquireRunMutex($run);
        if ($mutex === false) {
            return CleanupProgress::fromRun($run);
        }

        try {
            $run->refresh(); // the winner of a previous overlap may have finished it meanwhile

            if (! $run->isTerminal()) {
                $budget = $budgetSeconds ?? (float) settings('cleanup.tick_seconds', config('cleanup.tick_seconds', 3));
                $this->advanceLocked($run, microtime(true) + max(0.5, $budget));
            }

            return CleanupProgress::fromRun($run->refresh());
        } finally {
            $this->releaseRunMutex($mutex, $run);
        }
    }

    /**
     * Drive a run until it is terminal, paused, or $maxSeconds have elapsed (CLI / tests). A run left
     * unfinished stays resumable.
     */
    public function run(CleanupRun $run, float $maxSeconds = 3600.0, ?float $tickSeconds = null): CleanupRun
    {
        $until = microtime(true) + $maxSeconds;
        $tick = $tickSeconds ?? (float) config('cleanup.cli_tick_seconds', 50);
        $steps = 0;

        while (! $run->isTerminal() && $steps++ < self::MAX_STEPS && microtime(true) < $until) {
            $this->advance($run, min($tick, max(0.5, $until - microtime(true))));
            $run->refresh();

            if ($run->status === CleanupRun::STATUS_PAUSED) {
                break; // waiting for the system to be idle / the window to open; the scheduler resumes it
            }
        }

        return $run;
    }

    /** Ask a run to stop. It finishes the current item cleanly and ends CANCELLED. */
    public function cancel(CleanupRun $run, ?int $adminId = null): void
    {
        if ($run->isTerminal() || $run->cancel_requested_at !== null) {
            return;
        }

        $run->cancel_requested_at = now();
        $run->save();

        $this->audit->record('cancel', $run, ['percent' => $run->percent, 'deleted' => $run->deleted], $adminId);
    }

    /**
     * Close a run that cannot continue (driver gone for good, unrecoverable error). A run that already
     * deleted something ends PARTIAL, one that did nothing FAILED; either way the lock and the unique key
     * are released and the run can be resumed.
     *
     * @param  list<string>  $reasons
     */
    public function abandon(CleanupRun $run, string $error, array $reasons): CleanupRun
    {
        $run->refresh();
        if ($run->isTerminal()) {
            return $run;
        }

        $this->refreshTotals($run, $run->items()->orderBy('id')->get());
        $status = $run->deleted > 0 ? CleanupRun::STATUS_PARTIAL : CleanupRun::STATUS_FAILED;

        $run->items()->whereIn('status', [CleanupRunItem::STATUS_PENDING, CleanupRunItem::STATUS_RUNNING])
            ->update(['status' => CleanupRunItem::STATUS_FAILED, 'finished_at' => now()]);

        $this->closeRun($run, $status, $reasons, $error);

        return $run;
    }

    /* ------------------------------------------------------------------ *
     * One tick
     * ------------------------------------------------------------------ */

    private function advanceLocked(CleanupRun $run, float $deadline): void
    {
        $items = $run->items()->orderBy('id')->get();

        // Busy system / closed window: wait, do not fail.
        $pause = $this->pauseReason($run);
        if ($pause !== null) {
            $this->pause($run, $pause[0], $pause[1]);

            return;
        }
        if ($run->status === CleanupRun::STATUS_PAUSED) {
            $run->status = CleanupRun::STATUS_RUNNING;
            $run->paused_reason = null;
        }

        do {
            if ($this->stepOnce($run, $items, $deadline)) {
                return; // the run reached a terminal state
            }
        } while (microtime(true) < $deadline && ! $run->isTerminal());
    }

    /**
     * Do one phase-step of the current item. Returns true when the whole run has finished.
     *
     * @param  Collection<int, CleanupRunItem>  $items
     */
    private function stepOnce(CleanupRun $run, Collection $items, float $deadline): bool
    {
        $index = (int) $run->getMetaValue('item_index', 0);

        if ($index >= $items->count()) {
            $this->finalize($run, $items);

            return true;
        }

        /** @var CleanupRunItem $item */
        $item = $items[$index];
        $cancelled = $this->cancelRequested($run);

        if ($cancelled && $item->status === CleanupRunItem::STATUS_PENDING) {
            // Cancelled before this item ever started: nothing to verify, record it as cancelled.
            $this->cancelUnstarted($run, $items, $index);
            $this->finalize($run, $items);

            return true;
        }

        $target = $this->registry->get($item->target);
        $ctx = $this->context($run, $item, $target);
        $cp = $item->checkpoint ?? [];

        try {
            if ($cp === []) {
                $item->status = CleanupRunItem::STATUS_RUNNING;
                $item->started_at = now();
                $item->fs_free_before = $this->disk->freeBytes();
                $cp = $target->begin($ctx) + ['phase' => 'plan'];
            }

            // Cancel only matters while there is still work to stop. Once an item has reached verify,
            // its deletion is complete and the truthful verdict is the real one, not "cancelled".
            if ($cancelled && ! ($cp['cancelled'] ?? false) && in_array($cp['phase'] ?? '', ['plan', 'guard', 'delete'], true)) {
                $cp['cancelled'] = true;
                // Stop deleting; still verify what is left so the numbers are true.
                $cp['phase'] = ($cp['phase'] === 'delete') ? 'verify' : 'finish';
            }

            $this->runPhase($run, $item, $target, $ctx, $cp, $deadline);
        } catch (Throwable $e) {
            Log::channel('cleanup')->error("Cleanup item '{$item->target}' failed: ".$e->getMessage(), ['run_id' => $run->getKey(), 'exception' => $e]);
            $cp['phase'] = 'finish';
            $cp['exception'] = Str::limit($e->getMessage(), 500, '');
        }

        $this->saveItem($item, $cp);

        if (($cp['phase'] ?? '') === 'finish') {
            $this->finishItem($run, $item, $target, $cp);
            $run->putMeta('item_index', $index + 1);
        }

        $this->refreshTotals($run, $items);
        $run->step = $this->stepLabel($cp, $item);
        $run->message = $this->message($target, $item, $cp);
        $run->percent = $this->percent($run, $items, $cp);
        $run->putMeta('last_progress_at', now()->toIso8601String());
        $run->save(); // heartbeat

        return false;
    }

    /** Run the phase named in the checkpoint; flips `phase` forward when it completes. */
    private function runPhase(CleanupRun $run, CleanupRunItem $item, CleanupTarget $target, CleanupContext $ctx, array &$cp, float $deadline): void
    {
        switch ($cp['phase']) {
            case 'plan':
                if ($target->plan($ctx, $cp, $deadline)) {
                    $cp['targeted'] = (int) ($cp['targeted'] ?? 0);
                    $cp['phase'] = ($ctx->dryRun || $target->reportOnly()) ? 'finish' : 'guard';
                }
                break;

            case 'guard':
                $skip = $this->guardVerdict($run, $item, $target, $ctx, $cp);
                if ($skip !== null) {
                    $cp['skip'] = $skip;
                    $cp['phase'] = 'finish';
                } else {
                    $cp['phase'] = ((int) $cp['targeted']) > 0 ? 'delete' : 'verify';
                }
                break;

            case 'delete':
                if ($target->delete($ctx, $cp, $deadline)) {
                    $cp['phase'] = 'verify';
                }
                break;

            case 'verify':
                if ($target->verify($ctx, $cp, $deadline)) {
                    $cp['phase'] = $target->kind() === 'file' ? 'settle' : 'finish';
                    $cp['settle_until'] = microtime(true) + self::SETTLE_SECONDS;
                }
                break;

            case 'settle':
                // Free-space figures lag a delete by a moment; wait a little before measuring.
                $wait = min((float) $cp['settle_until'] - microtime(true), max(0.0, $deadline - microtime(true)));
                if ($wait > 0) {
                    usleep((int) ($wait * 1_000_000));
                }
                if (microtime(true) >= (float) $cp['settle_until']) {
                    $item->fs_free_after = $this->disk->freeBytes();
                    $cp['phase'] = 'finish';
                }
                break;

            default:
                throw new RuntimeException("Unknown cleanup phase '{$cp['phase']}'.");
        }
    }

    /**
     * Decide, before anything is deleted, whether this item may proceed. Returns a skip descriptor
     * ['status' => ..., 'reasons' => [...]] or null.
     *
     * @return array{status:string, reasons:list<string>}|null
     */
    private function guardVerdict(CleanupRun $run, CleanupRunItem $item, CleanupTarget $target, CleanupContext $ctx, array $cp): ?array
    {
        // Floor: enforced here as well as in the settings form and the registry, so even a
        // direct database edit or a stale preview cutoff cannot delete inside the floor.
        if (! $run->getMetaValue('truncate', false) && $target->floorDays() > 0
            && $ctx->cutoff->greaterThan(now()->subDays($target->floorDays())->addMinute())) {
            return ['status' => CleanupRunItem::STATUS_SKIPPED_FLOOR, 'reasons' => ['below_retention_floor']];
        }

        $targeted = (int) $cp['targeted'];

        if ($ctx->archiver !== null && $targeted > 0) {
            $refusal = Archiver::refusalReason($targeted, $this->disk->freeBytes());
            if ($refusal !== null) {
                return ['status' => CleanupRunItem::STATUS_SKIPPED_GUARD, 'reasons' => [$refusal]];
            }
        }

        // A scheduled run must not wipe a table because of a bad setting; a person confirms those by hand.
        if ($run->trigger === CleanupRun::TRIGGER_SCHEDULED && ! $run->getMetaValue('truncate', false)) {
            $reason = $this->guard->evaluate(
                $targeted,
                $target->totalRows(),
                (int) settings('cleanup.max_delete_pct', config('cleanup.max_delete_pct', 50)),
            );
            if ($reason !== null) {
                $this->audit->record('guard_abort', $run, ['target' => $item->target, 'targeted' => $targeted, 'reason' => $reason]);

                return ['status' => CleanupRunItem::STATUS_SKIPPED_GUARD, 'reasons' => [$reason]];
            }
        }

        return null;
    }

    /** Turn the target's raw numbers into the item's verdict and persist everything about it. */
    private function finishItem(CleanupRun $run, CleanupRunItem $item, CleanupTarget $target, array $cp): void
    {
        $targeted = (int) ($cp['targeted'] ?? 0);
        $deleted = (int) ($cp['deleted'] ?? 0);
        $failed = (int) ($cp['failed'] ?? 0);
        $remaining = (int) ($cp['remaining'] ?? 0);

        if (isset($cp['exception'])) {
            $result = ['status' => CleanupRunItem::STATUS_FAILED, 'reasons' => ['exception'], 'external_gone' => 0];
            $item->status = $result['status'];
            $item->reasons = [...$result['reasons'], 'error: '.$cp['exception']];
            // We do not know what is left after an exception, so do not pretend we do.
            $remaining = max($remaining, $targeted - $deleted);
        } elseif (isset($cp['skip'])) {
            $item->status = $cp['skip']['status'];
            $item->reasons = $cp['skip']['reasons'];
            $remaining = $targeted; // nothing was touched
            $deleted = 0;
        } elseif ($run->isDryRun() || $target->reportOnly()) {
            $result = $this->reconciler->dryRun();
            $item->status = $result['status'];
            $item->reasons = $result['reasons'];
            $remaining = $targeted;
        } else {
            $result = $this->reconciler->reconcile(
                targeted: $targeted,
                deleted: $deleted,
                failed: $failed,
                remaining: $remaining,
                archiveRows: isset($cp['archive_rows']) && $run->getMetaValue('archive') ? (int) $cp['archive_rows'] : null,
                cancelled: (bool) ($cp['cancelled'] ?? false),
                lateRows: (int) ($cp['late_rows'] ?? 0),
            );
            $item->status = $result['status'];
            $item->reasons = $result['reasons'];
            $item->external_gone = $result['external_gone'];
        }

        $item->targeted = $targeted;
        $item->deleted = $deleted;
        $item->failed = $failed;
        $item->remaining = $remaining;
        $item->late_rows = (int) ($cp['late_rows'] ?? 0);
        $item->bytes_freed_apparent = isset($cp['bytes_apparent']) ? (int) $cp['bytes_apparent'] : null;
        $item->bytes_freed_allocated = isset($cp['bytes_allocated']) ? (int) $cp['bytes_allocated'] : null;
        $item->archive_rows = isset($cp['archive_rows']) ? (int) $cp['archive_rows'] : null;
        $item->archive_path = $cp['archive_path'] ?? null;
        $item->finished_at = now();
        $item->save();

        $this->noteFilesystemLag($item);
    }

    /** A shortfall in measured free space is a NOTE, never a failure: other processes write concurrently. */
    private function noteFilesystemLag(CleanupRunItem $item): void
    {
        $expected = (int) ($item->bytes_freed_allocated ?? $item->bytes_freed_apparent ?? 0);
        if ($item->fs_free_before === null || $item->fs_free_after === null || $expected < 100 * 1024 * 1024) {
            return;
        }

        $observed = $item->fs_free_after - $item->fs_free_before;
        if ($observed < $expected * 0.5) {
            $item->reasons = array_values(array_unique([...($item->reasons ?? []), 'fs_lag']));
            $item->save();
        }
    }

    /**
     * @param  Collection<int, CleanupRunItem>  $items
     */
    private function cancelUnstarted(CleanupRun $run, Collection $items, int $fromIndex): void
    {
        foreach ($items as $i => $item) {
            if ($i >= $fromIndex && $item->status === CleanupRunItem::STATUS_PENDING) {
                $item->status = CleanupRunItem::STATUS_CANCELLED;
                $item->reasons = [VerificationReconciler::REASON_CANCELLED];
                $item->finished_at = now();
                $item->save();
            }
        }
        $run->putMeta('item_index', $items->count());
    }

    /**
     * @param  Collection<int, CleanupRunItem>  $items
     */
    private function finalize(CleanupRun $run, Collection $items): void
    {
        $items = $run->items()->orderBy('id')->get();
        $this->refreshTotals($run, $items);

        $status = $this->reconciler->runStatus($items->pluck('status')->all());
        $reasons = $items->pluck('reasons')->flatten()->filter()->unique()->values()->all();

        $this->closeRun($run, $status, $reasons, null);
    }

    /** Terminal bookkeeping shared by finalize() and abandon(). */
    private function closeRun(CleanupRun $run, string $status, array $reasons, ?string $error): void
    {
        $run->status = $status;
        $run->step = 'finalizing';
        $run->percent = in_array($status, [CleanupRun::STATUS_SUCCESS], true) ? 100 : min(100, max(0, (int) $run->percent));
        $run->reasons = array_values(array_unique($reasons));
        $run->error = $error !== null ? Str::limit($error, 2000, '') : $run->error;
        $run->finished_at = now();
        $run->active_key = null;
        $run->paused_reason = null;
        $run->bytes_after = $this->disk->usedBytes();
        $run->message = $this->summary($run);
        $run->save();

        $this->releaseLock($run);

        $this->audit->record('finish', $run, [
            'status' => $status,
            'targeted' => $run->targeted,
            'deleted' => $run->deleted,
            'remaining' => $run->remaining,
            'bytes_freed' => $run->bytes_freed,
            'reasons' => $run->reasons,
        ]);

        $this->notifier->runFinished($run);
    }

    /* ------------------------------------------------------------------ *
     * Pausing, cancelling, locks
     * ------------------------------------------------------------------ */

    /** @return array{0:string,1:string}|null [reason code, message] */
    private function pauseReason(CleanupRun $run): ?array
    {
        if (! $run->isDryRun() && ($busy = $this->busy->reason($run->lockOwner())) !== null) {
            return ['waiting_for_system', $busy];
        }

        if ($run->trigger === CleanupRun::TRIGGER_SCHEDULED && ! $this->window->isOpen()) {
            return ['outside_window', "Outside the automatic cleanup window ({$this->window->start()}-{$this->window->end()}); will resume in the next one."];
        }

        return null;
    }

    private function pause(CleanupRun $run, string $code, string $message): void
    {
        $run->status = CleanupRun::STATUS_PAUSED;
        $run->paused_reason = $code;
        $run->message = $message;
        $run->save(); // keeps the heartbeat fresh while waiting
    }

    private function cancelRequested(CleanupRun $run): bool
    {
        return CleanupRun::query()->whereKey($run->getKey())->whereNotNull('cancel_requested_at')->exists();
    }

    private function releaseLock(CleanupRun $run): void
    {
        if (! $run->getMetaValue('holds_lock', false)) {
            return;
        }

        try {
            // Only ever release a lock that is OURS — never a backup's or an update's.
            if (($this->lock->owner()['owner'] ?? null) === $run->lockOwner()) {
                $this->lock->release();
            }
        } catch (Throwable $e) {
            Log::channel('cleanup')->warning('Could not release the cleanup lock: '.$e->getMessage(), ['run_id' => $run->getKey()]);
        }
    }

    /** @return resource|false|null */
    private function acquireRunMutex(CleanupRun $run)
    {
        $dir = storage_path('framework/cleanup');
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            Log::channel('cleanup')->warning("Cannot create {$dir}; advancing run {$run->getKey()} without a mutex.");

            return null;
        }

        $handle = @fopen($dir.DIRECTORY_SEPARATOR.'run-'.$run->getKey().'.lock', 'c');
        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        return $handle;
    }

    /** @param  resource|null  $handle */
    private function releaseRunMutex($handle, CleanupRun $run): void
    {
        if (! is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        if ($run->isTerminal()) {
            @unlink(storage_path('framework/cleanup/run-'.$run->getKey().'.lock'));
        }
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    private function context(CleanupRun $run, CleanupRunItem $item, CleanupTarget $target): CleanupContext
    {
        $archiver = $run->getMetaValue('archive', false) && $target->kind() === 'db' && ! $run->isDryRun()
            ? Archiver::forRun((int) $run->getKey(), $target->key())
            : null;

        if ($archiver !== null) {
            $item->archive_path = $archiver->path();
        }

        return new CleanupContext(
            run: $run,
            item: $item,
            cutoff: $item->cutoff ?? now(),
            retentionDays: (int) $item->retention_days,
            dryRun: $run->isDryRun(),
            truncate: (bool) $run->getMetaValue('truncate', false),
            maxChunk: max(ChunkedDeleter::MIN_CHUNK, (int) settings('cleanup.chunk_rows', config('cleanup.chunk_rows', 2000))),
            sleepMs: max(0, (int) settings('cleanup.sleep_ms', config('cleanup.sleep_ms', 50))),
            archiver: $archiver,
            cancelCheck: fn () => $this->cancelRequested($run),
        );
    }

    /**
     * Cutoffs of a recent dry-run so the executed run deletes exactly what the admin previewed.
     *
     * @return array<string, CarbonInterface>
     */
    private function previewCutoffs(?int $previewRunId): array
    {
        if ($previewRunId === null) {
            return [];
        }

        $preview = CleanupRun::query()->find($previewRunId);
        if (! $preview || ! $preview->isDryRun() || ! $preview->finished_at
            || $preview->finished_at->lt(now()->subSeconds(self::PREVIEW_VALID_SECONDS))) {
            return [];
        }

        $out = [];
        foreach ($preview->items as $item) {
            if ($item->cutoff !== null) {
                $out[$item->target] = $item->cutoff;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $cp */
    private function saveItem(CleanupRunItem $item, array $cp): void
    {
        $encoded = (string) json_encode($cp);
        if (strlen($encoded) > self::MAX_CHECKPOINT_BYTES) {
            throw new RuntimeException('Cleanup checkpoint for '.$item->target.' grew to '.strlen($encoded).' bytes; a target must keep only offsets and counters in it.');
        }

        $item->checkpoint = $cp;
        $item->save();
    }

    /**
     * @param  Collection<int, CleanupRunItem>  $items
     */
    private function refreshTotals(CleanupRun $run, Collection $items): void
    {
        $run->targeted = (int) $items->sum('targeted');
        $run->deleted = (int) $items->sum('deleted');
        $run->failed = (int) $items->sum('failed');
        $run->remaining = (int) $items->sum('remaining');

        // Items still in flight carry their numbers in the checkpoint, not yet on the row.
        foreach ($items as $item) {
            if ($item->status === CleanupRunItem::STATUS_RUNNING && is_array($item->checkpoint)) {
                $run->targeted += (int) ($item->checkpoint['targeted'] ?? 0);
                $run->deleted += (int) ($item->checkpoint['deleted'] ?? 0);
                $run->failed += (int) ($item->checkpoint['failed'] ?? 0);
            }
        }

        $freed = $items->sum(fn (CleanupRunItem $i) => (int) ($i->bytes_freed_allocated ?? $i->bytes_freed_apparent ?? 0));
        $run->bytes_freed = $items->contains(fn (CleanupRunItem $i) => $i->bytes_freed_allocated !== null || $i->bytes_freed_apparent !== null) ? (int) $freed : null;
    }

    /** @param  array<string, mixed>  $cp */
    private function stepLabel(array $cp, CleanupRunItem $item): string
    {
        return match ($cp['phase'] ?? '') {
            'plan', 'guard' => 'planning',
            'delete' => 'deleting',
            'verify' => 'verifying',
            'settle' => 'settling',
            default => $item->status === CleanupRunItem::STATUS_RUNNING ? 'deleting' : 'reconciling',
        };
    }

    /** @param  array<string, mixed>  $cp */
    private function message(CleanupTarget $target, CleanupRunItem $item, array $cp): string
    {
        $targeted = (int) ($cp['targeted'] ?? 0);
        $deleted = (int) ($cp['deleted'] ?? 0);

        return match ($cp['phase'] ?? '') {
            'plan', 'guard' => "Counting what qualifies in {$target->label()}…",
            'delete' => "Cleaning {$target->label()}: {$deleted} of {$targeted}",
            'verify' => "Verifying {$target->label()}…",
            'settle' => "Measuring freed space for {$target->label()}…",
            default => "{$target->label()}: {$item->status}",
        };
    }

    /**
     * Overall percent: finished items count fully, the current one by phase weight
     * (plan 5%, delete 5-90%, verify 90-96%, settle 96-99%).
     *
     * @param  Collection<int, CleanupRunItem>  $items
     * @param  array<string, mixed>  $cp
     */
    private function percent(CleanupRun $run, Collection $items, array $cp): int
    {
        $total = max(1, $items->count());
        $index = (int) $run->getMetaValue('item_index', 0);

        $fraction = match ($cp['phase'] ?? '') {
            'plan' => 0.02,
            'guard' => 0.05,
            'delete' => 0.05 + 0.85 * min(1.0, ((int) ($cp['deleted'] ?? 0) + (int) ($cp['failed'] ?? 0)) / max(1, (int) ($cp['targeted'] ?? 0))),
            'verify' => 0.92,
            'settle' => 0.97,
            default => 0.0, // 'finish' — the item is already counted in $index
        };

        return (int) max(0, min(99, floor((min($index, $total) + $fraction) / $total * 100)));
    }

    private function summary(CleanupRun $run): string
    {
        if ($run->isDryRun()) {
            return "Preview: {$run->targeted} item(s) would be cleaned.";
        }

        return match ($run->status) {
            CleanupRun::STATUS_SUCCESS => "Cleaned {$run->deleted} item(s); nothing left over.",
            CleanupRun::STATUS_PARTIAL => "Cleaned {$run->deleted} item(s); {$run->remaining} could not be cleaned.",
            CleanupRun::STATUS_CANCELLED => "Cancelled after cleaning {$run->deleted} item(s).",
            default => 'The cleanup failed; see the reasons below.',
        };
    }
}
