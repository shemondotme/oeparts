<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\SearchLog;
use App\Models\FailedSearchLog;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display the main reports dashboard.
     */
    public function index()
    {
        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->endOfDay();
        $successfulSearches = SearchLog::query()
            ->where('result_count', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $failedSearches = FailedSearchLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $totalSearches = $successfulSearches + $failedSearches;

        return view('admin.reports.index', [
            'reportTypes' => [
                'sales' => 'Sales Reports',
                'customers' => 'Customer Reports',
                'search' => 'Search Analytics',
                'checkout' => 'Checkout Drop-off',
            ],
            'dateRanges' => $this->getDateRanges(),
            'quickStats' => [
                'revenue' => Order::query()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('grand_total'),
                'orders' => Order::query()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'customers' => User::query()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'search_success_rate' => $totalSearches > 0
                    ? round(($successfulSearches / $totalSearches) * 100, 2)
                    : 0,
            ],
        ]);
    }

    /**
     * Generate sales reports.
     */
    public function sales(Request $request)
    {
        $dateRange = $request->input('date_range', 'last_30_days');
        $startDate = $this->parseDateRange($dateRange)['start'];
        $endDate = $this->parseDateRange($dateRange)['end'];

        // Sales summary
        $salesSummary = Order::query()
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(grand_total) as total_revenue,
                AVG(grand_total) as average_order_value,
                SUM(CASE WHEN status = "delivered" THEN grand_total ELSE 0 END) as completed_revenue,
                SUM(CASE WHEN status = "pending" THEN grand_total ELSE 0 END) as pending_revenue,
                SUM(CASE WHEN status = "cancelled" THEN grand_total ELSE 0 END) as cancelled_revenue
            ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->first();

        // Daily sales trend
        $dailySales = Order::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(grand_total) as revenue')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top products by revenue
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('
                products.oem_number,
                products.name,
                COUNT(order_items.id) as units_sold,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.total_price) as total_revenue
            ')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('products.id', 'products.oem_number', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Payment method breakdown
        $paymentMethods = Order::query()
            ->selectRaw('payment_method, COUNT(*) as count, SUM(grand_total) as revenue')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get();

        return view('admin.reports.sales', [
            'dateRange' => $dateRange,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'salesSummary' => $salesSummary,
            'dailySales' => $dailySales,
            'topProducts' => $topProducts,
            'paymentMethods' => $paymentMethods,
            'dateRanges' => $this->getDateRanges(),
        ]);
    }

    /**
     * Generate customer reports.
     */
    public function customers(Request $request)
    {
        $dateRange = $request->input('date_range', 'last_30_days');
        $startDate = $this->parseDateRange($dateRange)['start'];
        $endDate = $this->parseDateRange($dateRange)['end'];

        // Customer growth
        $customerGrowth = User::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as new_customers')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Customer demographics - using user status instead of country
        $customerStatus = User::query()
            ->selectRaw('is_active, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('is_active')
            ->orderByDesc('count')
            ->get();

        // Customer lifetime value
        $customerLTV = Order::query()
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.email,
                COUNT(orders.id) as total_orders,
                SUM(orders.grand_total) as total_spent,
                MAX(orders.created_at) as last_order_date
            ')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('users.id', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(20)
            ->get();

        // Repeat customers vs new customers
        $repeatCustomers = Order::query()
            ->selectRaw('
                user_id,
                COUNT(*) as order_count,
                CASE WHEN COUNT(*) > 1 THEN "repeat" ELSE "new" END as customer_type
            ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->get();

        $repeatCount = $repeatCustomers->where('order_count', '>', 1)->count();
        $newCount = $repeatCustomers->where('order_count', 1)->count();

        return view('admin.reports.customers', [
            'dateRange' => $dateRange,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'customerGrowth' => $customerGrowth,
            'customerStatus' => $customerStatus,
            'customerLTV' => $customerLTV,
            'repeatCount' => $repeatCount,
            'newCount' => $newCount,
            'totalCustomers' => $repeatCustomers->count(),
            'dateRanges' => $this->getDateRanges(),
        ]);
    }

    /**
     * Generate search analytics reports.
     */
    public function search(Request $request)
    {
        $dateRange = $request->input('date_range', 'last_30_days');
        $startDate = $this->parseDateRange($dateRange)['start'];
        $endDate = $this->parseDateRange($dateRange)['end'];

        // Search volume trend
        $searchVolume = SearchLog::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as searches')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top searches
        $topSearches = SearchLog::query()
            ->selectRaw('search_query, COUNT(*) as search_count, AVG(result_count) as avg_results')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('search_query')
            ->orderByDesc('search_count')
            ->limit(20)
            ->get();

        // Search success rate
        $successfulSearches = SearchLog::query()
            ->where('result_count', '>', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $failedSearches = FailedSearchLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalSearches = $successfulSearches + $failedSearches;
        $successRate = $totalSearches > 0 ? ($successfulSearches / $totalSearches) * 100 : 0;

        // Search to order conversion (simplified for SQLite compatibility)
        $totalSearchesCount = SearchLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        
        $resultingOrdersCount = 0;
        if ($totalSearchesCount > 0) {
            // Simple approximation: count orders from users who searched in the same period
            $resultingOrdersCount = Order::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('user_id', function ($query) use ($startDate, $endDate) {
                    $query->select('user_id')
                        ->from('search_logs')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->whereNotNull('user_id');
                })
                ->count();
        }
        
        $conversionRate = $totalSearchesCount > 0 ? ($resultingOrdersCount * 100.0 / $totalSearchesCount) : 0;
        
        $searchToOrder = (object) [
            'total_searches' => $totalSearchesCount,
            'resulting_orders' => $resultingOrdersCount,
            'conversion_rate' => $conversionRate,
        ];

        return view('admin.reports.search', [
            'dateRange' => $dateRange,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'searchVolume' => $searchVolume,
            'topSearches' => $topSearches,
            'successfulSearches' => $successfulSearches,
            'failedSearches' => $failedSearches,
            'totalSearches' => $totalSearches,
            'successRate' => $successRate,
            'searchToOrder' => $searchToOrder,
            'dateRanges' => $this->getDateRanges(),
        ]);
    }

    /**
     * Generate checkout drop-off reports.
     */
    public function checkout(Request $request)
    {
        $dateRange = $request->input('date_range', 'last_30_days');
        $startDate = $this->parseDateRange($dateRange)['start'];
        $endDate = $this->parseDateRange($dateRange)['end'];

        // Cart abandonment analysis
        $cartAbandonment = Cart::query()
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_carts
            ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Orders created from carts (conversion)
        $orderConversion = Order::query()
            ->selectRaw('
                DATE(orders.created_at) as date,
                COUNT(*) as completed_orders
            ')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Combine cart abandonment and order conversion data
        $checkoutData = [];
        foreach ($cartAbandonment as $cart) {
            $order = $orderConversion->firstWhere('date', $cart->date);
            $checkoutData[] = (object) [
                'date' => $cart->date,
                'total_carts' => $cart->total_carts,
                'completed_orders' => $order ? $order->completed_orders : 0,
                'abandonment_rate' => $cart->total_carts > 0
                    ? round((($cart->total_carts - ($order ? $order->completed_orders : 0)) / $cart->total_carts) * 100, 2)
                    : 0,
            ];
        }

        // Checkout steps analysis (simplified - using order status)
        $checkoutSteps = Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        return view('admin.reports.checkout', [
            'dateRange' => $dateRange,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'checkoutData' => $checkoutData,
            'checkoutSteps' => $checkoutSteps,
            'totalCarts' => $cartAbandonment->sum('total_carts'),
            'totalOrders' => $orderConversion->sum('completed_orders'),
            'overallAbandonmentRate' => $cartAbandonment->sum('total_carts') > 0
                ? round((($cartAbandonment->sum('total_carts') - $orderConversion->sum('completed_orders')) / $cartAbandonment->sum('total_carts')) * 100, 2)
                : 0,
            'dateRanges' => $this->getDateRanges(),
        ]);
    }

    /**
     * Export report data.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:sales,customers,search,checkout',
            'format' => 'nullable|string|in:csv',
            'date_range' => 'nullable|string',
        ]);

        $type = $validated['type'];
        $dateRange = $request->input('date_range', 'last_30_days');
        $range = $this->parseDateRange($dateRange);
        $filename = sprintf('%s-report-%s.csv', $type, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($type, $range) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($this->exportRows($type, $range['start'], $range['end']) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportRows(string $type, Carbon $startDate, Carbon $endDate): array
    {
        return match ($type) {
            'sales' => $this->salesExportRows($startDate, $endDate),
            'customers' => $this->customerExportRows($startDate, $endDate),
            'search' => $this->searchExportRows($startDate, $endDate),
            'checkout' => $this->checkoutExportRows($startDate, $endDate),
        };
    }

    private function salesExportRows(Carbon $startDate, Carbon $endDate): array
    {
        $rows = [[
            'order_number',
            'status',
            'payment_status',
            'subtotal',
            'shipping_cost',
            'vat_amount',
            'grand_total',
            'created_at',
        ]];

        Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->chunk(200, function ($orders) use (&$rows) {
                foreach ($orders as $order) {
                    $rows[] = [
                        $order->order_number,
                        $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                        $order->payment_status instanceof \BackedEnum ? $order->payment_status->value : $order->payment_status,
                        $order->subtotal,
                        $order->shipping_cost,
                        $order->vat_amount,
                        $order->grand_total,
                        optional($order->created_at)->toDateTimeString(),
                    ];
                }
            });

        return $rows;
    }

    private function customerExportRows(Carbon $startDate, Carbon $endDate): array
    {
        $rows = [['email', 'name', 'is_active', 'created_at']];

        User::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->chunk(200, function ($users) use (&$rows) {
                foreach ($users as $user) {
                    $rows[] = [
                        $user->email,
                        $user->name,
                        $user->is_active ? 'active' : 'inactive',
                        optional($user->created_at)->toDateTimeString(),
                    ];
                }
            });

        return $rows;
    }

    private function searchExportRows(Carbon $startDate, Carbon $endDate): array
    {
        $rows = [['search_query', 'normalized_query', 'result_count', 'lang', 'created_at']];

        SearchLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->chunk(200, function ($searches) use (&$rows) {
                foreach ($searches as $search) {
                    $rows[] = [
                        $search->search_query,
                        $search->normalized_query,
                        $search->result_count,
                        $search->lang,
                        optional($search->created_at)->toDateTimeString(),
                    ];
                }
            });

        return $rows;
    }

    private function checkoutExportRows(Carbon $startDate, Carbon $endDate): array
    {
        $rows = [['date', 'total_carts', 'completed_orders']];
        $carts = Cart::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_carts')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $orders = Order::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as completed_orders')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->pluck('completed_orders', 'date');

        foreach ($carts as $cart) {
            $rows[] = [
                $cart->date,
                $cart->total_carts,
                $orders[$cart->date] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * Get available date ranges for reports.
     */
    private function getDateRanges(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_quarter' => 'This Quarter',
            'last_quarter' => 'Last Quarter',
            'this_year' => 'This Year',
            'last_year' => 'Last Year',
            'custom' => 'Custom Range',
        ];
    }

    /**
     * Parse date range string into start and end dates.
     */
    private function parseDateRange(string $range): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        switch ($range) {
            case 'today':
                return [
                    'start' => $today->copy()->startOfDay(),
                    'end' => $today->copy()->endOfDay(),
                ];
            case 'yesterday':
                return [
                    'start' => $yesterday->copy()->startOfDay(),
                    'end' => $yesterday->copy()->endOfDay(),
                ];
            case 'last_7_days':
                return [
                    'start' => $today->copy()->subDays(7)->startOfDay(),
                    'end' => $today->copy()->endOfDay(),
                ];
            case 'last_30_days':
                return [
                    'start' => $today->copy()->subDays(30)->startOfDay(),
                    'end' => $today->copy()->endOfDay(),
                ];
            case 'this_month':
                return [
                    'start' => $today->copy()->startOfMonth(),
                    'end' => $today->copy()->endOfMonth(),
                ];
            case 'last_month':
                return [
                    'start' => $today->copy()->subMonth()->startOfMonth(),
                    'end' => $today->copy()->subMonth()->endOfMonth(),
                ];
            case 'this_quarter':
                return [
                    'start' => $today->copy()->startOfQuarter(),
                    'end' => $today->copy()->endOfQuarter(),
                ];
            case 'last_quarter':
                return [
                    'start' => $today->copy()->subQuarter()->startOfQuarter(),
                    'end' => $today->copy()->subQuarter()->endOfQuarter(),
                ];
            case 'this_year':
                return [
                    'start' => $today->copy()->startOfYear(),
                    'end' => $today->copy()->endOfYear(),
                ];
            case 'last_year':
                return [
                    'start' => $today->copy()->subYear()->startOfYear(),
                    'end' => $today->copy()->subYear()->endOfYear(),
                ];
            default:
                return [
                    'start' => $today->copy()->subDays(30)->startOfDay(),
                    'end' => $today->copy()->endOfDay(),
                ];
        }
    }
}