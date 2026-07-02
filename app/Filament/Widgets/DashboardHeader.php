<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DashboardHeader extends StatsOverviewWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -39;

    protected function getStats(): array
    {
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
                'revenueSparkline' => collect(range(6, 0))->map(
                    fn ($i) => (float) Order::whereIn('status', $paidStatuses)
                        ->whereDate('created_at', today()->subDays($i))->sum('grand_total')
                )->all(),
                'ordersSparkline' => collect(range(6, 0))->map(
                    fn ($i) => Order::whereDate('created_at', today()->subDays($i))->count()
                )->all(),
                'usersSparkline' => collect(range(6, 0))->map(
                    fn ($i) => User::whereDate('created_at', today()->subDays($i))->count()
                )->all(),
            ];
        });

        $showRevenue = $admin?->hasAnyRole(['super_admin', 'admin', 'manager']) ?? false;

        // Yesterday = second-to-last sparkline point (index 5 of the 7-day series).
        $ordersDelta = $this->delta((float) $d['todayOrders'], (float) ($d['ordersSparkline'][5] ?? 0));

        $stats = [
            Stat::make('Today\'s Orders', $d['todayOrders'])
                ->description($ordersDelta['text'])
                ->descriptionIcon($ordersDelta['icon'])
                ->chart($d['ordersSparkline'])
                ->color($ordersDelta['color'])
                ->url(OrderResource::getUrl('index')),
            Stat::make('Pending Orders', $d['pendingOrders'])
                ->description('Awaiting processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color($d['pendingOrders'] > 0 ? 'warning' : 'success')
                ->url(OrderResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => OrderStatus::Pending->value]],
                ])),
            Stat::make('Active Users', $d['activeUsers'])
                ->description('Registered customers')
                ->descriptionIcon('heroicon-m-users')
                ->chart($d['usersSparkline'])
                ->color('info')
                ->url(CustomerResource::getUrl('index')),
        ];

        if ($showRevenue) {
            $revenueDelta = $this->delta((float) $d['todayRevenue'], (float) ($d['revenueSparkline'][5] ?? 0));

            array_unshift($stats,
                Stat::make('Today\'s Revenue', format_money($d['todayRevenue']))
                    ->description($revenueDelta['text'])
                    ->descriptionIcon($revenueDelta['icon'])
                    ->chart($d['revenueSparkline'])
                    ->color($revenueDelta['color'])
                    ->url(OrderResource::getUrl('index'))
            );
        }

        return $stats;
    }

    /**
     * Build a period-over-period delta indicator (text + trend icon + color)
     * for a KPI stat, comparing today's value to yesterday's.
     *
     * @return array{text: string, icon: string, color: string}
     */
    private function delta(float $today, float $previous): array
    {
        if ($previous <= 0.0) {
            return $today > 0.0
                ? ['text' => 'New activity today', 'icon' => 'heroicon-m-arrow-trending-up', 'color' => 'success']
                : ['text' => 'No activity yet', 'icon' => 'heroicon-m-minus-small', 'color' => 'gray'];
        }

        $pct = (($today - $previous) / $previous) * 100;
        $label = number_format(abs($pct), 1) . '% vs yesterday';

        if ($pct > 0.0) {
            return ['text' => $label, 'icon' => 'heroicon-m-arrow-trending-up', 'color' => 'success'];
        }

        if ($pct < 0.0) {
            return ['text' => $label, 'icon' => 'heroicon-m-arrow-trending-down', 'color' => 'danger'];
        }

        return ['text' => 'No change vs yesterday', 'icon' => 'heroicon-m-minus-small', 'color' => 'gray'];
    }
}
