<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Log;

/**
 * Widget registry — drives role access, cache TTL, period filtering, and visibility.
 *
 * Single source of truth for group membership (via 'group' key in each widget entry).
 * Avoids the old dual-source-of-truth bug with WIDGET_TABS and TAB_BLUEPRINT_LAYOUTS.
 *
 * The '_meta' key inside admins.dashboard_preferences is reserved for
 * non-widget state (global period) and must never be treated as a widget id.
 */
class WidgetPreferenceService
{
    public const META_KEY = '_meta';

    /** Widget groups — intuitive categories for the dashboard. */
    public const GROUP_SLUGS = [
        'business-overview' => 'Business Overview',
        'needs-attention'   => 'Needs Attention',
        'live-activity'     => 'Live Activity',
        'catalog-search'    => 'Catalog & Search',
        'system-health'     => 'System Health',
    ];

    /** Widgets that are always visible, never shown in preferences. */
    public const ALWAYS_ON = ['dashboard_header', 'health_strip'];

    /** Management roles — full commercial visibility. */
    private const MGMT = ['super_admin', 'admin', 'manager'];

    /**
     * Operational queues that support staff also action (orders awaiting
     * confirmation, refunds, customer messages, recent orders). Management
     * plus support — keeps these widgets reachable for the support role,
     * whose ROLE_DEFAULT_ON lists them. Without support here, getWidgets()
     * would filter them out before visibility is ever checked.
     */
    private const OPS = ['super_admin', 'admin', 'manager', 'support'];

    /** System/observability widgets — operators only. */
    private const SYSTEM = ['super_admin', 'admin'];

    /** Catalog roles */
    private const CATALOG = ['super_admin', 'admin', 'manager', 'catalog_admin'];

