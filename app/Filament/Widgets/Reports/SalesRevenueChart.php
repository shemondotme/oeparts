<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Native daily-revenue trend for the Sales report (was an embedded dashboard
 * chart). Reads the report page's selected $period, passed in on mount.
 */
class SalesRevenueChart extends ChartWidget
{
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected ?string $heading = 'Revenue Trend';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $start = $this->periodStart();

            $data = Order::whereNotIn('status', ['cancelled', 'refunded'])
                ->where('created_at', '>=', $start)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(grand_total) as total'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date')
                ->toArray();

            return [
                'values' => array_map(fn ($v) => (float) $v, array_values($data)),
                'labels' => array_keys($data),
            ];
        });

        return [
            'datasets' => [[
                'label' => 'Revenue (€)',
                'data' => $d['values'],
                'borderColor' => '#F59E0B',
                'backgroundColor' => 'transparent',
                'fill' => false,
                'tension' => 0.4,
                'pointRadius' => 3,
                'pointHoverRadius' => 6,
                'borderWidth' => 2,
            ]],
            'labels' => $d['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'maintainAspectRatio' => false,
        ];
    }
}
