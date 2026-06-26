<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrderVolumeChart extends ChartWidget
{
    use Concerns\HasDashboardPeriod;
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;
    use HasWidgetExport;

    protected static bool $isLazy = true;

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Order Volume';

    protected ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -34;

    // Default to 30-day view; driven by RevenueChart (W7)
    public ?string $filter = '30';

    public function getDescription(): ?string
    {
        return 'Daily order count over the selected period';
    }

    /** Segmented date-range control — mirrors W7 RevenueChart. */
    protected function getFilters(): ?array
    {
        return [
            '1'   => 'Today',
            '7'   => '7d',
            '30'  => '30d',
            '90'  => '90d',
            '365' => '1y',
        ];
    }

    /** When the user changes W8's own filter, sync to W7. */
    public function updatedFilter(string $value): void
    {
        $this->period = $value;
        $this->dispatch('cc-date-range-changed', range: $value);
    }

    /** When W7 changes its filter, follow suit. */
    #[\Livewire\Attributes\On('cc-date-range-changed')]
    public function onCcDateRangeChanged(string $range): void
    {
        if ($this->filter !== $range) {
            $this->filter = $range;
            $this->period = $range;
            $this->updateChartData();
        }
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions(chartOnly: true)];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();
        $end   = now();

        $cached = $this->cachedWidgetData(function () use ($start, $end): array {
            $data = Trend::query(Order::query())
                ->between(start: $start, end: $end)
                ->perDay()
                ->count();

            return [
                'values' => $data->map(fn (TrendValue $v) => $v->aggregate)->all(),
                'labels' => $data->map(fn (TrendValue $v) => $v->date)->all(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label'               => 'Orders',
                    'data'                => $cached['values'],
                    'backgroundColor'     => 'var(--aurora-sky)',
                    'op_gradient'         => ['rgba(14,165,233,0.85)', 'rgba(14,165,233,0.45)'],
                    'borderColor'         => 'transparent',
                    'borderRadius'        => 6,
                    'borderSkipped'       => false,
                    'hoverBackgroundColor' => 'var(--aurora-indigo)',
                ],
            ],
            'labels' => $cached['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid'        => [
                        'color'      => 'var(--chart-grid)',
                        'borderDash' => [4, 4],
                        'drawBorder' => false,
                    ],
                    'ticks'       => [
                        'precision'      => 0,
                        'maxTicksLimit'  => 5,
                        'font'           => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color'          => 'var(--color-text-muted)',
                    ],
                ],
                'x' => [
                    'grid'  => ['display' => false],
                    'ticks' => [
                        'font'  => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color' => 'var(--color-text-muted)',
                        'maxRotation' => 45,
                        'autoSkip'    => true,
                        'maxTicksLimit' => 10,
                    ],
                ],
            ],
            'plugins' => [
                'legend'  => ['display' => false],
                'tooltip' => [
                    'backgroundColor' => 'var(--chart-tooltip-bg)',
                    'titleColor'      => 'var(--chart-tooltip-text)',
                    'bodyColor'       => 'var(--chart-tooltip-text)',
                    'borderColor'     => 'var(--color-border-default)',
                    'borderWidth'     => 1,
                    'cornerRadius'    => 8,
                    'padding'         => 10,
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
