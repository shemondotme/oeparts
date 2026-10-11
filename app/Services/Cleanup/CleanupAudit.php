<?php

namespace App\Services\Cleanup;

use App\Models\ActivityLog;
use App\Models\CleanupRun;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The audit trail of the Cleanup Engine: one Activity Log row per run START, FINISH and
 * CANCEL (plus guard aborts and settings changes) — deliberately NOT one per chunk, so the
 * activity log itself cannot be flooded by the thing that is meant to keep logs small.
 *
 * Everything is mirrored to the dedicated `cleanup` log channel with the full numbers.
 * An audit failure is logged but never allowed to break or abort a cleanup.
 */
final class CleanupAudit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, ?CleanupRun $run, array $context = [], ?int $adminId = null): void
    {
        $context = array_filter($context, fn ($v) => $v !== null);

        Log::channel('cleanup')->info('cleanup.'.$action, ['run_id' => $run?->getKey()] + $context);

        try {
            ActivityLog::create([
                'admin_id' => $adminId ?? ($run ? $run->admin_id : null) ?? auth('admin')->id(),
                'action' => 'cleanup.'.$action,
                'model_type' => CleanupRun::class,
                'model_id' => $run?->getKey(),
                'old_values' => [],
                'new_values' => $context + ($run ? array_filter([
                    'target' => $run->target,
                    'mode' => $run->mode,
                    'trigger' => $run->trigger,
                ]) : []),
                // Scheduled / queued actors have no request; ip_address is NOT NULL.
                'ip_address' => request()->ip() ?? '0.0.0.0',
            ]);
        } catch (Throwable $e) {
            Log::warning('Cleanup audit row could not be written: '.$e->getMessage(), ['action' => $action]);
        }
    }
}
