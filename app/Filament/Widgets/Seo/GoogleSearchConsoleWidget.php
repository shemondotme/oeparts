<?php

namespace App\Filament\Widgets\Seo;

use App\Services\GoogleSearchConsoleService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class GoogleSearchConsoleWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $service = app(GoogleSearchConsoleService::class);

        if (! $service->isConfigured()) {
            return [
                Stat::make('Google Search Console', 'Not Connected')
                    ->description('Add OAuth credentials on the Control Center\'s Structured Data tab to see indexed/submitted counts here.')
                    ->descriptionIcon('heroicon-o-link-slash')
                    ->color('gray'),
            ];
        }

        $summary = Cache::remember('admin:seo-health:gsc', 300, fn () => $service->getSitemapSummary());

        if (isset($summary['error'])) {
            return [
                Stat::make('Google Search Console', 'Connection Error')
                    ->description($summary['error'])
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        return [
            Stat::make('Indexed vs Submitted', number_format($summary['indexed']).' / '.number_format($summary['submitted']))
                ->description('From Search Console\'s sitemap report')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color($summary['indexed'] < $summary['submitted'] ? 'warning' : 'success'),
            Stat::make('Sitemap Errors / Warnings', $summary['errors'].' / '.$summary['warnings'])
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($summary['errors'] > 0 ? 'danger' : ($summary['warnings'] > 0 ? 'warning' : 'success')),
        ];
    }
}
