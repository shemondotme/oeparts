<?php

namespace App\Filament\Widgets\Seo;

use App\Models\FailedSearchLog;
use App\Models\SearchLog;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Fully buildable from existing data — search_logs/failed_search_logs are
 * already written on every search, just never visualized. The
 * exact/cross-reference/partial ratio needed the match_type column added
 * alongside this dashboard (see the migration in the same SEO program).
 */
class InternalSearchAnalyticsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $since = Carbon::now()->subDays(30);

        $total = SearchLog::where('created_at', '>=', $since)->count();
        $zeroResult = FailedSearchLog::where('created_at', '>=', $since)->count();

        $byMatchType = SearchLog::where('created_at', '>=', $since)
            ->whereNotNull('match_type')
            ->selectRaw('match_type, COUNT(*) as c')
            ->groupBy('match_type')
            ->pluck('c', 'match_type');

        $matched = (int) $byMatchType->sum();
        $ratioDescription = $matched > 0
            ? sprintf(
                'Exact %d%% / Cross-ref %d%% / Partial %d%%',
                round(100 * ($byMatchType->get('exact', 0)) / $matched),
                round(100 * ($byMatchType->get('cross_reference', 0)) / $matched),
                round(100 * ($byMatchType->get('partial', 0)) / $matched),
            )
            : 'No matched searches yet';

        $zeroResultRate = ($total + $zeroResult) > 0
            ? round(100 * $zeroResult / ($total + $zeroResult))
            : 0;

        return [
            Stat::make('Searches (30d)', number_format($total))
                ->description($ratioDescription)
                ->descriptionIcon('heroicon-o-magnifying-glass')
                ->color('primary'),
            Stat::make('Zero-Result Rate', "{$zeroResultRate}%")
                ->description(number_format($zeroResult).' searches found nothing — sourcing gaps or bad OEMs')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($zeroResultRate > 20 ? 'danger' : ($zeroResultRate > 5 ? 'warning' : 'success')),
        ];
    }
}
