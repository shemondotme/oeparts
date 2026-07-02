<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class RevenueChart extends ChartWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected ?string $heading = 'Revenue Trend';

    // Segmented pill period selector instead of the native <select> dropdown.
    protected string $view = 'filament.widgets.chart-with-period';

    protected ?string $pollingInterval = '120s';

    protected static bool $isLazy = true;

    protected static ?int $sort = -38;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public ?string $filter = '30';

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

    public function updatedFilter(string $value): void
    {
        $this->period = $value;
        $this->dispatch('cc-date-range-changed', range: $value);
    }

    #[\Livewire\Attributes\On('cc-date-range-changed')]
    public function onCcDateRangeChanged(string $range): void
    {
        if ($this->filter !== $range) {
            $this->filter = $range;
            $this->period = $range;
            $this->updateChartData();
        }
    }


    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();
        $end = now();

        $cached = $this->cachedWidgetData(function () use ($start, $end): array {
            $paidStatuses = [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ];

            $data = Trend::query(Order::whereIn('status', $paidStatuses))
                ->between(start: $start, end: $end)
                ->perDay()
                ->sum('grand_total');

            // Immediately-preceding window of equal length, for the faded
            // comparison line ("this period vs last period").
            $lengthDays = max((int) $start->diffInDays($end), 1);
            $prev = Trend::query(Order::whereIn('status', $paidStatuses))
                ->between(start: $start->copy()->subDays($lengthDays), end: $start->copy())
                ->perDay()
                ->sum('grand_total');

            $values = $data->map(fn (TrendValue $v) => bcadd((string) $v->aggregate, '0', 2))->all();

            return [
                'values' => $values,
                'labels' => $data->map(fn (TrendValue $v) => $v->date)->all(),
                'prevValues' => array_slice(
                    array_values($prev->map(fn (TrendValue $v) => bcadd((string) $v->aggregate, '0', 2))->all()),
                    0,
                    count($values)
                ),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (€)',
                    'data' => $cached['values'],
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'transparent',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => '#F59E0B',
                    'pointBorderColor' => '#D97706',
                    'pointBorderWidth' => 2,
                    'pointHoverBorderWidth' => 3,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Previous period',
                    'data' => $cached['prevValues'],
                    'borderColor' => 'rgba(148, 163, 184, 0.6)',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [6, 4],
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'pointBackgroundColor' => 'rgba(148, 163, 184, 0.9)',
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
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => [
                        'maxTicksLimit' => 7,
                        'font' => ['size' => 11],
                        'color' => '#71717b',
                    ],
                ],
                'y' => [
                    'grid' => [
                        'color' => 'rgba(0,0,0,0.06)',
                        'borderDash' => [4, 4],
                        'drawBorder' => false,
                    ],
                    'ticks' => [
                        // No 'callback' JS-function string: Filament JSON-encodes
                        // chart options, so it never becomes a real function and
                        // Chart.js ignores it. The dataset label ("Revenue (€)")
                        // already conveys the currency.
                        'font' => ['size' => 11],
                        'color' => '#71717b',
                        'maxTicksLimit' => 5,
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
                    // No JS-function callback: see the ticks note above. The
                    // dataset label "Revenue (€)" already shows the currency in
                    // the default tooltip ("Revenue (€): 1234.56").
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
