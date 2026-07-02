<?php

namespace App\Filament\Widgets\Reports;

use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomersGrowthChart extends ChartWidget
{
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected ?string $heading = 'Customer Growth';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();

        $data = User::where('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as c'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('c', 'date')
            ->toArray();

        return [
            'datasets' => [[
                'label' => 'New customers',
                'data' => array_map('intval', array_values($data)),
                'borderColor' => '#F59E0B',
                'backgroundColor' => 'transparent',
                'fill' => false,
                'tension' => 0.4,
                'pointRadius' => 3,
                'pointHoverRadius' => 6,
                'borderWidth' => 2,
            ]],
            'labels' => array_keys($data),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'maintainAspectRatio' => false,
        ];
    }
}
