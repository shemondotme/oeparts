<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class CustomerGrowthChart extends ChartWidget implements \App\Filament\Support\DrilldownContract
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'New customer acquisition trend';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -28;

    protected ?string $heading = 'Acquisition Trend over Time';

    protected static ?string $maxWidth = '2/3';

    public function getDrilldownUrl(): ?string
    {
        return \App\Filament\Resources\CustomerResource::getUrl('index');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $cached = $this->cachedWidgetData(function (): array {
            $data = Trend::model(User::class)
                ->between(
                    start: $this->periodStart(),
                    end: now(),
                )
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
                    'label' => 'New Customers',
                    'data' => $cached['values'],
                    'backgroundColor' => 'function(ctx){const c=ctx.chart.ctx;const g=c.createLinearGradient(0,0,0,300);g.addColorStop(0,"rgba(129,140,248,0.8)");g.addColorStop(1,"rgba(99,102,241,0.5)");return g;}',
                    'hoverBackgroundColor' => 'function(ctx){const c=ctx.chart.ctx;const g=c.createLinearGradient(0,0,0,300);g.addColorStop(0,"rgba(167,139,250,0.9)");g.addColorStop(1,"rgba(129,140,248,0.6)");return g;}',
                    'borderColor' => '#6366F1',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
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
                        'maxTicksLimit' => 10,
                        'font' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color' => '#94a3b8',
                    ],
                ],
                'y' => [
                    'grid' => ['color' => 'rgba(255,255,255,0.06)', 'drawBorder' => false],
                    'ticks' => [
                        'precision' => 0,
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
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
