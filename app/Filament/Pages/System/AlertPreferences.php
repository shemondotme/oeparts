<?php

namespace App\Filament\Pages\System;

use App\Models\Admin;
use App\Models\AdminPushPreference;
use App\Models\AdminPushSubscription;
use App\Models\PushTopic;
use App\Services\Push\AdminPushService;
use App\Services\Push\PushTopicRegistry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * "My alerts": every admin's own device list and notification preferences —
 * what reaches their phone/desktop, with or without sound, and when to keep
 * quiet. The site-wide switches (which kinds exist, urgency, roles) live on
 * AlertControlCenter; anything an admin turns off here applies to them only.
 */
class AlertPreferences extends Page
{
    protected static ?string $slug = 'system/my-alerts';

    protected static ?string $title = 'My alerts';

    protected string $view = 'filament.pages.system.alert-preferences';

    protected ?string $subheading = 'Choose what reaches your phone and computer, and when to stay quiet. Turn alerts on for each device from the bell icon in the top bar.';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationLabel(): string
    {
        return 'My alerts';
    }

    public static function getNavigationSort(): ?int
    {
        return 43;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-bell-alert';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->check();
    }

    public bool $pushEnabled = true;

    public bool $soundEnabled = true;

    /** 'default' (follow the site setting) | 'hide' | 'show' */
    public string $hideDetails = 'default';

    public bool $quietEnabled = false;

    public string $quietStart = '22:00';

    public string $quietEnd = '08:00';

    /** @var array<string, bool> topic key => receive it? */
    public array $topicOn = [];

    public function mount(): void
    {
        $pref = AdminPushPreference::forAdmin($this->admin());

        $this->pushEnabled = (bool) $pref->push_enabled;
        $this->soundEnabled = (bool) $pref->sound_enabled;
        $this->hideDetails = $pref->hide_details === null ? 'default' : ($pref->hide_details ? 'hide' : 'show');
        $this->quietEnabled = (bool) $pref->quiet_enabled;
        $this->quietStart = $pref->quiet_start ?: '22:00';
        $this->quietEnd = $pref->quiet_end ?: '08:00';

        foreach ($this->availableTopics() as $topic) {
            $this->topicOn[$topic->key] = $pref->topicOverride($topic->key) !== false;
        }
    }

    /** @return Collection<int, PushTopic> */
    public function availableTopics(): Collection
    {
        app(PushTopicRegistry::class)->syncBuiltin();

        $roles = $this->admin()->getRoleNames()->all();

        return PushTopic::query()
            ->where('push_enabled', true)
            ->orderBy('group')
            ->orderBy('sort')
            ->get()
            ->filter(fn (PushTopic $topic) => $topic->allowsRoles($roles))
            ->values();
    }

    /** @return Collection<int, AdminPushSubscription> */
    public function devices(): Collection
    {
        return $this->admin()->pushSubscriptions()->latest()->get();
    }

    public function save(): void
    {
        $this->validate([
            'quietStart' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'quietEnd' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'hideDetails' => ['in:default,hide,show'],
        ]);

        $pref = AdminPushPreference::forAdmin($this->admin());

        $known = $this->availableTopics()->pluck('key')->all();
        $overrides = collect($pref->topic_overrides ?? [])
            // Keep overrides for topics this admin can't currently see (a role change
            // may bring them back) but drop the ones this form manages and re-add below.
            ->reject(fn ($value, $key) => in_array($key, $known, true))
            ->all();

        foreach ($known as $key) {
            if (($this->topicOn[$key] ?? true) === false) {
                $overrides[$key] = false;
            }
        }

        $pref->update([
            'push_enabled' => $this->pushEnabled,
            'sound_enabled' => $this->soundEnabled,
            'hide_details' => match ($this->hideDetails) {
                'hide' => true,
                'show' => false,
                default => null,
            },
            'quiet_enabled' => $this->quietEnabled,
            'quiet_start' => $this->quietStart,
            'quiet_end' => $this->quietEnd,
            'topic_overrides' => $overrides ?: null,
        ]);

        Notification::make()->title('Alert preferences saved')->success()->send();
    }

    public function removeDevice(int $id): void
    {
        $this->admin()->pushSubscriptions()->whereKey($id)->delete();

        Notification::make()->title('Device removed')->success()->send();
    }

    public function testDevice(int $id): void
    {
        $subscription = $this->admin()->pushSubscriptions()->whereKey($id)->first();
        $result = app(AdminPushService::class)->sendTest($this->admin(), $subscription);

        Notification::make()
            ->title($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    private function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        return $admin;
    }
}
