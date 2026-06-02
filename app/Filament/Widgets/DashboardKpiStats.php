<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardKpiStats extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -20;

    protected ?string $heading = 'Key Performance Indicators';

    protected function getStats(): array
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $todayRevenue = (string) Order::whereIn('status', $paidStatuses)
            ->whereDate('created_at', today())
            ->sum('grand_total');

        $yesterdayRevenue = (string) Order::whereIn('status', $paidStatuses)
            ->whereDate('created_at', today()->subDay())
            ->sum('grand_total');

        $todayOrders = Order::whereDate('created_at', today())->count();
        $yesterdayOrders = Order::whereDate('created_at', today()->subDay())->count();

        $pendingOrders = Order::where('status', OrderStatus::Pending->value)->count();

        $activeCustomers = User::where('is_active', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $lastWeekCustomers = User::where('is_active', true)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        $lowStock = Product::where('is_in_stock', false)
            ->where('is_active', true)
            ->count();

        $failedPayments = Order::where('payment_status', PaymentStatus::Failed->value)
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make("Today's Revenue", format_money($todayRevenue))
                ->description($this->pctLabel($todayRevenue, $yesterdayRevenue))
                ->descriptionColor($this->pctColor($todayRevenue, $yesterdayRevenue))
                ->color('success')
                ->icon('heroicon-o-currency-euro'),
            Stat::make('New Orders', number_format($todayOrders))
                ->description($this->countLabel($todayOrders, $yesterdayOrders))
                ->descriptionColor($this->countColor($todayOrders, $yesterdayOrders))
                ->color('primary')
                ->icon('heroicon-o-shopping-bag'),
            Stat::make('Pending Orders', number_format($pendingOrders))
                ->description($pendingOrders > 10 ? 'Action needed' : 'On track')
                ->descriptionColor($pendingOrders > 10 ? 'danger' : 'success')
                ->color($pendingOrders > 10 ? 'warning' : 'primary')
                ->icon('heroicon-o-clock'),
            Stat::make('Active Customers', number_format($activeCustomers))
                ->description($this->countLabel($activeCustomers, $lastWeekCustomers))
                ->descriptionColor($this->countColor($activeCustomers, $lastWeekCustomers))
                ->color('info')
                ->icon('heroicon-o-users'),
            Stat::make('Low Stock Parts', number_format($lowStock))
                ->description($lowStock > 0 ? 'Needs attention' : 'All stocked')
                ->descriptionColor($lowStock > 0 ? 'danger' : 'success')
                ->color($lowStock > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Failed Payments', number_format($failedPayments))
                ->description($failedPayments > 0 ? 'Requires review' : 'No failures')
                ->descriptionColor($failedPayments > 0 ? 'danger' : 'success')
                ->color($failedPayments > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-credit-card'),
        ];
    }

    private function pctLabel(string $current, string $previous): string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return bccomp($current, '0', 2) === 1 ? '+100.0% vs yesterday' : 'No data yesterday';
        }

        $diff = bcsub($current, $previous, 4);
        $ratio = bcdiv($diff, $previous, 4);
        $pct = (float) bcmul($ratio, '100', 1);

        $sign = $pct > 0 ? '+' : '';

        return sprintf('%s%.1f%% vs yesterday', $sign, $pct);
    }

    private function pctColor(string $current, string $previous): string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return bccomp($current, '0', 2) === 1 ? 'success' : 'gray';
        }

        return bccomp($current, $previous, 2) >= 0 ? 'success' : 'danger';
    }

    private function countLabel(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '+100.0% vs prior period' : 'No data prior period';
        }

        $pct = round((($current - $previous) / $previous) * 100, 1);
        $sign = $pct > 0 ? '+' : '';

        return sprintf('%s%.1f%% vs prior period', $sign, $pct);
    }

    private function countColor(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? 'success' : 'gray';
        }

        return $current >= $previous ? 'success' : 'danger';
    }
}
