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
                    'backgroundColor' => '#0B3A68',
                    'borderRadius' => 2,
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
                    'grid' => ['color' => 'rgba(0,0,0,0.04)', 'drawBorder' => false],
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
