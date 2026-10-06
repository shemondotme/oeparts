<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminPushDeferred;
use App\Models\AdminPushDelivery;
use App\Models\AdminPushPreference;
use App\Services\Push\AdminPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Delivers the alerts that were held back during an admin's quiet hours as a
 * single summary push once those hours end, then prunes old bookkeeping rows.
 * Runs every minute from routes/console.php (cheap: one indexed query when
 * nothing is waiting).
 */
class AdminPushFlushDeferred extends Command
{
    protected $signature = 'oeparts:admin-push:flush';

    protected $description = 'Send quiet-hours digests to admin devices and prune old push bookkeeping';

    public function handle(AdminPushService $push): int
    {
        $adminIds = AdminPushDeferred::query()->distinct()->pluck('admin_id');

        foreach ($adminIds as $adminId) {
            try {
                $this->flushFor((int) $adminId, $push);
            } catch (\Throwable $e) {
                Log::error('AdminPushFlushDeferred failed for an admin', ['admin' => $adminId, 'error' => $e->getMessage()]);
            }
        }

        // Anything still queued after two days is stale news — drop rather than
        // deliver an "order placed" alert days late.
        AdminPushDeferred::query()->where('created_at', '<', now()->subDays(2))->delete();
        AdminPushDelivery::query()->where('created_at', '<', now()->subDays(30))->delete();

        return self::SUCCESS;
    }

    private function flushFor(int $adminId, AdminPushService $push): void
    {
        $admin = Admin::find($adminId);

        if (! $admin || ! $admin->is_active) {
            AdminPushDeferred::query()->where('admin_id', $adminId)->delete();

            return;
        }

        $pref = AdminPushPreference::forAdmin($admin);

        if ($pref->quiet_enabled && $push->inQuietHours($pref)) {
            return; // Still quiet — leave everything queued.
        }

        $rows = AdminPushDeferred::query()->where('admin_id', $adminId)->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return;
        }

        if ($pref->push_enabled && $admin->pushSubscriptions()->exists()) {
            $hide = $pref->hide_details ?? $push->hideDetailsByDefault();

            if ($rows->count() === 1) {
                $only = $rows->first();
                $payload = [
                    'topic' => $only->topic,
                    'title' => $only->title,
                    'body' => $hide ? __('push.hidden_body') : (string) $only->body,
                    'url' => $only->url ?: url('/admin'),
                ];
            } else {
                $titles = $rows->pluck('title')->unique()->take(3)->implode(' · ');
                $extra = $rows->count() - 3;

                $payload = [
                    'topic' => 'digest',
                    'title' => __('push.digest_title', ['count' => $rows->count()]),
                    'body' => $hide ? __('push.hidden_body') : $titles.($extra > 0 ? ' '.__('push.digest_more', ['count' => $extra]) : ''),
                    'url' => url('/admin'),
                ];
            }

            $push->queue($admin, $payload + [
                'id' => null,
                'tag' => 'digest',
                'topicLabel' => 'Summary',
                'urgent' => false,
                'sound' => (bool) $pref->sound_enabled,
            ]);
        }

        AdminPushDeferred::query()->whereIn('id', $rows->pluck('id'))->delete();
    }
}
