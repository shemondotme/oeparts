<?php

namespace App\Filament\Widgets\Reports;

use App\Models\FailedSearchLog;
use App\Models\SearchLog;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SearchStats extends StatsOverviewWidget
{
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected function getStats(): array
    {
        $start = $this->periodStart();

        $total = SearchLog::where('created_at', '>=', $start)->count();
        $unresolved = FailedSearchLog::where('created_at', '>=', $start)
            ->where('inquiry_submitted', false)
            ->count();

        return [
            Stat::make('Total Searches', number_format($total))
                ->description('Search queries in period')
                ->descriptionIcon('heroicon-o-magnifying-glass')
                ->color('primary'),
            Stat::make('Unresolved Searches', number_format($unresolved))
                ->description('No results, no inquiry — sourcing gaps')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($unresolved > 0 ? 'warning' : 'success'),
        ];
    }
}
