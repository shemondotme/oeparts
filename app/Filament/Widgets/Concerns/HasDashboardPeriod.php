<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\WidgetPreferenceService;
use Carbon\CarbonInterface;

/**
 * Global dashboard period participation.
 *
 * The Dashboard page persists the selected period ('1','7','30','90','365')
 * per admin and broadcasts 'period-changed'. Widgets using this trait
 * hydrate the persisted value on mount (so lazy-loaded widgets are correct
 * without the event) and re-render when it changes.
 *
 * Exempt widgets (registry 'period' => false) keep their semantic windows:
 * DashboardHeader (today), QuickActions, HealthStrip, DiskSpace,
 * RequestMetrics, DashboardAlerts, StockAlert, AbandonedCart (2h),
 * PartsInquiry pending, RecentActivityLog.
 */
trait HasDashboardPeriod
{
    public string $period = '30';

    public function mountHasDashboardPeriod(): void
    {
        $this->period = app(WidgetPreferenceService::class)->getPeriod();
    }

    protected function getListeners(): array
    {
        return [
            'period-changed' => 'updatePeriod',
        ];
    }

    public function updatePeriod(string $period): void
    {
        if (in_array($period, ['1', '7', '30', '90', '365'], true)) {
            $this->period = $period;
        }
    }

    /** Start of the selected window; '1' means today (midnight), not 24h back. */
    protected function periodStart(): CarbonInterface
    {
        return $this->period === '1'
            ? today()
            : now()->subDays((int) $this->period);
    }

    protected function periodLabel(): string
    {
        return match ($this->period) {
            '1' => 'today',
            '7' => 'last 7 days',
            '90' => 'last 90 days',
            '365' => 'last year',
            default => 'last 30 days',
        };
    }
}
