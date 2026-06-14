<?php

namespace App\Livewire;

use App\Services\AdminNotificationService;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationCenter extends Component
{
    public bool $open          = false;
    public int  $unreadCount   = 0;
    public array $notifications = [];
    public array $collapsed     = [];

    protected $listeners = ['notification-center-refresh' => 'refresh'];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        /** @var AdminNotificationService $service */
        $service = app(AdminNotificationService::class);

        $this->unreadCount  = $service->unreadCount($admin->id);
        $items              = $service->getForAdmin($admin->id);

        // Group by category for display
        $grouped = [];
        foreach ($items as $n) {
            $cat = $n->category->value;
            $grouped[$cat][] = $n;
        }
        $this->notifications = $grouped;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function toggleCategory(string $category): void
    {
        if (in_array($category, $this->collapsed)) {
            $this->collapsed = array_values(array_filter($this->collapsed, fn ($c) => $c !== $category));
        } else {
            $this->collapsed[] = $category;
        }
    }

    public function markRead(string $id): void
    {
        $admin = auth('admin')->user();
        if (! $admin) return;

        app(AdminNotificationService::class)->markRead($id);
        $this->refresh();
    }

    public function markAllRead(): void
    {
        $admin = auth('admin')->user();
        if (! $admin) return;

        app(AdminNotificationService::class)->markAllRead($admin->id);
        $this->refresh();
    }

    public function dismiss(string $id): void
    {
        $admin = auth('admin')->user();
        if (! $admin) return;

        app(AdminNotificationService::class)->dismiss($id);
        $this->refresh();
    }

    public function exportCsv(): void
    {
        $admin = auth('admin')->user();
        if (! $admin) return;

        $notifications = app(AdminNotificationService::class)->getForAdmin($admin->id);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['ID', 'Category', 'Title', 'Detail', 'Read At', 'Created At']);
        foreach ($notifications as $n) {
            fputcsv($stream, [
                $n->id,
                $n->category->label(),
                $n->title,
                $n->detail,
                $n->read_at ?? '',
                $n->created_at ?? '',
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->dispatch('op:download-blob',
            content: base64_encode($csv),
            mime: 'text/csv;charset=UTF-8',
            filename: 'notifications-' . date('Y-m-d') . '.csv',
        );
    }

    public function exportJson(): void
    {
        $admin = auth('admin')->user();
        if (! $admin) return;

        $notifications = app(AdminNotificationService::class)->getForAdmin($admin->id);
        $json = json_encode($notifications->toArray(), JSON_PRETTY_PRINT);

        $this->dispatch('op:download-blob',
            content: base64_encode($json),
            mime: 'application/json',
            filename: 'notifications-' . date('Y-m-d') . '.json',
        );
    }

    public function render()
    {
        return view('livewire.notification-center');
    }
}
