<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusDistributionWidget extends ChartWidget
{
    use Concerns\HasDashboardPeriod;
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected ?string $heading = 'Order Status';

    protected ?string $pollingInterval = '120s';

    protected static bool $isLazy = true;

    protected static ?int $sort = -24;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];


    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();

        $cached = $this->cachedWidgetData(function () use ($start): array {
            $counts = Order::where('created_at', '>=', $start)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();

            $statuses = OrderStatus::cases();
            $labels = [];
            $values = [];
            $colors = [];

            $colorMap = [
                OrderStatus::Pending->value => '#F59E0B',
                OrderStatus::Paid->value => '#D97706',
                OrderStatus::Processing->value => '#3B82F6',
                OrderStatus::Shipped->value => '#10B981',
                OrderStatus::Delivered->value => '#059669',
                OrderStatus::Cancelled->value => '#EF4444',
                OrderStatus::RefundRequested->value => '#DC2626',
                OrderStatus::Refunded->value => '#71717b',
            ];

            foreach ($statuses as $status) {
                $count = $counts[$status->value] ?? 0;
                if ($count === 0) {
                    continue;
                }
                $labels[] = $status->label();
                $values[] = $count;
                $colors[] = $colorMap[$status->value] ?? '#71717b';
            }

            return compact('labels', 'values', 'colors');
        });

        return [
            'datasets' => [
                [
                    'data' => $cached['values'],
                    'backgroundColor' => $cached['colors'],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                    'hoverBorderWidth' => 0,
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => $cached['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                    'labels' => [
                        'font' => ['size' => 12],
                        'color' => '#52525b',
                        'padding' => 12,
                        'boxWidth' => 12,
                        'boxHeight' => 12,
                        'borderRadius' => 3,
                        'useBorderRadius' => true,
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
                    // No JS-function callback here: Filament JSON-encodes chart
                    // options, so a 'function(){…}' string is never a real JS
                    // function and Chart.js silently ignores it. The default
                    // doughnut tooltip ("Label: value") already shows the count.
                ],
            ],
            'cutout' => '65%',
            'maintainAspectRatio' => false,
        ];
    }
}
