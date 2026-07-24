<?php

namespace App\Console\Commands;

use App\Jobs\NotifyAdminsOfAutoUpdate;
use App\Models\UpdateHistory;
use App\Services\Updates\UpdateApplier;
use App\Services\Updates\UpdateChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Update & Recovery System — unattended SECURITY-update auto-apply.
 *
 * Opt-in via OE_UPDATE_AUTO_SECURITY (config('updates.auto_apply_security')) —
 * automatic background updates, deliberately scoped to security-only (never
 * routine feature releases, which may carry breaking changes an operator
 * should review). Reuses UpdateApplier::start()/run() — the EXACT same
 * chunked, pre-flight-gated, backup-first, auto-rollback-on-failure FSM the
 * admin dashboard's "Apply Update" button drives (App\Filament\Pages\System\
 * SystemUpdates::startApply()) — so auto-apply inherits every existing
 * safety property instead of being a separate, less-tested code path. The
 * only things this command adds on top: the config gate, targeting
 * next_release only when UpdateChecker flags it security, and always
 * emailing super_admins the outcome — silent background updates are a
 * common complaint about auto-update systems generally, this is
 * deliberately loud instead.
 */
class AutoApplySecurityUpdate extends Command
{
    protected $signature = 'oeparts:update:auto-apply';

    protected $description = 'Automatically apply a pending SECURITY update, if auto-apply is enabled';

    public function handle(UpdateChecker $checker, UpdateApplier $applier): int
    {
        if (! (bool) config('updates.auto_apply_security', false)) {
            $this->line('Auto-apply is disabled (OE_UPDATE_AUTO_SECURITY is not set). Nothing to do.');

            return self::SUCCESS;
        }

        $status = $checker->check(force: true);

        if (! $status->reachable) {
            // Transient network issues must not make the scheduled job "fail".
            $this->warn('Update server unreachable: '.$status->error);

            return self::SUCCESS;
        }

        if (! $status->updateAvailable || ! $status->security || ! $status->nextRelease) {
            $this->info('No pending security update to auto-apply.');

            return self::SUCCESS;
        }

        $manifest = $status->nextRelease;
        $fromVersion = $status->currentVersion;
        $toVersion = (string) ($manifest['version'] ?? 'unknown');
        $startedAt = now()->toIso8601String();

        $this->warn('Auto-applying security update: '.$fromVersion.' -> '.$toVersion);
        Log::channel(config('updates.log_channel', 'stack'))
            ->notice('Scheduled auto-apply starting: '.$fromVersion.' -> '.$toVersion);

        try {
            $history = $applier->start($manifest, initiatedBy: null);
        } catch (\Throwable $e) {
            // Same gate the dashboard button hits (disk space, locks, version
            // mismatch, …) — nothing was touched, just report it.
            $this->error('Auto-apply could not start: '.$e->getMessage());
            Log::channel(config('updates.log_channel', 'stack'))
                ->error('Scheduled auto-apply failed to start: '.$e->getMessage());

            NotifyAdminsOfAutoUpdate::dispatch([
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'success' => false,
                'rolled_back' => false,
                'error' => $e->getMessage(),
                'started_at' => $startedAt,
            ]);

            return self::SUCCESS; // the update system itself is fine; nothing to retry here
        }

        $history = $applier->run($history);

        $result = [
            'from_version' => $history->from_version,
            'to_version' => $history->to_version,
            'success' => $history->isSuccessful(),
            'rolled_back' => $history->status === UpdateHistory::STATUS_ROLLED_BACK,
            'error' => $history->error,
            'started_at' => $startedAt,
        ];

        if ($result['success']) {
            $this->info('Auto-applied security update to '.$history->to_version.'.');
            Log::channel(config('updates.log_channel', 'stack'))
                ->notice('Scheduled auto-apply succeeded: '.$history->from_version.' -> '.$history->to_version);
        } else {
            $this->error('Auto-apply did not complete: '.$history->error);
            Log::channel(config('updates.log_channel', 'stack'))
                ->error('Scheduled auto-apply failed/rolled back: '.$history->error);
        }

        NotifyAdminsOfAutoUpdate::dispatch($result);

        return self::SUCCESS;
    }
}
