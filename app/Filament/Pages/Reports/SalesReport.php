<?php

namespace App\Filament\Pages\Reports;

use App\Models\Order;
use App\Models\OrderItem;
use App\Filament\Clusters\Reports;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Sales Report';

    protected ?string $subheading = 'Store sales, order counts, and revenue performance.';

    protected string $view = 'filament.pages.reports.sales-report';

    public string $period = '30';

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return 'Sales Report';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public function getRevenue(): string
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $revenue = Order::where('status', '!=', 'cancelled')
            ->where('status', '!=', 'refunded')
            ->where('created_at', '>=', $start)
            ->sum('grand_total');

        return number_format((float) $revenue, 2, '.', '');
    }

    public function getOrderCount(): int
    {
        $start = Carbon::now()->subDays((int) $this->period);

        return Order::where('created_at', '>=', $start)->count();
    }

    public function getAvgOrderValue(): string
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $avg = Order::where('status', '!=', 'cancelled')
            ->where('status', '!=', 'refunded')
            ->where('created_at', '>=', $start)
            ->avg('grand_total');

        return number_format((float) $avg, 2, '.', '');
    }

    public function getDailyRevenue(): array
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $data = Order::where('status', '!=', 'cancelled')
            ->where('status', '!=', 'refunded')
            ->where('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(grand_total) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        return [
            'labels' => array_keys($data),
            'values' => array_map(fn ($v) => number_format((float) $v, 2, '.', ''), array_values($data)),
        ];
    }

    public function getTopProducts(): array
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $products = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $start)
            ->select('order_items.product_id', DB::raw('COALESCE(order_items.oem_number_snapshot, order_items.manufacturer_snapshot, CAST(order_items.product_id AS CHAR)) as name'), DB::raw('SUM(order_items.quantity) as total_qty'), DB::raw('SUM(order_items.total_price) as total_revenue'))
            ->groupBy('order_items.product_id', 'order_items.oem_number_snapshot', 'order_items.manufacturer_snapshot')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->toArray();

        return array_map(function ($p) {
            $p['total_revenue'] = number_format((float) $p['total_revenue'], 2, '.', '');
            return $p;
        }, $products);
    }
}
