<?php

namespace App\Services\Updates;

use App\Models\UpdateHistory;
use Illuminate\Support\Facades\Log;

/**
 * Finishes a self-update whose file swap succeeded but which nothing is driving
 * any more.
 *
 * The apply FSM is poll-driven: the admin's browser tab calls advance() every
 * couple of seconds, and the steps after the swap (finalize = migrations,
 * verify) must run on a FRESH request that boots the NEW code (rule #46). But
 * the tab that started the update was rendered by the OLD release, and the moment
 * the swap lands its Livewire session can no longer talk to the new code — a
 * Livewire upgrade between two releases answers every further poll with
 * "419 page expired" (its release-token check), and a freshly loaded admin page
 * used to 500 until the migrations it depends on had run. Nothing then advanced
 * the update: the site sat in maintenance mode with the new code on disk and an
 * un-migrated database until the watchdog rolled it all back two hours later.
 * Found by rehearsing the real 1.0.16 -> 2.0.0 self-update: the swap step
 * answered 500, every later poll 419, and finalize never ran.
 *
 * The old release cannot be changed, but the release that just landed can drive
 * its own remaining steps. This runs from the first request that reaches the new
 * code — the dying tab's next poll, an admin reloading the page, a visitor being
 * shown the maintenance page, or the hourly watchdog — and carries the update to
 * a terminal state exactly as the UI poll would have (same advance(), same lock,
 * same failure/rollback handling), so it neither needs nor cares whether a
 * browser is still watching.
 */
class InterruptedUpdateResumer
{
    /** Steps that only ever run AFTER the swap put the new code on disk. */
    public const POST_SWAP_STEPS = ['finalize', 'verify', 'complete'];

    /** finalize -> verify -> complete, with headroom. */
    private const MAX_STEPS = 12;

    public function __construct(private readonly RecoveryWindowFlag $window) {}

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
     * The newest non-terminal update that is past the swap and has not been
     * touched for a moment. The grace period keeps this off the toes of the
     * request that has only just finished the swap (its tail is still rendering)
     * and of a healthy poller that is mid-step.
     */
    private function interrupted(): ?UpdateHistory
    {
        return UpdateHistory::query()
            ->whereNotIn('status', [
                UpdateHistory::STATUS_SUCCESS, UpdateHistory::STATUS_FAILED, UpdateHistory::STATUS_ROLLED_BACK,
            ])
            ->whereIn('step', self::POST_SWAP_STEPS)
            ->where('updated_at', '<', now()->subSeconds((int) config('updates.resume_grace_seconds', 3)))
            ->recent()
            ->first();
    }
}
