<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomerGrowthChart extends ChartWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasPeriodFilterPills;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected ?string $heading = 'Customer Growth';

    // Segmented pill period selector (the global period control).
    protected string $view = 'filament.widgets.chart-with-period';

    // Eager: async-alpine never initializes charts on lazily-morphed HTML (see RevenueChart).
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();

        $data = User::where('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'New Customers',
                    'data' => $data->values()->all(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'transparent',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
