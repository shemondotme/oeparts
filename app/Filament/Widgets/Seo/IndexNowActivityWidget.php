<?php

namespace App\Filament\Widgets\Seo;

use App\Models\IndexNowPushLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IndexNowActivityWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $enabled = filter_var(settings('seo.indexnow_enabled', false), FILTER_VALIDATE_BOOLEAN);

        $last = IndexNowPushLog::query()->latest('created_at')->first();
        $recentFailures = IndexNowPushLog::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $lastRunDescription = $last
            ? ucfirst($last->status).' — '.$last->url_count.' URL(s), '.$last->created_at->diffForHumans()
            : 'No pushes recorded yet';

        return [
            Stat::make('IndexNow', $enabled ? 'Enabled' : 'Disabled')
                ->description($enabled ? 'Pushing to Bing/Yandex/Naver/Seznam on product save' : 'Enable on the Control Center\'s Crawlers & AI tab')
                ->descriptionIcon($enabled ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle')
                ->color($enabled ? 'success' : 'gray'),
            Stat::make('Last Push', $lastRunDescription)
                ->description($recentFailures > 0 ? "{$recentFailures} failed push(es) in the last 7 days" : 'No failures in the last 7 days')
                ->descriptionIcon($recentFailures > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($recentFailures > 0 ? 'warning' : 'success'),
        ];
    }
}
