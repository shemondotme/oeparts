<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

use App\Filament\Resources\OrderResource;

class RevenueChart extends ChartWidget implements \App\Filament\Support\DrilldownContract
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Revenue trend over the selected period';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Revenue Trend over Time';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -37;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getDrilldownUrl(): ?string
    {
        return OrderResource::getUrl('index');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $cached = $this->cachedWidgetData(function (): array {
            $paidStatuses = [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ];

            $data = Trend::query(Order::whereIn('status', $paidStatuses))
                ->between(
                    start: $this->periodStart(),
                    end: now(),
                )
                ->perDay()
                ->sum('grand_total');

            return [
                'values' => $data->map(fn (TrendValue $v) => bcadd((string) $v->aggregate, '0', 2))->all(),
                'labels' => $data->map(fn (TrendValue $v) => $v->date)->all(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (€)',
                    'data' => $cached['values'],
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'function(ctx){const c=ctx.chart.ctx;const g=c.createLinearGradient(0,0,0,300);g.addColorStop(0,"rgba(139,92,246,0.35)");g.addColorStop(1,"rgba(34,211,238,0.08)");return g;}',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => '#8B5CF6',
                    'pointBorderColor' => '#22D3EE',
                    'pointBorderWidth' => 3,
                    'pointHoverBackgroundColor' => '#A78BFA',
                    'pointHoverBorderColor' => '#67E8F9',
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
                        'font' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color' => '#94a3b8',
                    ],
                ],
                'y' => [
                    'grid' => ['color' => 'rgba(255,255,255,0.06)', 'drawBorder' => false],
                    'ticks' => [
                        'callback' => 'function(value) { return "€" + value; }',
                        'font' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color' => '#94a3b8',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'titleFont' => ['family' => 'Geist Sans, sans-serif', 'size' => 12, 'weight' => 'bold'],
                    'bodyFont' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 12],
                    'backgroundColor' => '#0f172a',
                    'titleColor' => '#f8fafc',
                    'bodyColor' => '#cbd5e1',
                    'borderColor' => '#1e293b',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'padding' => 10,
                    'callbacks' => [
                        'label' => 'function(ctx) { return "€" + Number(ctx.parsed.y).toLocaleString("en-US", {minimumFractionDigits: 2}); }',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
