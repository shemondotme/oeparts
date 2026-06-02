<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class CustomerGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Customer Growth';

    protected static ?string $maxWidth = '2/3';

    public string $period = '30';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = Trend::model(User::class)
            ->between(
                start: now()->subDays((int) $this->period),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'New Customers',
                    'data' => $data->map(fn (TrendValue $v) => $v->aggregate),
                    'backgroundColor' => '#0B3A68',
                    'borderRadius' => 2,
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
                        'maxTicksLimit' => 10,
                        'font' => ['size' => 11],
                    ],
                ],
                'y' => [
                    'grid' => ['color' => 'rgba(0,0,0,0.05)'],
                    'ticks' => [
                        'precision' => 0,
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
