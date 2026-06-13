<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Support\AdminUi;
use App\Filament\Widgets\Concerns\HasDashboardPeriod;
use App\Filament\Widgets\Concerns\HasWidgetRoles;
use App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardKpiStats extends BaseWidget
{
    use HasDashboardPeriod;
    use HasWidgetRoles;
    use InteractsWithDashboardCache;

    public bool $loadFailed = false;

    public function getDescription(): ?string
    {
        return 'Key performance indicators for the selected period';
    }

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -38;

    protected ?string $heading = 'Key Performance Indicators';

    protected function getStats(): array
    {
        try {
            $d = $this->cachedWidgetData(fn (): array => $this->computeStats());
            $this->loadFailed = false;
        } catch (\Exception $e) {
            report($e);
            $this->loadFailed = true;

            $d = [
                'revenue' => '0.00', 'prevRevenue' => '0.00',
                'orders' => 0, 'prevOrders' => 0,
                'pending' => 0, 'customers' => 0, 'prevCustomers' => 0,
                'lowStock' => 0, 'failedPayments' => 0,
            ];
        }

        $compareLabel = $this->period === '1' ? 'yesterday' : 'prior period';
        $pendingThreshold = (int) settings('dashboard.pending_orders_attention', 10);

        $revenueLabel = $this->period === '1' ? "Today's Revenue" : 'Revenue (' . $this->periodLabel() . ')';
        $ordersLabel = $this->period === '1' ? 'New Orders' : 'Orders (' . $this->periodLabel() . ')';

        return [
            Stat::make($revenueLabel, format_money($d['revenue']))
                ->description($this->loadFailed ? 'Data temporarily unavailable' : $this->pctLabel($d['revenue'], $d['prevRevenue'], $compareLabel))
                ->descriptionColor($this->loadFailed ? 'warning' : $this->pctColor($d['revenue'], $d['prevRevenue']))
                ->color('success')
                ->icon('heroicon-o-currency-euro')
                ->url(bccomp($d['revenue'], '0', 2) === 1 ? AdminUi::drilldownUrl(OrderResource::class, [
                    'created_at' => ['created_from' => $this->periodStart()->toDateString(), 'created_until' => today()->toDateString()],
                ]) : null),
            Stat::make($ordersLabel, number_format($d['orders']))
                ->description($this->loadFailed ? 'Data temporarily unavailable' : $this->countLabel($d['orders'], $d['prevOrders'], $compareLabel))
                ->descriptionColor($this->loadFailed ? 'warning' : $this->countColor($d['orders'], $d['prevOrders']))
                ->color('primary')
                ->icon('heroicon-o-shopping-bag')
                ->url($d['orders'] > 0 ? AdminUi::drilldownUrl(OrderResource::class, [
                    'created_at' => ['created_from' => $this->periodStart()->toDateString(), 'created_until' => today()->toDateString()],
                ]) : null),
            Stat::make('Pending Orders', number_format($d['pending']))
                ->description($d['pending'] > $pendingThreshold ? 'Action needed' : 'On track')
                ->descriptionColor($d['pending'] > $pendingThreshold ? 'danger' : 'success')
                ->color($d['pending'] > $pendingThreshold ? 'warning' : 'primary')
                ->icon('heroicon-o-clock')
                ->url($d['pending'] > 0 ? AdminUi::drilldownUrl(OrderResource::class, [
                    'status' => ['value' => OrderStatus::Pending->value],
                ]) : null),
            Stat::make('New Customers', number_format($d['customers']))
                ->description($this->loadFailed ? 'Data temporarily unavailable' : $this->countLabel($d['customers'], $d['prevCustomers'], $compareLabel))
                ->descriptionColor($this->loadFailed ? 'warning' : $this->countColor($d['customers'], $d['prevCustomers']))
                ->color('info')
                ->icon('heroicon-o-users')
                ->url($d['customers'] > 0 ? CustomerResource::getUrl('index') : null),
            Stat::make('Low Stock Parts', number_format($d['lowStock']))
                ->description($d['lowStock'] > 0 ? 'Needs attention' : 'All stocked')
                ->descriptionColor($d['lowStock'] > 0 ? 'danger' : 'success')
                ->color($d['lowStock'] > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle')
                ->url($d['lowStock'] > 0 ? AdminUi::drilldownUrl(ProductResource::class, [
                    'is_in_stock' => ['value' => '0'],
                ]) : null),
            Stat::make('Failed Payments', number_format($d['failedPayments']))
                ->description($d['failedPayments'] > 0 ? 'Requires review' : 'No failures')
                ->descriptionColor($d['failedPayments'] > 0 ? 'danger' : 'success')
                ->color($d['failedPayments'] > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-credit-card')
                ->url($d['failedPayments'] > 0 ? PaymentResource::getUrl('index') : null),
        ];
    }

    /**
     * Plain-array stat computation (cache contract: no objects).
     * Period '1' keeps the original today-vs-yesterday semantics; any other
     * period compares the window against the prior window of equal length.
     */
    private function computeStats(): array
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $start = $this->periodStart();
        $days = max(1, (int) $this->period);
        $prevStart = $start->copy()->subDays($days);

        return [
            'revenue' => (string) Order::whereIn('status', $paidStatuses)
                ->where('created_at', '>=', $start)->sum('grand_total'),
            'prevRevenue' => (string) Order::whereIn('status', $paidStatuses)
                ->whereBetween('created_at', [$prevStart, $start])->sum('grand_total'),
            'orders' => Order::where('created_at', '>=', $start)->count(),
            'prevOrders' => Order::whereBetween('created_at', [$prevStart, $start])->count(),
            'pending' => Order::where('status', OrderStatus::Pending->value)->count(),
            'customers' => User::where('is_active', true)
                ->where('created_at', '>=', $start)->count(),
            'prevCustomers' => User::where('is_active', true)
                ->whereBetween('created_at', [$prevStart, $start])->count(),
            'lowStock' => Product::where('is_in_stock', false)
                ->where('is_active', true)->count(),
            'failedPayments' => Order::where('payment_status', PaymentStatus::Failed->value)
                ->whereDate('created_at', today())->count(),
        ];
    }

    private function pctLabel(string $current, string $previous, string $compareLabel): string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return bccomp($current, '0', 2) === 1 ? "+100.0% vs {$compareLabel}" : "No data {$compareLabel}";
        }

        $diff = bcsub($current, $previous, 4);
        $ratio = bcdiv($diff, $previous, 4);
        $pct = (float) bcmul($ratio, '100', 1);

        $sign = $pct > 0 ? '+' : '';

        return sprintf('%s%.1f%% vs %s', $sign, $pct, $compareLabel);
    }

    private function pctColor(string $current, string $previous): string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return bccomp($current, '0', 2) === 1 ? 'success' : 'gray';
        }

        return bccomp($current, $previous, 2) >= 0 ? 'success' : 'danger';
    }

    private function countLabel(int $current, int $previous, string $compareLabel): string
    {
        if ($previous === 0) {
            return $current > 0 ? "+100.0% vs {$compareLabel}" : "No data {$compareLabel}";
        }

        $pct = round((($current - $previous) / $previous) * 100, 1);
        $sign = $pct > 0 ? '+' : '';

        return sprintf('%s%.1f%% vs %s', $sign, $pct, $compareLabel);
    }

    private function countColor(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? 'success' : 'gray';
        }

        return $current >= $previous ? 'success' : 'danger';
    }
}
