<?php

namespace App\Services;

use App\Enums\AdminNotificationCategory;
use App\Models\Admin;
use App\Notifications\AdminDashboardNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminNotificationService
{
    /**
     * Send a dashboard notification to a specific admin.
     * Applies batching: if 5+ notifications of the same category arrive within
     * 60 seconds for this admin, they are collapsed into a single summary.
     */
    public function create(
        Admin $admin,
        AdminNotificationCategory $category,
        string $title,
        string $detail,
        ?string $actionUrl = null,
        array $extra = [],
    ): void {
        $admin->notify(
            new AdminDashboardNotification($category, $title, $detail, $actionUrl, $extra)
        );

        $this->batchCheck($admin, $category);
    }

    /**
     * Broadcast a notification to ALL active admins (system-wide alerts).
     */
    public function createForAll(
        AdminNotificationCategory $category,
        string $title,
        string $detail,
        ?string $actionUrl = null,
        array $extra = [],
    ): void {
        Admin::where('is_active', true)->each(
            fn (Admin $admin) => $this->create($admin, $category, $title, $detail, $actionUrl, $extra)
        );
    }

    /**
     * If 5+ unread notifications of the same category arrived within the last
     * 60 seconds, collapse them into a single summary and delete the originals.
     */
    public function batchCheck(Admin $admin, AdminNotificationCategory $category): void
    {
        $window = Carbon::now()->subSeconds(60);

        $recent = DB::table('notifications')
            ->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $admin->id)
            ->where('type', AdminDashboardNotification::class)
            ->whereNull('read_at')
            ->where('created_at', '>=', $window)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.category')) = ?", [$category->value])
            ->get();

        if ($recent->count() < 5) {
            return;
        }

        // Delete the individual notifications and insert a summary
        DB::table('notifications')
            ->whereIn('id', $recent->pluck('id'))
            ->delete();

        $admin->notify(new AdminDashboardNotification(
            category:  $category,
            title:     $recent->count() . ' ' . $category->label() . ' alerts',
            detail:    'Multiple ' . strtolower($category->label()) . ' events occurred in the last minute.',
            actionUrl: null,
            extra:     ['batched' => true, 'count' => $recent->count()],
        ));
    }

    public function markRead(string $id): void
    {
        DB::table('notifications')
            ->where('id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllRead(int $adminId): void
    {
        DB::table('notifications')
            ->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $adminId)
            ->where('type', AdminDashboardNotification::class)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function dismiss(string $id): void
    {
        DB::table('notifications')->where('id', $id)->delete();
    }

    /**
     * Retrieve notifications for an admin, optionally unread-only.
     * Returns the 50 most recent.
     */
    public function getForAdmin(int $adminId, bool $unreadOnly = false): Collection
    {
        $query = DB::table('notifications')
            ->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $adminId)
            ->where('type', AdminDashboardNotification::class)
            ->orderByDesc('created_at')
            ->limit(50);

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->get()->map(function ($row) {
            $data = json_decode($row->data, true);
            return (object) [
                'id'         => $row->id,
                'category'   => AdminNotificationCategory::tryFrom($data['category'] ?? '') ?? AdminNotificationCategory::System,
                'title'      => $data['title'] ?? '',
                'detail'     => $data['detail'] ?? '',
                'action_url' => $data['action_url'] ?? null,
                'batched'    => $data['batched'] ?? false,
                'read_at'    => $row->read_at,
                'created_at' => $row->created_at,
            ];
        });
    }

    public function unreadCount(int $adminId): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', Admin::class)
            ->where('notifiable_id', $adminId)
            ->where('type', AdminDashboardNotification::class)
            ->whereNull('read_at')
            ->count();
    }

    public function exportCsv(int $adminId): StreamedResponse
    {
        $notifications = $this->getForAdmin($adminId);

        return response()->streamDownload(function () use ($notifications) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Category', 'Title', 'Detail', 'Read At', 'Created At']);
            foreach ($notifications as $n) {
                fputcsv($handle, [
                    $n->id,
                    $n->category->label(),
                    $n->title,
                    $n->detail,
                    $n->read_at ?? '',
                    $n->created_at ?? '',
                ]);
            }
            fclose($handle);
        }, 'notifications-' . date('Y-m-d') . '.csv');
    }

    public function exportJson(int $adminId): StreamedResponse
    {
        $notifications = $this->getForAdmin($adminId)->toArray();

        return response()->streamDownload(function () use ($notifications) {
            echo json_encode($notifications, JSON_PRETTY_PRINT);
        }, 'notifications-' . date('Y-m-d') . '.json');
    }
}
