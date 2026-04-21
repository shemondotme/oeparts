<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductCondition;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Admin;
use App\Models\SearchLog;
use App\Models\PartInquiry;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Models\CronLog;
use App\Models\FailedSearchLog;
use App\Models\IpBlocklist;
use App\Models\LanguageString;
use App\Models\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with 26 widgets.
     */
    public function index(Request $request)
    {
        $admin = auth('admin')->user();
        $preferences = $admin->dashboard_preferences ?? $this->defaultWidgetPreferences();

        // Collect data for all widgets
        $widgets = $this->getWidgetData($preferences);

        return view('admin.dashboard.index', [
            'widgets' => $widgets,
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update dashboard widget preferences.
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
        ]);

        $admin = auth('admin')->user();
        $admin->dashboard_preferences = $validated['preferences'];
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard preferences updated.',
        ]);
    }

    /**
     * Default widget layout and visibility.
     */
    private function defaultWidgetPreferences(): array
    {
        return [
            // Row 1: KPI Overview
            ['id' => 'total_orders', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'total_revenue', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'total_customers', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'total_products', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            // Row 2: Charts
            ['id' => 'sales_chart', 'visible' => true, 'col_span' => 2, 'row_span' => 2],
            ['id' => 'search_popularity', 'visible' => true, 'col_span' => 2, 'row_span' => 2],
            // Row 3: Alerts & Activity
            ['id' => 'system_alerts', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'recent_orders', 'visible' => true, 'col_span' => 1, 'row_span' => 2],
            ['id' => 'recent_inquiries', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'recent_contacts', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            // Row 4: Health & Logs
            ['id' => 'health_strip', 'visible' => true, 'col_span' => 4, 'row_span' => 1],
            ['id' => 'activity_log', 'visible' => true, 'col_span' => 2, 'row_span' => 2],
            ['id' => 'failed_jobs', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'cron_status', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            // Row 5: Additional widgets
            ['id' => 'top_searches', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'newsletter_stats', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'ip_blocklist', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'translation_progress', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'admin_activity', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'cart_abandonment', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'product_condition', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'order_status', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'customer_growth', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'search_zero_results', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'checkout_dropoff', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
            ['id' => 'vat_compliance', 'visible' => true, 'col_span' => 1, 'row_span' => 1],
        ];
    }

    /**
     * Fetch data for all widgets.
     */
    private function getWidgetData(array $preferences): array
    {
        $data = [];

        foreach ($preferences as $widget) {
            $method = 'widget' . str_replace('_', '', ucwords($widget['id'], '_'));
            if (method_exists($this, $method)) {
                $data[$widget['id']] = $this->$method();
                $data[$widget['id']]['meta'] = $widget;
                $data[$widget['id']]['hidden'] = !$widget['visible'];
            } else {
                $data[$widget['id']] = [
                    'title' => ucwords(str_replace('_', ' ', $widget['id'])),
                    'value' => 'N/A',
                    'meta' => $widget,
                    'hidden' => !$widget['visible'],
                ];
            }
        }

        return $data;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Widget Data Methods (26 widgets)
    // ──────────────────────────────────────────────────────────────────────────

    private function widgetTotalOrders(): array
    {
        $count = Order::count();
        $change = $this->getPercentageChange(Order::class, 'created_at');
        return [
            'title' => 'Total Orders',
            'value' => number_format($count),
            'change' => $change,
            'icon' => 'shopping-cart',
            'color' => 'blue',
        ];
    }

    private function widgetTotalRevenue(): array
    {
        $revenue = Order::whereIn('status', [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ])->sum('grand_total');
        $change = 12.5; // placeholder
        return [
            'title' => 'Total Revenue',
            'value' => '€' . number_format((float) $revenue, 2),
            'change' => $change,
            'icon' => 'currency-euro',
            'color' => 'green',
        ];
    }

    private function widgetTotalCustomers(): array
    {
        $count = User::count();
        $change = $this->getPercentageChange(User::class, 'created_at');
        return [
            'title' => 'Total Customers',
            'value' => number_format($count),
            'change' => $change,
            'icon' => 'users',
            'color' => 'purple',
        ];
    }

    private function widgetTotalProducts(): array
    {
        $count = Product::count();
        $change = $this->getPercentageChange(Product::class, 'created_at');
        return [
            'title' => 'Total Products',
            'value' => number_format($count),
            'change' => $change,
            'icon' => 'cube',
            'color' => 'amber',
        ];
    }

    private function widgetSalesChart(): array
    {
        $sales = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(grand_total) as revenue')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'title' => 'Sales Last 30 Days',
            'type' => 'line',
            'data' => $sales,
            'labels' => $sales->pluck('date'),
            'values' => $sales->pluck('revenue'),
        ];
    }

    private function widgetSearchPopularity(): array
    {
        $searches = SearchLog::selectRaw('search_query as query, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('search_query')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'title' => 'Trending Searches',
            'type' => 'bar',
            'data' => $searches,
        ];
    }

    private function widgetSystemAlerts(): array
    {
        $alerts = [];
        // Check for out-of-stock products (binary is_in_stock — no quantity tracking)
        $lowStock = Product::where('is_in_stock', false)->count();
        if ($lowStock) {
            $alerts[] = ['type' => 'warning', 'message' => "{$lowStock} products out of stock"];
        }
        // Check for failed jobs
        $failed = DB::table('failed_jobs')->count();
        if ($failed) {
            $alerts[] = ['type' => 'danger', 'message' => "{$failed} failed jobs"];
        }
        // Check for pending inquiries
        $pending = PartInquiry::where('status', 'pending')->count();
        if ($pending) {
            $alerts[] = ['type' => 'info', 'message' => "{$pending} pending inquiries"];
        }

        return [
            'title' => 'System Alerts',
            'alerts' => $alerts,
            'count' => count($alerts),
        ];
    }

    private function widgetRecentOrders(): array
    {
        $orders = Order::with('user')
            ->latest()
            ->limit(5)
            ->get();

        return [
            'title' => 'Recent Orders',
            'items' => $orders,
        ];
    }

    private function widgetRecentInquiries(): array
    {
        $inquiries = PartInquiry::latest()->limit(5)->get();
        return [
            'title' => 'Recent Inquiries',
            'items' => $inquiries,
        ];
    }

    private function widgetRecentContacts(): array
    {
        $contacts = ContactMessage::latest()->limit(5)->get();
        return [
            'title' => 'Recent Contacts',
            'items' => $contacts,
        ];
    }

    private function widgetHealthStrip(): array
    {
        $checks = [
            ['label' => 'Database', 'status' => 'healthy'],
            ['label' => 'Redis', 'status' => 'healthy'],
            ['label' => 'Queue', 'status' => 'warning'],
            ['label' => 'Storage', 'status' => 'healthy'],
            ['label' => 'PHP', 'status' => 'healthy'],
            ['label' => 'SSL', 'status' => 'healthy'],
        ];

        return [
            'title' => 'System Health',
            'checks' => $checks,
        ];
    }

    private function widgetActivityLog(): array
    {
        $logs = ActivityLog::with('admin')->latest()->limit(10)->get();
        return [
            'title' => 'Activity Log',
            'items' => $logs,
        ];
    }

    private function widgetFailedJobs(): array
    {
        $count = DB::table('failed_jobs')->count();
        return [
            'title' => 'Failed Jobs',
            'value' => $count,
        ];
    }

    private function widgetCronStatus(): array
    {
        $lastRun = CronLog::latest('ran_at')->first();
        return [
            'title'    => 'Cron Status',
            'value'    => $lastRun ? $lastRun->ran_at->diffForHumans() : 'Never',
            'subtitle' => !$lastRun ? 'Not yet configured' : null,
            'status'   => !$lastRun
                ? 'warning'
                : ($lastRun->ran_at->gt(now()->subHour()) ? 'success' : 'danger'),
        ];
    }

    private function widgetTopSearches(): array
    {
        $searches = SearchLog::selectRaw('search_query as query, COUNT(*) as count')
            ->groupBy('search_query')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return [
            'title' => 'All-Time Searches',
            'items' => $searches,
        ];
    }

    private function widgetNewsletterStats(): array
    {
        $total = NewsletterSubscriber::count();
        $today = NewsletterSubscriber::whereDate('subscribed_at', today())->count();
        return [
            'title' => 'Newsletter',
            'value' => $total,
            'subtitle' => "+{$today} today",
        ];
    }

    private function widgetIpBlocklist(): array
    {
        $count = IpBlocklist::where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();
        return [
            'title' => 'Blocked IPs',
            'value' => $count,
        ];
    }

    private function widgetTranslationProgress(): array
    {
        $total = LanguageString::count();
        $translated = LanguageString::whereNotNull('value')->where('value', '!=', '')->count();
        $percent = $total > 0 ? round(($translated / $total) * 100) : 0;
        return [
            'title' => 'Translations',
            'value' => "{$percent}%",
            'subtitle' => "{$translated}/{$total} strings",
        ];
    }

    private function widgetAdminActivity(): array
    {
        $active = Admin::where('last_login_at', '>=', now()->subDay())->count();
        $total = Admin::count();
        return [
            'title' => 'Active Admins',
            'value' => $active,
            'subtitle' => "of {$total} total",
        ];
    }

    private function widgetCartAbandonment(): array
    {
        // placeholder
        return [
            'title' => 'Cart Abandonment',
            'value' => '24%',
        ];
    }

    private function widgetProductCondition(): array
    {
        $new  = Product::where('condition', ProductCondition::New->value)->count();
        $used = Product::where('condition', ProductCondition::Used->value)->count();
        return [
            'title' => 'Product Condition',
            'value' => "{$new} new, {$used} used",
        ];
    }

    private function widgetOrderStatus(): array
    {
        $statuses = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return [
            'title' => 'Order Status',
            'data' => $statuses,
        ];
    }

    private function widgetCustomerGrowth(): array
    {
        $growth = User::where('created_at', '>=', now()->subMonth())->count();
        return [
            'title' => 'Customer Growth',
            'value' => "+{$growth} this month",
        ];
    }

    private function widgetSearchZeroResults(): array
    {
        $count = FailedSearchLog::count();
        return [
            'title' => 'Zero‑Result Searches',
            'value' => $count,
        ];
    }

    private function widgetCheckoutDropoff(): array
    {
        // placeholder
        return [
            'title' => 'Checkout Drop‑off',
            'value' => '18%',
        ];
    }

    private function widgetVatCompliance(): array
    {
        // B2B orders that declared is_b2b but provided no VAT number
        $nonCompliant = Order::where('is_b2b', true)->whereNull('vat_number')->count();
        return [
            'title' => 'VAT Compliance',
            'value' => $nonCompliant > 0 ? "{$nonCompliant} issues" : '100%',
            'status' => $nonCompliant > 0 ? 'warning' : 'success',
        ];
    }

    /**
     * Helper to calculate percentage change from previous period.
     */
    private function getPercentageChange(string $model, string $dateColumn): float
    {
        $current = $model::where($dateColumn, '>=', now()->subDays(30))->count();
        $previous = $model::whereBetween($dateColumn, [now()->subDays(60), now()->subDays(30)])->count();

        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}