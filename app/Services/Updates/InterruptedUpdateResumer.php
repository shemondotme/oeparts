<?php

namespace App\Services\Updates;

use App\Models\UpdateHistory;
use Illuminate\Support\Facades\Log;

/**
 * Finishes a self-update whose destructive step already ran (or started
 * running) but which nothing is driving any more.
 *
 * The apply FSM is poll-driven: the admin's browser tab calls advance() every
 * couple of seconds. For a ZIP install, the steps after the swap (finalize =
 * migrations, verify) must run on a FRESH request that boots the NEW code
 * (rule #46) — but the tab that started the update was rendered by the OLD
 * release, and the moment the swap lands its Livewire session can no longer
 * talk to the new code (a Livewire upgrade between two releases answers every
 * further poll with "419 page expired", and a freshly loaded admin page used
 * to 500 until the migrations it depends on had run). Found by rehearsing the
 * real 1.0.16 -> 2.0.0 self-update: the swap step answered 500, every later
 * poll 419, and finalize never ran.
 *
 * For a GIT-managed install there's no separate "swap" moment at all —
 * `git checkout --force` IS the destructive mutation, applied file-by-file as
 * it runs (see UpdateApplier::needsRollback()). A request killed from OUTSIDE
 * PHP (a host's own request-time limit, hit by a `git fetch` over a slow link
 * to the remote) leaves no exception to catch and nothing to resume it —
 * confirmed on a real production install: the working tree ended up fully on
 * the new release with an un-migrated database, sitting broken until someone
 * noticed and finished the update by hand over SSH.
 *
 * Either way, the release that has just landed (or is already, destructively,
 * mid-landing) can drive its own remaining steps. This runs from the first
 * request that reaches it — the dying tab's next poll, an admin reloading the
 * page, a visitor being shown the maintenance page, or the hourly watchdog —
 * and carries the update to a terminal state exactly as the UI poll would
 * have (same advance(), same lock, same failure/rollback handling), so it
 * neither needs nor cares whether a browser is still watching.
 */
class InterruptedUpdateResumer
{
    /**
     * Steps that have already (potentially) mutated the live install — the same
     * boundary UpdateApplier::needsRollback() draws, plus the terminal 'complete'
     * step. Kept as a literal list rather than reflecting into the protected
     * needsRollback() method: the sets would diverge the moment one changes
     * without the other, and GitUpdateApplierTest / UpdateApplierTest already
     * pin needsRollback()'s own boundary, so a mismatch here fails loudly in
     * this class's own tests instead of silently.
     */
    private const GIT_RESUMABLE_STEPS = ['git_checkout', 'composer_install', 'finalize', 'verify', 'complete'];

    private const ZIP_RESUMABLE_STEPS = ['finalize', 'verify', 'complete'];

    /** Longest step list (git mode) plus headroom. */
    private const MAX_STEPS = 12;

    public function __construct(
        private readonly RecoveryWindowFlag $window,
        private readonly GitUpdater $gitUpdater,
    ) {}

    /** True when at least one step was actually driven. Never throws. */
    public function resume(): bool
    {
        // A bare is_file() — this runs on EVERY request, and the arm flag exists
        // only while an update window is open (written by start(), removed on
        // success or a completed rollback).
        if (! $this->window->isArmed()) {
            return false;
        }

        try {
            $history = $this->interrupted();
            if (! $history) {
                return false;
            }

            // Whoever triggered this (a tab, a visitor) may hang up while the
            // migrations run; the update must not be abandoned half-way with it.
            ignore_user_abort(true);
            @set_time_limit(0);

            $applier = app(UpdateApplier::class);
            $advanced = false;

            for ($i = 0; $i < self::MAX_STEPS; $i++) {
                $history = $history->refresh(); // another request may have moved it on
                if ($history->isTerminal()) {
                    break;
                }

                $before = $history->step;
                $history = $applier->advance($history);

                // An unmoved step means advance() lost the non-blocking lock to a
                // driver that is already running it — leave it to them.
                if (! $history->isTerminal() && $history->step === $before) {
                    break;
                }

                $advanced = true;
            }

            return $advanced;
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->error('Resuming an interrupted update failed: '.$e->getMessage(), ['exception' => $e::class]);

            return false;
        }
    }

    /**
     * The newest non-terminal update that is past this install's own destructive
     * boundary and has not been touched for a moment. The grace period keeps this
     * off the toes of the request that has only just mutated the install (its
     * tail is still rendering) and of a healthy poller that is mid-step.
     */
    private function interrupted(): ?UpdateHistory
    {
        // Mode is a property of THIS install (is there a .git dir?), not of any one
        // history row, and can't change mid-update — same resolution UpdateApplier
        // itself uses to pick the step list in the first place.
        $steps = $this->gitUpdater->isGitManaged() ? self::GIT_RESUMABLE_STEPS : self::ZIP_RESUMABLE_STEPS;

        return UpdateHistory::query()
            ->whereNotIn('status', [
                UpdateHistory::STATUS_SUCCESS, UpdateHistory::STATUS_FAILED, UpdateHistory::STATUS_ROLLED_BACK,
            ])
            ->whereIn('step', $steps)
            ->where('updated_at', '<', now()->subSeconds((int) config('updates.resume_grace_seconds', 3)))
            ->recent()
            ->first();
    }
}
