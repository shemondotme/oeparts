<?php

namespace App\Filament\Widgets\Reports;

use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CustomersStats extends StatsOverviewWidget
{
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected function getStats(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $start = $this->periodStart();

            return [
                'total' => User::count(),
                'new' => User::where('created_at', '>=', $start)->count(),
                'repeat' => DB::table('orders')
                    ->select('user_id')
                    ->whereNotNull('user_id')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->get()
                    ->count(),
            ];
        });

        return [
            Stat::make('Total Customers', number_format($d['total']))
                ->description('All-time registrations')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            Stat::make('New Customers', number_format($d['new']))
                ->description('Joined in period')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('success'),
            Stat::make('Repeat Customers', number_format($d['repeat']))
                ->description('More than one order')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('warning'),
        ];
    }
}
