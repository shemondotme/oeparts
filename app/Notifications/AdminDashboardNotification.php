<?php

namespace App\Notifications;

use App\Enums\AdminNotificationCategory;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Fed by AdminNotificationService (job-failure alerts, health-check
 * warnings, cache issues) and displayed nowhere else — Filament's topbar
 * bell is the only notification UI this app has (see
 * Filament\Notifications\Livewire\DatabaseNotifications::getNotificationsQuery(),
 * which filters strictly on data->format = 'filament'). toDatabase() must
 * therefore produce the same JSON shape Filament\Notifications\Notification
 * itself produces, or these alerts are silently invisible forever — which
 * is exactly what was happening before this fix (title/detail/category/
 * action_url alone, no 'format' key, so 0 of them ever rendered).
 */
class AdminDashboardNotification extends Notification
{
    public function __construct(
        public readonly AdminNotificationCategory $category,
        public readonly string $title,
        public readonly string $detail,
        public readonly ?string $actionUrl = null,
        public readonly array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->detail)
            ->icon($this->category->filamentIcon())
            ->iconColor($this->category->filamentColor());

        if (filled($this->actionUrl)) {
            $notification->actions([
                Action::make('view')
                    ->label('View')
                    ->url($this->actionUrl)
                    ->markAsRead(),
            ]);
        }

        return [
            ...$notification->getDatabaseMessage(),
            // Preserved for AdminNotificationService's own batching query
            // (data->category) and CSV/JSON export — extra keys alongside
            // Filament's own are otherwise ignored by its renderer.
            'category' => $this->category->value,
            'detail' => $this->detail,
            'action_url' => $this->actionUrl,
            ...$this->extra,
        ];
    }
}
