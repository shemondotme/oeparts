<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Native Sales-report KPIs (was hand-rolled stat cards in the custom Blade
 * view). Reads the report page's selected $period, passed in on mount.
 */
class SalesStats extends StatsOverviewWidget
{
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected function getStats(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $start = $this->periodStart();

            return [
                'revenue' => (float) Order::whereNotIn('status', ['cancelled', 'refunded'])
                    ->where('created_at', '>=', $start)
                    ->sum('grand_total'),
                'orders' => Order::where('created_at', '>=', $start)->count(),
                'avg' => (float) Order::whereNotIn('status', ['cancelled', 'refunded'])
                    ->where('created_at', '>=', $start)
                    ->avg('grand_total'),
            ];
        });

        return [
            Stat::make('Gross Revenue', format_money($d['revenue']))
                ->description('Paid orders in period')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Total Orders', number_format($d['orders']))
                ->description('All orders in period')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('primary'),
            Stat::make('Avg Order Value', format_money($d['avg']))
                ->description('Per paid order')
                ->descriptionIcon('heroicon-o-calculator')
                ->color('warning'),
        ];
    }
}
