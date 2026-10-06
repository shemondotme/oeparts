<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\AdminPushDelivery;
use App\Services\Push\WebPushClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one push payload to every device an admin has registered, then
 * records the outcome per device and drops subscriptions the push service says
 * are gone (HTTP 404/410) or that have failed repeatedly.
 *
 * NotifyAdminsOnJobFailure deliberately ignores this job: a failure here must
 * not itself raise another notification (and so another push).
 */
class SendAdminWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /** A device failing this many pushes in a row is considered dead. */
    public const MAX_CONSECUTIVE_FAILURES = 5;

    public function __construct(
        public readonly int $adminId,
        public readonly array $payload,
        public readonly ?int $onlySubscriptionId = null,
    ) {}

    public function backoff(): array
    {
        return [30];
    }

    public function handle(WebPushClient $client): void
    {
        $admin = Admin::find($this->adminId);

        if (! $admin) {
            return;
        }

        $subscriptions = $admin->pushSubscriptions()
            ->when($this->onlySubscriptionId, fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $results = $client->send($subscriptions, $this->payload, (bool) ($this->payload['urgent'] ?? false));

        foreach ($subscriptions as $subscription) {
            $result = $results[$subscription->id] ?? ['ok' => false, 'expired' => false, 'status' => null, 'error' => 'no result'];

            if ($result['ok']) {
                $subscription->forceFill(['last_success_at' => now(), 'failure_count' => 0])->save();
                $status = 'sent';
            } elseif ($result['expired']) {
                $subscription->delete();
                $status = 'expired';
            } else {
                $subscription->forceFill([
                    'last_failure_at' => now(),
                    'failure_count' => $subscription->failure_count + 1,
                ])->save();
                $status = 'failed';

                Log::warning('Admin web push failed', [
                    'admin' => $this->adminId,
                    'subscription' => $subscription->id,
                    'status' => $result['status'],
                    'error' => $result['error'],
                ]);

                if ($subscription->failure_count >= self::MAX_CONSECUTIVE_FAILURES) {
                    $subscription->delete();
                }
            }

            AdminPushDelivery::create([
                'admin_id' => $this->adminId,
                'subscription_id' => $subscription->id,
                'topic' => $this->payload['topic'] ?? null,
                'status' => $status,
                'http_status' => $result['status'],
                'error' => $result['error'],
            ]);
        }
    }
}
