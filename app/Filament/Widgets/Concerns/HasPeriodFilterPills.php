<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\WidgetPreferenceService;

/**
 * The dashboard's ONE global period control, rendered as the segmented
 * pill strip on every chart widget (view: filament.widgets.chart-with-period).
 *
 * A pill click persists the choice per admin (WidgetPreferenceService::
 * savePeriod) and broadcasts 'period-changed', which every widget using
 * HasDashboardPeriod — charts and stat strips alike — already listens for.
 * Pair this trait with HasDashboardPeriod; it drives the ChartWidget's
 * native $filter property, so the clicked chart re-renders through the
 * vendor updateChartData checksum path with no extra wiring.
 */
trait HasPeriodFilterPills
{
    protected function getFilters(): ?array
    {
        return [
            '1' => 'Today',
            '7' => '7d',
            '30' => '30d',
            '90' => '90d',
            '365' => '1y',
        ];
    }

    public function mountHasPeriodFilterPills(): void
    {
        $this->filter = app(WidgetPreferenceService::class)->getPeriod();
    }

    public function updatedFilter(string $value): void
    {
        app(WidgetPreferenceService::class)->savePeriod($value);

        $this->period = $value;

        $this->dispatch('period-changed', period: $value);
    }
}
