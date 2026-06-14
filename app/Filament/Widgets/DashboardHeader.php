<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class DashboardHeader extends Widget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Welcome overview and quick actions';
    }

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.dashboard-header';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -39;

    protected function getViewData(): array
    {
        $hour = now()->hour;
        if ($hour < 12) {
            $greeting = 'Good morning';
        } elseif ($hour < 17) {
            $greeting = 'Good afternoon';
        } else {
            $greeting = 'Good evening';
        }

        $admin = auth('admin')->user();

        $d = $this->cachedWidgetData(function (): array {
            $paidStatuses = [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ];

            return [
                'todayRevenue' => (string) Order::whereIn('status', $paidStatuses)
                    ->whereDate('created_at', today())
                    ->sum('grand_total'),
                'todayOrders' => Order::whereDate('created_at', today())->count(),
                'pendingOrders' => Order::where('status', OrderStatus::Pending->value)->count(),
                'activeUsers' => User::where('is_active', true)->count(),
                'failedJobs' => DB::table('failed_jobs')->count(),
            ];
        });

        $pendingWarning = (int) settings('dashboard.pending_orders_warning', 50);

        $systemHealth = 'healthy';
        if ($d['failedJobs'] > 0) $systemHealth = 'degraded';
        if ($d['pendingOrders'] > $pendingWarning) $systemHealth = 'warning';

        // The revenue tile is restricted to management roles; the header
        // itself is visible to every role (see registry 'roles').
        $showRevenue = $admin?->hasAnyRole(['super_admin', 'admin', 'manager']) ?? false;

        $roleBadge = $admin?->getRoleNames()?->first() ?? 'Admin';
        $roleColors = [
            'super_admin' => 'var(--accent-danger)',
            'admin' => 'var(--accent-brand)',
            'manager' => 'var(--accent-warning)',
            'catalog_admin' => 'var(--accent-success)',
            'support' => 'var(--text-secondary)',
        ];
        $badgeColor = $roleColors[$roleBadge] ?? 'var(--accent-brand)';

        return [
            'greeting' => $greeting,
            'adminName' => $admin ? $admin->name : 'Demo Admin',
            'roleBadge' => ucwords(str_replace('_', ' ', $roleBadge)),
            'badgeColor' => $badgeColor,
            'currentDate' => now()->format('l, F j, Y'),
            'showRevenue' => $showRevenue,
            'todayRevenue' => $showRevenue ? format_money($d['todayRevenue']) : null,
            'todayOrders' => $d['todayOrders'],
            'pendingOrders' => $d['pendingOrders'],
            'activeUsers' => $d['activeUsers'],
            'systemHealth' => $systemHealth,
            'failedJobs' => $d['failedJobs'],
        ];
    }
}
