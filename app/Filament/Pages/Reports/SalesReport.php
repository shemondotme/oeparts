<?php

namespace App\Filament\Pages\Reports;

use App\Models\Order;
use App\Models\OrderItem;
use App\Filament\Clusters\Reports;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class SalesReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Sales Report';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action('exportCsv'),
        ];
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $data = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $start)
            ->select(
                'orders.id as order_id',
                'orders.created_at',
                'orders.status',
                'orders.grand_total',
                'order_items.oem_number_snapshot',
                'order_items.quantity',
                'order_items.total_price'
            )
            ->orderBy('orders.created_at', 'desc')
            ->get();

        $filename = 'sales-report-' . now()->format('Y-m-d') . '.csv';

        return Response::stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order ID', 'Date', 'Status', 'Grand Total', 'OEM Number', 'Qty', 'Line Total']);
            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->order_id,
                    $row->created_at?->format('Y-m-d H:i'),
                    $row->status,
                    number_format((float) bcadd((string) $row->grand_total, '0', 2), 2, '.', ''),
                    $row->oem_number_snapshot,
                    $row->quantity,
                    number_format((float) bcadd((string) $row->total_price, '0', 2), 2, '.', ''),
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function getRevenue(): string
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $revenue = Order::where('status', '!=', 'cancelled')
            ->where('status', '!=', 'refunded')
            ->where('created_at', '>=', $start)
            ->sum('grand_total');

        return number_format((float) bcadd((string) $revenue, '0', 2), 2, '.', '');
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

        return number_format((float) bcadd((string) $avg, '0', 2), 2, '.', '');
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
            'values' => array_map(fn ($v) => number_format((float) bcadd((string) $v, '0', 2), 2, '.', ''), array_values($data)),
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
            $p['total_revenue'] = number_format((float) bcadd((string) $p['total_revenue'], '0', 2), 2, '.', '');
            return $p;
        }, $products);
    }
}
