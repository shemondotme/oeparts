<?php

namespace App\Notifications;

use App\Enums\AdminNotificationCategory;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class AdminDashboardNotification extends Notification
{
    public function __construct(
        public readonly AdminNotificationCategory $category,
        public readonly string $title,
        public readonly string $detail,
        public readonly ?string $actionUrl = null,
        public readonly array  $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'category'   => $this->category->value,
            'title'      => $this->title,
            'detail'     => $this->detail,
            'action_url' => $this->actionUrl,
            ...$this->extra,
        ];
    }
}
