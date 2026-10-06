<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminPushPreference;
use App\Models\AdminPushSubscription;
use App\Services\Push\AdminPushService;
use App\Services\Push\PushTopicRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Endpoints behind the installable admin app: service worker + manifest (public,
 * they carry no secrets) and the JSON API the browser script uses to register a
 * device, poll while push is unavailable, send a test, and mark-as-read from a
 * notification button.
 */
class AdminPushController extends Controller
{
    public function serviceWorker(): Response
    {
        $source = file_get_contents(public_path('admin-push/sw.js')) ?: '';

        return response($source, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // Served from /admin/sw.js (default scope /admin/); widened to '/admin' so the
            // dashboard URL itself (/admin, no trailing slash) is inside the scope too.
            'Service-Worker-Allowed' => '/admin',
            'Cache-Control' => 'no-cache, max-age=0',
        ]);
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'id' => '/admin',
            'name' => settings('general.site_name', 'OeParts').' Admin',
            'short_name' => 'OE Admin',
            'description' => 'Store administration and live alerts.',
            'start_url' => '/admin',
            'scope' => '/admin',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => '#0B1A29',
            'background_color' => '#0B1A29',
            'icons' => [
                ['src' => '/admin-push/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/admin-push/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/admin-push/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'])
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
            'deviceLabel' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $userAgent = (string) $request->userAgent();

        // A browser profile is one subscription: if someone else logged in on the
        // same browser earlier, it now belongs to whoever registers it last.
        $subscription = AdminPushSubscription::updateOrCreate(
            ['endpoint_hash' => AdminPushSubscription::hashEndpoint($data['endpoint'])],
            [
                'admin_id' => $admin->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => Str::limit($userAgent, 250, ''),
                'device_label' => $data['deviceLabel'] ?? $this->describeDevice($userAgent),
                'failure_count' => 0,
            ],
        );

        AdminPushPreference::forAdmin($admin);

        return response()->json(['ok' => true, 'id' => $subscription->id]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        AdminPushSubscription::query()
            ->where('admin_id', $request->user('admin')->id)
            ->where('endpoint_hash', AdminPushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Send a real push to one of the admin's own devices, synchronously. */
    public function test(Request $request, AdminPushService $push): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => ['nullable', 'integer'],
            'endpoint' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Admin $admin */
        $admin = $request->user('admin');

        $query = $admin->pushSubscriptions();

        if (! empty($data['subscription_id'])) {
            $query->whereKey($data['subscription_id']);
        } elseif (! empty($data['endpoint'])) {
            $query->where('endpoint_hash', AdminPushSubscription::hashEndpoint($data['endpoint']));
        }

        $subscription = $query->first();

        // A specific device was asked for but is not this admin's: say so, don't
        // quietly test some other device of theirs instead.
        $result = $subscription || (empty($data['subscription_id']) && empty($data['endpoint']))
            ? $push->sendTest($admin, $subscription)
            : ['ok' => false, 'no_device' => true, 'message' => __('push.test_no_device')];

        $status = $result['ok'] ? 200 : (($result['no_device'] ?? false) ? 422 : 502);

        return response()->json($result, $status);
    }

    /**
     * Fallback for browsers/devices with no push subscription: lets the open admin
     * page learn about new bell notifications (to play the sound / show an
     * in-page alert) and keeps the app-icon badge count fresh.
     */
    public function poll(Request $request, AdminPushService $push, PushTopicRegistry $topics): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $pref = AdminPushPreference::forAdmin($admin);

        $cursor = $request->query('cursor');
        $now = now();
        $items = [];

        if (is_string($cursor) && $cursor !== '') {
            try {
                $since = Carbon::parse($cursor);
            } catch (\Throwable) {
                $since = null;
            }

            if ($since) {
                $items = $admin->notifications()
                    ->where('data->format', 'filament')
                    ->where('created_at', '>', $since)
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(function ($n) use ($topics, $pref) {
                        $data = (array) $n->data;
                        $topic = $topics->find($data['viewData']['push_topic'] ?? $data['push_topic'] ?? null);

                        return [
                            'id' => $n->id,
                            'topic' => $topic->key,
                            'title' => Str::limit(strip_tags((string) ($data['title'] ?? '')), 120),
                            'body' => Str::limit(strip_tags((string) ($data['body'] ?? $data['detail'] ?? '')), 200),
                            'url' => collect($data['actions'] ?? [])->pluck('url')->filter()->first() ?? ($data['action_url'] ?? null),
                            'sound' => $topic->sound_enabled && $pref->sound_enabled,
                            'wanted' => $topic->push_enabled && $pref->topicOverride($topic->key) !== false,
                        ];
                    })
                    ->filter(fn (array $item) => $item['wanted'])
                    ->values()
                    ->all();
            }
        }

        return response()->json([
            'cursor' => $now->toIso8601String(),
            'unread' => $push->unreadCount($admin),
            'items' => $items,
            'sound' => (bool) $pref->sound_enabled,
        ]);
    }

    /**
     * "Mark as read" button inside a native notification. The service worker
     * cannot hold a CSRF token, so the URL itself is the credential: it is signed,
     * short-lived, and bound to one admin + one notification.
     */
    public function markRead(Request $request, int $admin, string $id): JsonResponse
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $admin)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $unread = Admin::find($admin)?->unreadNotifications()->where('data->format', 'filament')->count() ?? 0;

        return response()->json(['ok' => true, 'unread' => $unread]);
    }

    private function describeDevice(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Device',
        };

        return $browser.' · '.$os;
    }
}
