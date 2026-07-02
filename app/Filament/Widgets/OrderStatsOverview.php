<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OrderStatsOverview extends StatsOverviewWidget
{
    use Concerns\HasDashboardPeriod;
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected static ?int $sort = -37;

    protected ?string $heading = 'Order Overview';

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Revenue, new orders, and pending-order wait time for the selected period';
    }

    public function getStats(): array
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $d = $this->cachedWidgetData(function () use ($paidStatuses): array {
            $start = $this->periodStart();
            $days = max(1, (int) $this->period);
            $prevStart = $start->copy()->subDays($days);

            $revenueCurrent = Order::whereIn('status', $paidStatuses)
                ->where('created_at', '>=', $start)
                ->sum('grand_total');

            $revenuePrevious = Order::whereIn('status', $paidStatuses)
                ->whereBetween('created_at', [$prevStart, $start])
                ->sum('grand_total');

            $revenueSparkline = collect(range(6, 0))->map(
                fn ($i) => (string) Order::whereIn('status', $paidStatuses)
                    ->whereDate('created_at', now()->subDays($i))
                    ->sum('grand_total')
            )->values()->toArray();

            $ordersCurrent = Order::where('created_at', '>=', $start)->count();
            $ordersPrevious = Order::whereBetween('created_at', [$prevStart, $start])->count();

            $ordersSparkline = collect(range(6, 0))->map(
                fn ($i) => Order::whereDate('created_at', now()->subDays($i))->count()
            )->values()->toArray();

            $driver = DB::connection()->getDriverName();
            $diffExpr = $driver === 'sqlite'
                ? "(julianday('now') - julianday(created_at)) * 1440"
                : 'TIMESTAMPDIFF(MINUTE, created_at, NOW())';
            $pending = Order::where('status', OrderStatus::Pending->value)
                ->selectRaw("COUNT(*) as count, COALESCE(AVG({$diffExpr}), 0) as avg_minutes")
                ->first();

            return [
                'revenueCurrent' => (string) $revenueCurrent,
                'revenuePrevious' => (string) $revenuePrevious,
                'revenueSparkline' => $revenueSparkline,
                'ordersSparkline' => $ordersSparkline,
                'ordersCurrent' => $ordersCurrent,
                'ordersPrevious' => $ordersPrevious,
                'ordersThreshold' => (int) settings('dashboard.orders_threshold', 50),
                'pendingCount' => (int) ($pending->count ?? 0),
                'pendingAvgMinutes' => (int) round((float) ($pending->avg_minutes ?? 0)),
                'pendingDelayedThreshold' => (int) settings('dashboard.pending_delayed_minutes', 120),
            ];
        });

        $revenueTrendLabel = 'No data';
        if (bccomp($d['revenuePrevious'], '0', 2) === 1) {
            $diff = bcsub($d['revenueCurrent'], $d['revenuePrevious'], 4);
            $ratio = bcdiv($diff, $d['revenuePrevious'], 4);
            $pct = (float) bcmul($ratio, '100', 1);
            $revenueTrendLabel = ($pct > 0 ? '+' : '') . number_format($pct, 1) . '% vs prior period';
        } elseif (bccomp($d['revenueCurrent'], '0', 2) === 1) {
            $revenueTrendLabel = 'New';
        }

        $ordersTrendLabel = 'No data';
        if ($d['ordersPrevious'] > 0) {
            $pct = round((($d['ordersCurrent'] - $d['ordersPrevious']) / $d['ordersPrevious']) * 100, 1);
            $ordersTrendLabel = ($pct > 0 ? '+' : '') . number_format($pct, 1) . '% vs prior period';
        } elseif ($d['ordersCurrent'] > 0) {
            $ordersTrendLabel = 'New';
        }
        $exceedsThreshold = $d['ordersCurrent'] > $d['ordersThreshold'];

        $avgMinutes = $d['pendingAvgMinutes'];
        if ($avgMinutes >= 2880) {
            $waitLabel = round($avgMinutes / 1440, 1) . 'd avg wait';
        } elseif ($avgMinutes >= 60) {
            $waitLabel = floor($avgMinutes / 60) . 'h ' . ($avgMinutes % 60) . 'm avg wait';
        } else {
            $waitLabel = $avgMinutes . 'm avg wait';
        }
        $isDelayed = $avgMinutes > $d['pendingDelayedThreshold'];

        return [
            Stat::make($this->periodLabel() . ' Revenue', '€' . number_format((float) $d['revenueCurrent'], 2))
                ->description($revenueTrendLabel)
                ->descriptionIcon($revenueTrendLabel === 'No data' ? null : 'heroicon-o-banknotes')
                ->chart(array_map('floatval', $d['revenueSparkline']))
                ->color('success')
                ->url(OrderResource::getUrl('index')),

            Stat::make('New Orders', number_format($d['ordersCurrent']))
                ->description($exceedsThreshold ? "{$ordersTrendLabel} — above target" : $ordersTrendLabel)
                ->descriptionIcon($exceedsThreshold ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-shopping-bag')
                ->chart(array_map('intval', $d['ordersSparkline']))
                ->color($exceedsThreshold ? 'warning' : 'info')
                ->url(OrderResource::getUrl('index')),

            Stat::make('Pending Orders', number_format($d['pendingCount']))
                ->description($isDelayed ? "{$waitLabel} — delayed" : $waitLabel)
                ->descriptionIcon($isDelayed ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock')
                ->color($isDelayed ? 'danger' : 'success')
                ->url(OrderResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => OrderStatus::Pending->value]],
                ])),
        ];
    }
}
