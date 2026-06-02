<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue';

    protected static ?int $sort = -18;

    protected static ?string $maxWidth = '2/3';

    public string $period = '30';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $query = Order::whereIn('status', $paidStatuses);

        $data = Trend::query($query)
            ->between(
                start: now()->subDays((int) $this->period),
                end: now(),
            )
            ->perDay()
            ->sum('grand_total');

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (€)',
                    'data' => $data->map(fn (TrendValue $v) => bcadd((string) $v->aggregate, '0', 2)),
                    'borderColor' => '#0B3A68',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => '#0B3A68',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointHoverBackgroundColor' => '#F59E0B',
                    'pointHoverBorderColor' => '#0B3A68',
                ],
            ],
            'labels' => $data->map(fn (TrendValue $v) => $v->date),
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
                    ],
                ],
                'y' => [
                    'grid' => ['color' => 'rgba(0,0,0,0.05)'],
                    'ticks' => [
                        'callback' => 'function(value) { return "€" + value; }',
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(ctx) { return "€" + Number(ctx.parsed.y).toLocaleString("en-US", {minimumFractionDigits: 2}); }',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
