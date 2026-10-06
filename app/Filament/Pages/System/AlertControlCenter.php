<?php

namespace App\Filament\Pages\System;

use App\Models\ActivityLog;
use App\Models\AdminPushDeferred;
use App\Models\AdminPushDelivery;
use App\Models\AdminPushSubscription;
use App\Models\PushTopic;
use App\Services\Push\AdminPushService;
use App\Services\Push\PushTopicRegistry;
use App\Services\SettingsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Site-wide control of admin push alerts: the master switch, the default for
 * hiding details on lock screens, and — per notification kind — whether it is
 * pushed, how urgent it is, whether it makes a sound and which roles get it.
 * Kinds nobody registered by hand appear here automatically the first time they
 * are sent (see PushTopicRegistry). Each admin then narrows this down further on
 * their own "My alerts" page.
 */
class AlertControlCenter extends Page
{
    protected static ?string $slug = 'system/alert-control';

    protected static ?string $title = 'Alert control';

    protected string $view = 'filament.pages.system.alert-control-center';

    protected ?string $subheading = 'Decide which events are pushed to admins’ phones and computers, how urgent each one is and who receives it.';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationLabel(): string
    {
        return 'Alert control';
    }

    public static function getNavigationSort(): ?int
    {
        return 42;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-megaphone';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public bool $enabled = true;

    public bool $hideDetails = false;

    /** @var array<int, array<string, mixed>> */
    public array $topics = [];

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->enabled = filter_var($settings->get('push.enabled', true), FILTER_VALIDATE_BOOLEAN);
        $this->hideDetails = filter_var($settings->get('push.hide_details', false), FILTER_VALIDATE_BOOLEAN);

        app(PushTopicRegistry::class)->syncBuiltin();

        $this->topics = PushTopic::query()
            ->orderBy('group')->orderBy('sort')->orderBy('label')
            ->get()
            ->map(fn (PushTopic $t) => [
                'id' => $t->id,
                'key' => $t->key,
                'group' => $t->group,
                'label' => $t->label,
                'description' => $t->description,
                'urgency' => $t->urgency,
                'push_enabled' => (bool) $t->push_enabled,
                'sound_enabled' => (bool) $t->sound_enabled,
                'allowed_roles' => $t->allowed_roles ?? [],
                'is_auto' => (bool) $t->is_auto,
            ])
            ->all();
    }

    /** @return array<string, string> role name => label */
    public function roleOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->pluck('name', 'name')
            ->map(fn (string $name) => ucwords(str_replace('_', ' ', $name)))
            ->all();
    }

    public function save(): void
    {
        $this->validate([
            'topics.*.label' => ['required', 'string', 'max:150'],
            'topics.*.urgency' => ['required', 'in:normal,urgent'],
            'topics.*.allowed_roles' => ['array'],
            'topics.*.allowed_roles.*' => ['string', 'max:100'],
        ]);

        $settings = app(SettingsService::class);
        $old = [
            'enabled' => $settings->get('push.enabled', true),
            'hide_details' => $settings->get('push.hide_details', false),
        ];

        $settings->set('push.enabled', $this->enabled ? 'true' : 'false');
        $settings->set('push.hide_details', $this->hideDetails ? 'true' : 'false');

        foreach ($this->topics as $row) {
            PushTopic::query()->find($row['id'])?->update([
                'label' => $row['label'],
                'urgency' => $row['urgency'],
                'push_enabled' => (bool) $row['push_enabled'],
                'sound_enabled' => (bool) $row['sound_enabled'],
                // Empty selection = every role (not "nobody"), the least surprising reading.
                'allowed_roles' => ! empty($row['allowed_roles']) ? array_values($row['allowed_roles']) : null,
            ]);
        }

        if ($admin = auth('admin')->user()) {
            ActivityLog::create([
                'admin_id' => $admin->id,
                'action' => 'settings_updated',
                'model_type' => PushTopic::class,
                'model_id' => null,
                'old_values' => $old,
                'new_values' => ['enabled' => $this->enabled, 'hide_details' => $this->hideDetails, 'topics' => count($this->topics)],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        Notification::make()->title('Alert control saved')->success()->send();
    }

    /**
     * @return array{devices: int, admins: int, sent: int, failed: int, expired: int, deferred: int}
     */
    public function stats(): array
    {
        $since = now()->subDay();
        $byStatus = AdminPushDelivery::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'devices' => AdminPushSubscription::count(),
            'admins' => AdminPushSubscription::query()->distinct()->count('admin_id'),
            'sent' => (int) ($byStatus['sent'] ?? 0),
            'failed' => (int) ($byStatus['failed'] ?? 0),
            'expired' => (int) ($byStatus['expired'] ?? 0),
            'deferred' => AdminPushDeferred::count(),
        ];
    }

    /** @return Collection<int, AdminPushDelivery> */
    public function recentProblems(): Collection
    {
        return AdminPushDelivery::query()
            ->whereIn('status', ['failed', 'expired'])
            ->latest('id')
            ->limit(8)
            ->get();
    }

    public function sendMyTest(): void
    {
        $result = app(AdminPushService::class)->sendTest(auth('admin')->user());

        Notification::make()->title($result['message'])->{$result['ok'] ? 'success' : 'danger'}()->send();
    }
}
