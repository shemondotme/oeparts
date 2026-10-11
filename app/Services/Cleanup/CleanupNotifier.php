<?php

namespace App\Services\Cleanup;

use App\Enums\AdminNotificationCategory;
use App\Filament\Pages\System\CleanupDashboard;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Services\AdminNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the admins when an AUTOMATIC cleanup did not finish cleanly, so a scheduled job that
 * nobody watches cannot fail silently for weeks. A manual run needs no notification: the admin
 * who started it is looking at the progress bar.
 *
 * Alerts are de-duplicated per target+status for 24 hours so a target that trips its guard every
 * night produces one notification, not thirty. Which outcomes notify is the `cleanup.notify_on`
 * setting: 'partial_failed' (default), 'failed', or 'never'.
 */
final class CleanupNotifier
{
    public function runFinished(CleanupRun $run): void
    {
        if ($run->trigger === CleanupRun::TRIGGER_MANUAL || ! $this->shouldNotify($run)) {
            return;
        }

        $scope = $run->target ?? 'batch';
        if (! Cache::add("cleanup:alert:{$scope}:{$run->status}", 1, 86400)) {
            return; // already told them in the last 24h
        }

        try {
            app(AdminNotificationService::class)->createForAll(
                AdminNotificationCategory::System,
                'Automatic cleanup '.($run->status === CleanupRun::STATUS_FAILED ? 'failed' : 'did not finish cleanly'),
                $this->detail($run),
                CleanupDashboard::getUrl(),
                ['push_topic' => 'cleanup_alert'],
            );
        } catch (Throwable $e) {
            Log::warning('Cleanup alert could not be sent: '.$e->getMessage(), ['run_id' => $run->getKey()]);
        }
    }

    private function shouldNotify(CleanupRun $run): bool
    {
        $mode = (string) settings('cleanup.notify_on', config('cleanup.notify_on', 'partial_failed'));

        return match ($mode) {
            'never' => false,
            'failed' => $run->status === CleanupRun::STATUS_FAILED,
            default => in_array($run->status, [CleanupRun::STATUS_PARTIAL, CleanupRun::STATUS_FAILED], true),
        };
    }

    private function detail(CleanupRun $run): string
    {
        $lines = [];

        foreach ($run->items()->where('status', '!=', CleanupRunItem::STATUS_SUCCESS)->get() as $item) {
            $lines[] = $item->target.': '.$item->status.($item->reasons ? ' ('.implode(', ', $item->reasons).')' : '');
        }

        $summary = "Run #{$run->getKey()} ended {$run->status}: {$run->deleted} deleted, {$run->remaining} still pending.";

        return $lines === [] ? $summary : $summary.' '.implode('; ', array_slice($lines, 0, 6));
    }
}
