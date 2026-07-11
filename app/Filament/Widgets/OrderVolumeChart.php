<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrderVolumeChart extends ChartWidget
{
    use Concerns\HasDashboardPeriod;
    use Concerns\HasPeriodFilterPills;
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    // Eager: async-alpine never initializes charts on lazily-morphed HTML (see RevenueChart).
    protected static bool $isLazy = false;

    protected ?string $heading = 'Order Volume';

    // Segmented pill period selector instead of the native <select> dropdown.
    protected string $view = 'filament.widgets.chart-with-period';

    protected ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -34;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();
        $end = now();

        $cached = $this->cachedWidgetData(function () use ($start, $end): array {
            $data = Trend::query(Order::query())
                ->between(start: $start, end: $end)
                ->perDay()
                ->count();

            // Immediately-preceding window of equal length, drawn as a faded
            // overlay line for at-a-glance period-over-period comparison.
            $lengthDays = max((int) $start->diffInDays($end), 1);
            $prev = Trend::query(Order::query())
                ->between(start: $start->copy()->subDays($lengthDays), end: $start->copy())
                ->perDay()
                ->count();

            $values = $data->map(fn (TrendValue $v) => $v->aggregate)->all();

            return [
                'values' => $values,
                'labels' => $data->map(fn (TrendValue $v) => $v->date)->all(),
                'prevValues' => array_slice(
                    array_values($prev->map(fn (TrendValue $v) => $v->aggregate)->all()),
                    0,
                    count($values)
                ),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $cached['values'],
                    'backgroundColor' => '#F59E0B',
                    'borderColor' => 'transparent',
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => '#D97706',
                ],
                [
                    'type' => 'line',
                    'label' => 'Previous period',
                    'data' => $cached['prevValues'],
                    'borderColor' => 'rgba(148, 163, 184, 0.7)',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [6, 4],
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'borderWidth' => 2,
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
                    'grid' => [
                        'color' => 'rgba(0,0,0,0.06)',
                        'borderDash' => [4, 4],
                        'drawBorder' => false,
                    ],
                    'ticks' => [
                        'precision' => 0,
                        'maxTicksLimit' => 5,
                        'font' => ['size' => 11],
                        'color' => '#71717b',
                    ],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => [
                        'font' => ['size' => 11],
                        'color' => '#71717b',
                        'maxRotation' => 45,
                        'autoSkip' => true,
                        'maxTicksLimit' => 10,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'align' => 'end',
                    'labels' => [
                        'boxWidth' => 12,
                        'boxHeight' => 12,
                        'usePointStyle' => true,
                        'font' => ['size' => 11],
                        'color' => '#71717b',
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => '#18181b',
                    'titleColor' => '#fafafa',
                    'bodyColor' => '#a1a1aa',
                    'borderColor' => '#27272a',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'padding' => 10,
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
