<?php

namespace App\Filament\Widgets\Reports;

use App\Models\AbandonedCart;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class CheckoutFunnelChart extends ChartWidget
{
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected ?string $heading = 'Checkout Funnel';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $start = $this->periodStart();

            $abandoned = AbandonedCart::where('created_at', '>=', $start)->count();
            $completed = Order::where('created_at', '>=', $start)->count();
            $started = $completed + $abandoned;
            $paid = Order::where('created_at', '>=', $start)
                ->whereIn('status', ['paid', 'shipped', 'delivered'])
                ->count();
            $cancelled = Order::where('created_at', '>=', $start)
                ->where('status', 'cancelled')
                ->count();

            return compact('started', 'completed', 'paid', 'cancelled', 'abandoned');
        });

        return [
            'datasets' => [[
                'label' => 'Count',
                'data' => [$d['started'], $d['completed'], $d['paid'], $d['cancelled'], $d['abandoned']],
                'backgroundColor' => ['#F59E0B', '#3B82F6', '#22C55E', '#EF4444', '#71717B'],
                'borderRadius' => 6,
                'borderSkipped' => false,
            ]],
            'labels' => ['Started', 'Completed', 'Paid', 'Cancelled', 'Abandoned'],
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
