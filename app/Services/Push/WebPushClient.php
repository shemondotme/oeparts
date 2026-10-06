<?php

namespace App\Services\Push;

use App\Models\AdminPushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Thin seam around minishlink/web-push so tests can swap the network call out
 * (bind a fake to this class) while production talks to the real push services.
 */
class WebPushClient
{
    public function __construct(private readonly VapidKeys $vapid) {}

    /**
     * @param  Collection<int, AdminPushSubscription>  $subscriptions
     * @return array<int, array{ok: bool, expired: bool, status: ?int, error: ?string}> keyed by subscription id
     */
    public function send(Collection $subscriptions, array $payload, bool $urgent): array
    {
        $webPush = new WebPush(
            ['VAPID' => $this->vapid->forWebPush()],
            ['TTL' => 86400, 'urgency' => $urgent ? 'high' : 'normal'],
            // Guzzle as the PSR-18 client so a hung push service cannot stall a queue worker.
            new Client(['timeout' => 15, 'connect_timeout' => 5]),
        );

        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription->id;

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }

        $results = [];

        foreach ($webPush->flush() as $report) {
            $id = $byEndpoint[$report->getEndpoint()] ?? null;

            if ($id === null) {
                continue;
            }

            $results[$id] = [
                'ok' => $report->isSuccess(),
                'expired' => $report->isSubscriptionExpired(),
                'status' => $report->getResponse()?->getStatusCode(),
                'error' => $report->isSuccess() ? null : mb_substr((string) $report->getReason(), 0, 250),
            ];
        }

        return $results;
    }
}
