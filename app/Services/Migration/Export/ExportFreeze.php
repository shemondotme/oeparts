<?php

namespace App\Services\Migration\Export;

use App\Models\Setting;
use App\Services\Instance\BlockLevel;
use App\Services\Instance\Contracts\BlockSource;
use App\Services\Migration\MigrationQueues;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Log;

/**
 * Holds the shop still while a freeze export copies it, and puts everything back afterwards.
 *
 * Engaging: the freeze record is written FIRST, remembering the operator's own maintenance setting, so
 * that if the process dies half way the record already says how to undo what was done; then maintenance
 * mode is switched on, and the CUSTOMER queues are paused on every real queue connection (the dedicated
 * `migration` queue never is, or the export that the freeze is for could not run). It is also the first
 * BlockSource the SystemGate knows: while a freeze is live only the scheduled commands that keep the
 * installation healthy run.
 *
 * Releasing restores the maintenance setting to exactly what it was (never simply "off"), resumes the
 * queues and removes the record. It is idempotent and is called from four places that must agree: the
 * driver when the export ends, the watchdog and the next web request when a deadline has passed, and the
 * operator's unfreeze command.
 */
final class ExportFreeze implements BlockSource
{
    private const SETTING_GROUP = 'maintenance';

    private const SETTING_KEY = 'enabled';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly QueueManager $queues,
    ) {}

    // ---- BlockSource ------------------------------------------------------------------------------

    public function level(): BlockLevel
    {
        $state = FreezeState::read();

        return $state !== null && $state->isLive() ? BlockLevel::Everything : BlockLevel::None;
    }

    public function reason(): string
    {
        return 'an export is holding the site still';
    }

    // ---- engage / hold / release ---------------------------------------------------------------------

    /**
     * Put the shop in maintenance for run `$runId`, for at most `$capMinutes`.
     */
    public function engage(int $runId, int $capMinutes): FreezeState
    {
        $existing = FreezeState::read();
        if ($existing !== null && $existing->isLive()) {
            if ($existing->belongsToRun($runId)) {
                return $existing; // the driver stepped again after a crash: already frozen
            }

            // One shared lock means one export at a time; a second freeze on top of a live one would lose the first's
            // record of the operator's maintenance setting. Refuse rather than guess.
            throw new \LogicException("The site is already frozen by export {$existing->runId}.");
        }

        $now = CarbonImmutable::now();
        $state = FreezeState::begin($runId, $now, $now->addMinutes(max(1, $capMinutes)), $this->currentMaintenance(), []);
        $state->write(); // the record first: it is what undoes everything below

        $this->settings->set(self::SETTING_GROUP.'.'.self::SETTING_KEY, true);

        $paused = $this->pauseCustomerQueues($state->currentDeadline() ?? $now->addMinutes($capMinutes));
        $state = FreezeState::begin($runId, $now, $state->exportDeadlineAt, $state->previousMaintenance, $paused);
        $state->write();

        Log::channel('migration')->warning('Export freeze engaged.', ['run' => $runId, 'cap_minutes' => $capMinutes, 'paused_queues' => $paused]);

        return $state;
    }

    /** The package is sealed: the cap of the export phase gives way to the hold deadline (null = until lifted by hand). */
    public function hold(?int $holdMinutes): ?FreezeState
    {
        $state = FreezeState::read();

        if ($state === null) {
            return null;
        }

        $held = $state->toHold($holdMinutes === null || $holdMinutes <= 0 ? null : CarbonImmutable::now()->addMinutes($holdMinutes));
        $held->write();

        return $held;
    }

    /**
     * Lift the freeze. Returns false when there was none (so every caller may call it without checking).
     */
    public function release(string $reason): bool
    {
        $state = FreezeState::read();

        if ($state === null) {
            $this->removeFiles();

            return false;
        }

        $this->restoreMaintenance($state->previousMaintenance);
        $this->resumeQueues($state->pausedQueues);
        $this->removeFiles();

        Log::channel('migration')->warning('Export freeze released.', ['run' => $state->runId, 'reason' => $reason]);

        return true;
    }

    /**
     * Renew the pause on the customer queues of a live freeze. The pause lives in the cache, which a flush (a
     * deploy, `cache:clear`, a Redis restart) would silently empty: the watchdog calls this every few minutes.
     */
    public function refreshQueuePauses(): void
    {
        $state = FreezeState::read();

        if ($state === null || ! $state->isLive()) {
            return;
        }

        $deadline = $state->currentDeadline();
        $seconds = ($deadline === null ? 86400 : max(60, $deadline->getTimestamp() - time())) + 600;

        foreach ($state->pausedQueues as $pair) {
            [$connection, $queue] = explode(':', $pair, 2);
            $this->queues->pauseFor($connection, $queue, $seconds);
        }
    }

    /** True when a freeze record exists whose deadline has passed: the caller should release it. */
    public function isExpired(): bool
    {
        $state = FreezeState::read();

        return $state !== null && ! $state->isLive();
    }

    // ---- maintenance ----------------------------------------------------------------------------------

    /** @return array{present: bool, enabled: int|null} */
    private function currentMaintenance(): array
    {
        $row = Setting::query()->where('group', self::SETTING_GROUP)->where('key', self::SETTING_KEY)->first();

        return $row === null
            ? ['present' => false, 'enabled' => null]
            : ['present' => true, 'enabled' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0];
    }

    /** @param  array{present: bool, enabled: int|null}  $previous */
    private function restoreMaintenance(array $previous): void
    {
        if ($previous['present']) {
            $this->settings->set(self::SETTING_GROUP.'.'.self::SETTING_KEY, (bool) $previous['enabled']);

            return;
        }

        // The setting did not exist before the freeze: leave it as it was found.
        Setting::query()->where('group', self::SETTING_GROUP)->where('key', self::SETTING_KEY)->delete();
        $this->settings->forget(self::SETTING_GROUP);
    }

    // ---- queues ----------------------------------------------------------------------------------------

    /** @return array<int, string> "connection:queue" pairs that were paused */
    private function pauseCustomerQueues(\DateTimeInterface $until): array
    {
        $seconds = max(60, $until->getTimestamp() - time()) + 600;
        $paused = [];

        foreach ($this->realConnections() as $connection) {
            foreach ($this->customerQueues() as $queue) {
                $this->queues->pauseFor($connection, $queue, $seconds);
                $paused[] = $connection.':'.$queue;
            }
        }

        return $paused;
    }

    /** @param  array<int, string>  $paused */
    private function resumeQueues(array $paused): void
    {
        foreach ($paused as $pair) {
            [$connection, $queue] = explode(':', $pair, 2);
            $this->queues->resume($connection, $queue);
        }
    }

    /** @return array<int, string> the queue connections a worker can serve (not sync, not null) */
    private function realConnections(): array
    {
        $names = [];

        foreach ((array) config('queue.connections', []) as $name => $config) {
            if (! in_array($config['driver'] ?? null, ['sync', 'null', null], true)) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /** @return array<int, string> the queues of customer-facing work; never the dedicated migration queue */
    private function customerQueues(): array
    {
        return MigrationQueues::customer();
    }

    private function removeFiles(): void
    {
        $path = FreezeState::file()->path();

        foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
