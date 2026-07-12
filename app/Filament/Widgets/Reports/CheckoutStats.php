<?php

namespace App\Filament\Widgets\Reports;

use App\Models\AbandonedCart;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CheckoutStats extends StatsOverviewWidget
{
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected function getStats(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $start = $this->periodStart();

            $abandoned = AbandonedCart::where('created_at', '>=', $start)->count();
            $completed = Order::where('created_at', '>=', $start)->count();
            $started = $completed + $abandoned;
            $paid = Order::where('created_at', '>=', $start)
                ->whereIn('status', ['paid', 'shipped', 'delivered'])
                ->count();

            $dropoff = $started > 0 ? round(100 - (($paid / $started) * 100), 1) : 0.0;

            return compact('started', 'paid', 'dropoff');
        });

        return [
            Stat::make('Drop-off Rate', $d['dropoff'] . '%')
                ->description('Started checkout but never paid')
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color($d['dropoff'] > 50 ? 'danger' : 'warning'),
            Stat::make('Started Checkout', number_format($d['started']))
                ->description('Orders + abandoned carts')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('primary'),
            Stat::make('Paid', number_format($d['paid']))
                ->description('Reached paid / shipped / delivered')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
