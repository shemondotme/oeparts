<?php

namespace App\Filament\Widgets\Seo;

use App\Services\CoreWebVitalsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class CoreWebVitalsWidget extends StatsOverviewWidget
{
    private const RATING_COLORS = [
        'good' => 'success',
        'needs-improvement' => 'warning',
        'poor' => 'danger',
    ];

    protected function getStats(): array
    {
        $service = app(CoreWebVitalsService::class);

        if (! $service->isConfigured()) {
            return [
                Stat::make('Core Web Vitals', 'Not Connected')
                    ->description('Add a CrUX API key on the Control Center\'s Structured Data tab to see real-user LCP/CLS/INP here.')
                    ->descriptionIcon('heroicon-o-link-slash')
                    ->color('gray'),
            ];
        }

        $metrics = Cache::remember('admin:seo-health:crux', 300, fn () => $service->getMetrics());

        if (isset($metrics['insufficientData'])) {
            return [
                Stat::make('Core Web Vitals', 'Insufficient Data')
                    ->description('CrUX has no real-user data yet for this origin — needs enough Chrome traffic, not a bug in this integration.')
                    ->descriptionIcon('heroicon-o-information-circle')
                    ->color('gray'),
            ];
        }

        if (isset($metrics['error'])) {
            return [
                Stat::make('Core Web Vitals', 'Connection Error')
                    ->description($metrics['error'])
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('danger'),
            ];
        }

        return [
            $this->stat('LCP (p75)', $metrics['lcp_ms'], 'ms', $service->lcpRating($metrics['lcp_ms'])),
            $this->stat('CLS (p75)', $metrics['cls'], '', $service->clsRating($metrics['cls'])),
            $this->stat('INP (p75)', $metrics['inp_ms'], 'ms', $service->inpRating($metrics['inp_ms'])),
        ];
    }

    private function stat(string $label, int|float|null $value, string $unit, ?string $rating): Stat
    {
        $display = $value === null ? '—' : (is_float($value) ? number_format($value, 2) : number_format($value)).$unit;

        return Stat::make($label, $display)
            ->description($rating ? ucwords(str_replace('-', ' ', $rating)) : 'No data')
            ->descriptionIcon('heroicon-o-signal')
            ->color(self::RATING_COLORS[$rating] ?? 'gray');
    }
}
