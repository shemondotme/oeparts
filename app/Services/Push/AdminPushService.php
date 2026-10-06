<?php

namespace App\Services\Push;

use App\Jobs\SendAdminWebPush;
use App\Models\Admin;
use App\Models\AdminPushDeferred;
use App\Models\AdminPushDelivery;
use App\Models\AdminPushPreference;
use App\Models\AdminPushSubscription;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Turns an admin's bell notification into a browser/OS push, honouring every
 * control layer: site-wide switch -> topic switch/roles -> the admin's own
 * switches and per-topic overrides -> quiet hours (non-urgent are held back).
 *
 * Push mirrors the bell: only notifications Filament's bell actually shows
 * (data.format = 'filament') are pushed, so a device never gets an alert for
 * something the admin cannot find in the panel afterwards.
 */
class AdminPushService
{
    public function __construct(
        private readonly PushTopicRegistry $topics,
        private readonly SettingsService $settings,
    ) {}

    public function isGloballyEnabled(): bool
    {
        return filter_var($this->settings->get('push.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function hideDetailsByDefault(): bool
    {
        return filter_var($this->settings->get('push.hide_details', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Entry point from the NotificationSent listener.
     *
     * @return string outcome: sent | deferred | skipped:<reason>
     */
    public function handleStored(Admin $admin, DatabaseNotification $stored): string
    {
        $data = (array) $stored->data;

        if (($data['format'] ?? null) !== 'filament') {
            return 'skipped:not_in_bell';
        }

        return $this->deliver($admin, [
            'id' => $stored->id,
            'topic' => $data['viewData']['push_topic'] ?? $data['push_topic'] ?? PushTopicRegistry::FALLBACK,
            'title' => $this->clean($data['title'] ?? '', 120) ?: 'New notification',
            'body' => $this->clean($data['body'] ?? $data['detail'] ?? '', 200),
            'url' => $this->extractUrl($data),
        ]);
    }

    /**
     * @param  array{id?: ?string, topic: string, title: string, body?: string, url?: ?string}  $content
     */
    public function deliver(Admin $admin, array $content): string
    {
        if (! $this->isGloballyEnabled()) {
            return 'skipped:globally_off';
        }

        if (! $admin->is_active) {
            return 'skipped:inactive';
        }

        $topic = $this->topics->find($content['topic']);

        if (! $topic->push_enabled) {
            return 'skipped:topic_off';
        }

        if (! $topic->allowsRoles($admin->getRoleNames()->all())) {
            return 'skipped:role_not_allowed';
        }

        $pref = AdminPushPreference::forAdmin($admin);

        if (! $pref->push_enabled) {
            return 'skipped:admin_off';
        }

        if ($pref->topicOverride($topic->key) === false) {
            return 'skipped:admin_topic_off';
        }

        if (! $admin->pushSubscriptions()->exists()) {
            return 'skipped:no_device';
        }

        if (! $topic->isUrgent() && $this->inQuietHours($pref)) {
            AdminPushDeferred::create([
                'admin_id' => $admin->id,
                'topic' => $topic->key,
                'title' => mb_substr($content['title'], 0, 255),
                'body' => mb_substr((string) ($content['body'] ?? ''), 0, 500),
                'url' => $content['url'] ?? null,
            ]);

            return 'deferred';
        }

        $hide = $pref->hide_details ?? $this->hideDetailsByDefault();

        $this->queue($admin, [
            'id' => $content['id'] ?? null,
            'topic' => $topic->key,
            'topicLabel' => $topic->label,
            'title' => $content['title'],
            'body' => $hide ? __('push.hidden_body') : ($content['body'] ?? ''),
            'url' => $content['url'] ?: $this->adminHome(),
            'urgent' => $topic->isUrgent(),
            'sound' => $topic->sound_enabled && $pref->sound_enabled,
        ]);

        return 'sent';
    }

    /**
     * Send a real push to one of the admin's own devices right now (no queue) so
     * the result — including a push-service rejection — can be shown immediately.
     *
     * @return array{ok: bool, message: string, no_device?: bool}
     */
    public function sendTest(Admin $admin, ?AdminPushSubscription $subscription = null): array
    {
        $subscription ??= $admin->pushSubscriptions()->first();

        if (! $subscription || $subscription->admin_id !== $admin->id) {
            return ['ok' => false, 'no_device' => true, 'message' => __('push.test_no_device')];
        }

        $startedAt = now()->subSecond();

        SendAdminWebPush::dispatchSync($admin->id, [
            'id' => null,
            'topic' => 'test',
            'topicLabel' => 'Test',
            'tag' => 'push-test',
            'title' => __('push.test_title'),
            'body' => __('push.test_body'),
            'url' => $this->adminHome(),
            'urgent' => false,
            'sound' => true,
            'badge' => $this->unreadCount($admin),
            'icon' => url('/admin-push/icon-192.png'),
        ], $subscription->id);

        $delivery = AdminPushDelivery::query()
            ->where('admin_id', $admin->id)
            ->where('created_at', '>=', $startedAt)
            ->latest('id')
            ->first();

        $ok = $delivery?->status === 'sent';

        return [
            'ok' => $ok,
            'message' => $ok
                ? __('push.test_sent')
                : __('push.test_failed', ['reason' => $delivery?->error ?: $delivery?->status ?: 'unknown']),
        ];
    }

    /** Queue the actual network push for every device the admin has registered. */
    public function queue(Admin $admin, array $payload): void
    {
        $payload['tag'] = $payload['tag'] ?? $payload['topic'];
        $payload['badge'] = $this->unreadCount($admin);
        $payload['icon'] = url('/admin-push/icon-192.png');

        if (! empty($payload['id'])) {
            $payload['readUrl'] = URL::temporarySignedRoute(
                'admin.push.read',
                now()->addDays(3),
                ['admin' => $admin->id, 'id' => $payload['id']],
            );
        }

        try {
            SendAdminWebPush::dispatch($admin->id, $payload);
        } catch (\Throwable $e) {
            Log::error('AdminPushService: could not queue push', ['admin' => $admin->id, 'error' => $e->getMessage()]);
        }
    }

    public function unreadCount(Admin $admin): int
    {
        return $admin->unreadNotifications()->where('data->format', 'filament')->count();
    }

    public function inQuietHours(AdminPushPreference $pref, ?Carbon $now = null): bool
    {
        if (! $pref->quiet_enabled) {
            return false;
        }

        $timezone = (string) $this->settings->get('general.timezone', config('app.timezone', 'UTC'));

        try {
            $now = ($now ?? now())->copy()->setTimezone($timezone);
        } catch (\Throwable) {
            $now = ($now ?? now())->copy();
        }

        $current = $now->format('H:i');
        $start = $pref->quiet_start ?: '22:00';
        $end = $pref->quiet_end ?: '08:00';

        if ($start === $end) {
            return false;
        }

        // Window may wrap past midnight (22:00 -> 08:00).
        return $start < $end
            ? ($current >= $start && $current < $end)
            : ($current >= $start || $current < $end);
    }

    private function extractUrl(array $data): ?string
    {
        foreach ((array) ($data['actions'] ?? []) as $action) {
            if (is_array($action) && filled($action['url'] ?? null)) {
                return (string) $action['url'];
            }
        }

        return filled($data['action_url'] ?? null) ? $this->absolute((string) $data['action_url']) : null;
    }

    private function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    private function adminHome(): string
    {
        return url('/admin');
    }

    private function clean(mixed $value, int $limit): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return Str::limit($text, $limit);
    }
}