    public const WIDGETS = [

        // ── Always-on (never in preferences) ────────────────────────────

        'dashboard_header' => [
            'class' => \App\Filament\Widgets\DashboardHeader::class,
            'label' => 'Dashboard Header',
            'description' => "Today's revenue, orders, and customer KPIs",
            'group' => 'business-overview',
            'default_visible' => true,
            'default_sort' => 0,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'health_strip' => [
            'class' => \App\Filament\Widgets\HealthStrip::class,
            'label' => 'System Health Strip',
            'description' => 'Database, Redis, queue, and storage status',
            'group' => 'system-health',
            'default_visible' => true,
            'default_sort' => 0,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],

        // ── Business Overview ───────────────────────────────────────────

        'order_stats_overview' => [
            'class' => \App\Filament\Widgets\OrderStatsOverview::class,
            'label' => 'Order Stats Overview',
            'description' => 'Revenue, orders, customers, and average order value',
            'group' => 'business-overview',
            'default_visible' => true,
            'default_sort' => 1,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'revenue_chart' => [
            'class' => \App\Filament\Widgets\RevenueChart::class,
            'label' => 'Revenue Chart',
            'description' => 'Monthly revenue trend over the selected period',
            'group' => 'business-overview',
            'default_visible' => true,
            'default_sort' => 2,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'order_volume_chart' => [
            'class' => \App\Filament\Widgets\OrderVolumeChart::class,
            'label' => 'Order Volume Chart',
            'description' => 'Number of orders over the selected period',
            'group' => 'business-overview',
            'default_visible' => true,
            'default_sort' => 3,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'order_status_distribution' => [
            'class' => \App\Filament\Widgets\OrderStatusDistributionWidget::class,
            'label' => 'Order Status Distribution',
            'description' => 'Breakdown of orders by status (pending, shipped, delivered)',
            'group' => 'business-overview',
            'default_visible' => false,
            'default_sort' => 4,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'customer_growth' => [
            'class' => \App\Filament\Widgets\CustomerGrowthChart::class,
            'label' => 'Customer Growth Chart',
            'description' => 'New customer registrations over the selected period',
            'group' => 'business-overview',
            'default_visible' => false,
            'default_sort' => 5,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'manufacturer_revenue' => [
            'class' => \App\Filament\Widgets\TopManufacturersRevenue::class,
            'label' => 'Top Manufacturers by Revenue',
            'description' => 'Revenue breakdown by manufacturer',
            'group' => 'business-overview',
            'default_visible' => false,
            'default_sort' => 6,
            'roles' => self::CATALOG,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],

        // ── Needs Attention ─────────────────────────────────────────────

        'abandoned_carts' => [
            'class' => \App\Filament\Widgets\AbandonedCartWidget::class,
            'label' => 'Abandoned Carts',
            'description' => 'Carts left unpurchased — recovery opportunity',
            'group' => 'needs-attention',
            'default_visible' => true,
            'default_sort' => 7,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'awaiting_confirmation' => [
            'class' => \App\Filament\Widgets\AwaitingConfirmationList::class,
            'label' => 'Awaiting Confirmation',
            'description' => 'Paid orders awaiting fulfilment action',
            'group' => 'needs-attention',
            'default_visible' => true,
            'default_sort' => 8,
            'roles' => self::OPS,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],
        'refunds_pending' => [
            'class' => \App\Filament\Widgets\RefundsPendingList::class,
            'label' => 'Pending Refunds',
            'description' => 'Refund requests awaiting approval or processing',
            'group' => 'needs-attention',
            'default_visible' => true,
            'default_sort' => 9,
            'roles' => self::OPS,
            'financial' => true,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'new_messages' => [
            'class' => \App\Filament\Widgets\NewMessagesInbox::class,
            'label' => 'New Messages',
            'description' => 'Unread messages from customers and suppliers',
            'group' => 'needs-attention',
            'default_visible' => true,
            'default_sort' => 10,
            'roles' => self::OPS,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],
        'stock_alert' => [
            'class' => \App\Filament\Widgets\StockAlertWidget::class,
            'label' => 'Stock Alerts',
            'description' => 'Parts that are out of stock or running low',
            'group' => 'needs-attention',
            'default_visible' => true,
            'default_sort' => 11,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'failed_searches' => [
            'class' => \App\Filament\Widgets\FailedSearchesWidget::class,
            'label' => 'Failed Searches',
            'description' => 'OEM numbers customers searched but didn\'t find',
            'group' => 'needs-attention',
            'default_visible' => false,
            'default_sort' => 12,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'failed_queue_jobs' => [
            'class' => \App\Filament\Widgets\FailedQueueJobsMonitor::class,
            'label' => 'Failed Queue Jobs',
            'description' => 'Background jobs that failed and need review',
            'group' => 'needs-attention',
            'default_visible' => false,
            'default_sort' => 13,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],

        // ── Live Activity ───────────────────────────────────────────────

        'recent_orders' => [
            'class' => \App\Filament\Widgets\RecentOrdersList::class,
            'label' => 'Recent Orders',
            'description' => 'Latest orders from customers',
            'group' => 'live-activity',
            'default_visible' => true,
            'default_sort' => 14,
            'roles' => self::OPS,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],
        'parts_inquiry' => [
            'class' => \App\Filament\Widgets\PartsInquiryWidget::class,
            'label' => 'Parts Inquiry',
            'description' => 'Part requests pending your action',
            'group' => 'live-activity',
            'default_visible' => false,
            'default_sort' => 15,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'recent_activity' => [
            'class' => \App\Filament\Widgets\RecentActivityLog::class,
            'label' => 'Recent Activity',
            'description' => 'Activity log of recent admin actions and system events',
            'group' => 'live-activity',
            'default_visible' => false,
            'default_sort' => 16,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],
        'latest_customers' => [
            'class' => \App\Filament\Widgets\LatestCustomersWidget::class,
            'label' => 'Latest Customers',
            'description' => 'Most recent customer registrations',
            'group' => 'live-activity',
            'default_visible' => false,
            'default_sort' => 17,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],

        // ── Catalog & Search ────────────────────────────────────────────

        'new_products_added' => [
            'class' => \App\Filament\Widgets\NewProductsAdded::class,
            'label' => 'New Products Added',
            'description' => 'Recently added parts to your catalog',
            'group' => 'catalog-search',
            'default_visible' => false,
            'default_sort' => 18,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'top_searches' => [
            'class' => \App\Filament\Widgets\TopSearchedOems::class,
            'label' => 'Top Searched OEMs',
            'description' => 'Most-searched OEM numbers by customers',
            'group' => 'catalog-search',
            'default_visible' => false,
            'default_sort' => 19,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'manufacturing_stats' => [
            'class' => \App\Filament\Widgets\ManufacturingStatsWidget::class,
            'label' => 'Catalog Stats',
            'description' => 'Manufacturers, products, and stock coverage',
            'group' => 'catalog-search',
            'default_visible' => false,
            'default_sort' => 20,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],
        'newsletter_growth' => [
            'class' => \App\Filament\Widgets\NewsletterGrowthWidget::class,
            'label' => 'Newsletter Growth',
            'description' => 'Newsletter subscriber growth and engagement',
            'group' => 'catalog-search',
            'default_visible' => false,
            'default_sort' => 22,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
        ],

        // ── System Health ───────────────────────────────────────────────

        'disk_space' => [
            'class' => \App\Filament\Widgets\DiskSpaceWidget::class,
            'label' => 'Disk Space',
            'description' => 'Server disk usage and available space',
            'group' => 'system-health',
            'default_visible' => false,
            'default_sort' => 23,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::LONG_TTL,
        ],
        'cache_status' => [
            'class' => \App\Filament\Widgets\CacheStatusWidget::class,
            'label' => 'Cache Status',
            'description' => 'Redis cache hit rate and memory usage',
            'group' => 'system-health',
            'default_visible' => false,
            'default_sort' => 24,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],
        'request_metrics' => [
            'class' => \App\Filament\Widgets\RequestMetricsWidget::class,
            'label' => 'Activity Metrics',
            'description' => 'Queue, email, and search activity (last hour)',
            'group' => 'system-health',
            'default_visible' => false,
            'default_sort' => 26,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
        ],
    ];

    /** Role-specific default visibility for new admins. */
    public const ROLE_DEFAULT_ON = [
        'super_admin' => [
            'order_stats_overview',
            'revenue_chart',
            'order_volume_chart',
            'abandoned_carts',
            'awaiting_confirmation',
            'refunds_pending',
            'new_messages',
            'stock_alert',
            'recent_orders',
            'failed_queue_jobs',
        ],
        'admin' => [
            'order_stats_overview',
            'revenue_chart',
            'order_volume_chart',
            'abandoned_carts',
            'awaiting_confirmation',
            'refunds_pending',
            'new_messages',
            'stock_alert',
            'recent_orders',
            'failed_queue_jobs',
        ],
        'manager' => [
            'order_stats_overview',
            'revenue_chart',
            'order_volume_chart',
            'abandoned_carts',
            'awaiting_confirmation',
            'refunds_pending',
            'new_messages',
            'stock_alert',
            'recent_orders',
        ],
        'catalog_admin' => [
            'stock_alert',
            'new_products_added',
            'top_searches',
            'failed_searches',
            'manufacturing_stats',
            'manufacturer_revenue',
        ],
        'support' => [
            'awaiting_confirmation',
            'refunds_pending',
            'new_messages',
            'recent_orders',
        ],
    ];

    // ── Widget visibility (per-admin preferences) ───────────────────────

    /**
     * Get visibility for a widget — preference if set, else role default, else registry default.
     *
     * @return bool true if the widget should be displayed on dashboard
     */
    public function getVisibility(string $widgetId): bool
    {
        // Always-on widgets are never hidden.
        if (in_array($widgetId, self::ALWAYS_ON, true)) {
            return true;
        }

        $admin = $this->getAdmin();
        if (! $admin) {
            return $this->registryDefault($widgetId);
        }

        // Check if explicitly set in preferences.
        $prefs = $admin->dashboard_preferences ?? [];
        $visibility = $prefs['widget_visibility'] ?? [];
        if (isset($visibility[$widgetId])) {
            return (bool) $visibility[$widgetId];
        }

        // Fall back to role-based default.
        $role = $admin->roles()->first()?->name ?? 'support';
        $roleDefaults = self::ROLE_DEFAULT_ON[$role] ?? [];
        if (in_array($widgetId, $roleDefaults, true)) {
            return true;
        }

        // Fall back to registry default.
        return $this->registryDefault($widgetId);
    }

    /**
     * Save widget visibility preference.
     */
    public function saveVisibility(string $widgetId, bool $visible): void
    {
        $admin = $this->getAdmin();
        if (! $admin) {
            return;
        }

        $prefs = $admin->dashboard_preferences ?? [];
        $prefs['widget_visibility'] = $prefs['widget_visibility'] ?? [];
        $prefs['widget_visibility'][$widgetId] = $visible;
        $admin->dashboard_preferences = $prefs;

        try {
            $admin->save();
        } catch (\Exception $e) {
            Log::error('Failed to save widget visibility', [
                'admin_id' => $admin->id,
                'widget_id' => $widgetId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset widget visibility to role-specific defaults.
     */
    public function resetVisibility(): void
    {
        $admin = $this->getAdmin();
        if (! $admin) {
            return;
        }

        $prefs = $admin->dashboard_preferences ?? [];
        unset($prefs['widget_visibility']);
        $admin->dashboard_preferences = $prefs;

        try {
            $admin->save();
        } catch (\Exception $e) {
            Log::error('Failed to reset widget visibility', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── Registry accessors ──────────────────────────────────────────────

    /** @return string group slug for the widget class */
    public static function groupFor(string $widgetClass): ?string
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['group'] ?? null;
            }
        }

        return null;
    }

    /** @return string human-readable description for the widget class */
    public static function descriptionFor(string $widgetClass): string
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['description'] ?? '';
            }
        }

        return '';
    }

    /** @return list<string> roles allowed to view the widget class */
    public static function rolesFor(string $widgetClass): array
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['roles'];
            }
        }

        return ['super_admin', 'admin', 'manager'];
    }

    public static function ttlFor(string $widgetClass): int
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['ttl'];
            }
        }

        return AdminCacheService::DEFAULT_TTL;
    }

    /**
     * Registry render order (default_sort) for a widget class — the single
     * source of truth for dashboard layout order (groups rendered in sequence,
     * widgets ordered within each group). Drives Dashboard::getWidgets()'s
     * ordering; the per-class legacy $sort property is NOT consulted for the
     * grid order (Filament preserves getWidgets() array order, it does not
     * re-sort by $sort). Unknown classes sort last.
     */
    public static function sortFor(string $widgetClass): int
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['default_sort'];
            }
        }

        return PHP_INT_MAX;
    }

    public static function isPeriodCapable(string $widgetClass): bool
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['period'];
            }
        }

        return false;
    }

    public function getWidgetId(string $widgetClass): ?string
    {
        foreach (static::WIDGETS as $id => $config) {
            if ($config['class'] === $widgetClass) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Invalidate every cached-data entry for a widget, across all periods it
     * can be viewed at — cache keys are period-suffixed (see
     * InteractsWithDashboardCache), so a bare `AdminCacheService::forget($id)`
     * never matches the real key and silently does nothing. Unknown widget
     * ids are a no-op (never throw from an observer's cache-invalidation path).
     */
    public static function forgetCache(string $widgetId): void
    {
        $config = self::WIDGETS[$widgetId] ?? null;

        if (! $config) {
            return;
        }

        $periods = ($config['period'] ?? false) ? ['1', '7', '30', '90', '365'] : ['-'];

        foreach ($periods as $period) {
            AdminCacheService::forget("{$widgetId}:p{$period}");
        }
    }

    // ── Global period (persisted in _meta) ──────────────────────────────

    public function getPeriod(): string
    {
        $meta = $this->getMeta();

        $period = (string) ($meta['period'] ?? '30');

        return in_array($period, ['1', '7', '30', '90', '365'], true) ? $period : '30';
    }

    public function savePeriod(string $period): void
    {
        if (! in_array($period, ['1', '7', '30', '90', '365'], true)) {
            return;
        }

        $this->saveMeta(['period' => $period]);
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function registryDefault(string $widgetId): bool
    {
        return (self::WIDGETS[$widgetId]['default_visible'] ?? false);
    }

    private function getMeta(): array
    {
        $admin = $this->getAdmin();
        if (! $admin) {
            return [];
        }

        $meta = ($admin->dashboard_preferences ?? [])[self::META_KEY] ?? [];

        return is_array($meta) ? $meta : [];
    }

    private function saveMeta(array $values): void
    {
        $admin = $this->getAdmin();
        if (! $admin) {
            return;
        }

        $prefs = $admin->dashboard_preferences ?? [];
        $prefs[self::META_KEY] = array_merge($prefs[self::META_KEY] ?? [], $values);
        $admin->dashboard_preferences = $prefs;

        try {
            $admin->save();
        } catch (\Exception $e) {
            Log::error('Failed to save dashboard meta', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getAdmin(): ?Admin
    {
        return auth('admin')->user();
    }
}
