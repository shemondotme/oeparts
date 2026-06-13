<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Enums\OrderStatus;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ActivityOverviewWidget extends Widget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Recent admin activity across the panel';
    }

    protected string $view = 'filament.widgets.activity-overview-widget';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected ?string $heading = 'Recent Activity';

    protected static ?int $sort = -36;

    protected function getViewData(): array
    {
        try {
            $data = $this->cachedWidgetData(fn (): array => $this->computeOverview());
        } catch (\Exception $e) {
            report($e);
            $data = [
                'totalOrders' => 0,
                'totalCustomers' => 0,
                'activeProducts' => 0,
                'monthRevenue' => '0.00',
                'lowStock' => 0,
                'completionRate' => 0,
                'orderSparkline' => [],
                'revenueSparkline' => [],
                'customerSparkline' => [],
            ];
        }

        return $data + ['periodLabel' => $this->periodLabel()];
    }

    private function computeOverview(): array
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $sparklineStart = $this->periodStart();

        $totalOrders = Order::count();
        $completedCount = Order::whereIn('status', [OrderStatus::Shipped->value, OrderStatus::Delivered->value])->count();

        return [
            'totalOrders' => $totalOrders,
            'totalCustomers' => User::count(),
            'activeProducts' => Product::where('is_active', true)->count(),
            // Cast to string: DECIMAL sums must stay in string form until
            // format_money — no float arithmetic is performed on this value.
            'monthRevenue' => (string) Order::whereIn('status', $paidStatuses)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('grand_total'),
            'lowStock' => Product::where('is_in_stock', false)
                ->where('is_active', true)
                ->count(),
            // Counts, not money — float math acceptable here.
            'completionRate' => $totalOrders > 0 ? round(($completedCount / $totalOrders) * 100) : 100,
            'orderSparkline' => Order::where('created_at', '>=', $sparklineStart)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count')
                ->toArray(),
            // Chart points only — values cast to string at fetch, converted
            // to float solely at the rendering boundary.
            'revenueSparkline' => array_map(
                'strval',
                Order::whereIn('status', $paidStatuses)
                    ->where('created_at', '>=', $sparklineStart)
                    ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(grand_total) as total'))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('total')
                    ->toArray(),
            ),
            'customerSparkline' => User::where('created_at', '>=', $sparklineStart)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count')
                ->toArray(),
        ];
    }
}
