<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for the dashboard widget registry.
 *
 * Each WIDGETS entry drives: visibility defaults, sort order, role access
 * (HasWidgetRoles), cache TTL (InteractsWithDashboardCache), global-period
 * participation (HasDashboardPeriod) and the default canvas size used when
 * seeding admin_dashboards layouts (DashboardLayoutService).
 *
 * The '_meta' key inside admins.dashboard_preferences is reserved for
 * non-widget state (global period, active dashboard id) and must never be
 * treated as a widget id.
 */
class WidgetPreferenceService
{
    public const META_KEY = '_meta';

    /** Management roles — full commercial visibility. */
    private const MGMT = ['super_admin', 'admin', 'manager'];

    /** System/observability widgets — operators only. */
    private const SYSTEM = ['super_admin', 'admin'];

    private const LEGACY_ID_MAP = [
        'stock-alert' => 'stock_alert',
        'abandoned-carts' => 'abandoned_carts',
        'coupon-usage' => 'coupon_usage',
        'parts-inquiry' => 'parts_inquiry',
        'latest-customers' => 'latest_customers',
        'manufacturing-stats' => 'manufacturing_stats',
        'newsletter-growth' => 'newsletter_growth',
        'disk-space' => 'disk_space',
        'request-metrics' => 'request_metrics',
    ];

    public const WIDGETS = [
        'dashboard_header' => [
            'class' => \App\Filament\Widgets\DashboardHeader::class,
            'label' => 'Dashboard Welcome Header',
            'default_visible' => true,
            'default_sort' => 1,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 12, 'h' => 2],
        ],
        'kpi_stats' => [
            'class' => \App\Filament\Widgets\DashboardKpiStats::class,
            'label' => 'KPI Statistics',
            'default_visible' => true,
            'default_sort' => 2,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 12, 'h' => 2],
        ],
        'revenue_chart' => [
            'class' => \App\Filament\Widgets\RevenueChart::class,
            'label' => 'Revenue Chart',
            'default_visible' => true,
            'default_sort' => 3,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'activity_overview' => [
            'class' => \App\Filament\Widgets\ActivityOverviewWidget::class,
            'label' => 'Activity Overview Sidebar',
            'default_visible' => true,
            'default_sort' => 4,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'recent_orders' => [
            'class' => \App\Filament\Widgets\RecentOrdersList::class,
            'label' => 'Recent Orders',
            'default_visible' => true,
            'default_sort' => 5,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'quick_actions' => [
            'class' => \App\Filament\Widgets\QuickActionsWidget::class,
            'label' => 'Quick Actions Shortcuts',
            'default_visible' => true,
            'default_sort' => 6,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'top_searches' => [
            'class' => \App\Filament\Widgets\TopSearchedOems::class,
            'label' => 'Top Searched OEMs',
            'default_visible' => true,
            'default_sort' => 7,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'failed_searches' => [
            'class' => \App\Filament\Widgets\FailedSearchesWidget::class,
            'label' => 'Failed Searches (Sourcing)',
            'default_visible' => true,
            'default_sort' => 8,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'alerts' => [
            'class' => \App\Filament\Widgets\DashboardAlerts::class,
            'label' => 'System Alerts',
            'default_visible' => true,
            'default_sort' => 9,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 2],
        ],
        'health_strip' => [
            'class' => \App\Filament\Widgets\HealthStrip::class,
            'label' => 'System Health',
            'default_visible' => true,
            'default_sort' => 10,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
            'default_layout' => ['w' => 6, 'h' => 2],
        ],
        'manufacturer_revenue' => [
            'class' => \App\Filament\Widgets\TopManufacturersRevenue::class,
            'label' => 'Top Manufacturers by Revenue',
            'default_visible' => false,
            'default_sort' => 11,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'customer_growth' => [
            'class' => \App\Filament\Widgets\CustomerGrowthChart::class,
            'label' => 'Customer Growth',
            'default_visible' => false,
            'default_sort' => 12,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'checkout_dropoff' => [
            'class' => \App\Filament\Widgets\CheckoutDropoffChart::class,
            'label' => 'Checkout Drop-off',
            'default_visible' => true,
            'default_sort' => 13,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'sales_by_country' => [
            'class' => \App\Filament\Widgets\SalesByCountryChart::class,
            'label' => 'Sales by Country',
            'default_visible' => true,
            'default_sort' => 14,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'order_status_distribution' => [
            'class' => \App\Filament\Widgets\OrderStatusDistribution::class,
            'label' => 'Order Status Distribution',
            'default_visible' => true,
            'default_sort' => 15,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin', 'support'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'payment_method_split' => [
            'class' => \App\Filament\Widgets\PaymentMethodSplit::class,
            'label' => 'Payment Method Split',
            'default_visible' => false,
            'default_sort' => 16,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'recent_activity' => [
            'class' => \App\Filament\Widgets\RecentActivityLog::class,
            'label' => 'Recent Activity Log',
            'default_visible' => false,
            'default_sort' => 17,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::LONG_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'stock_alert' => [
            'class' => \App\Filament\Widgets\StockAlertWidget::class,
            'label' => 'Stock Alerts',
            'default_visible' => true,
            'default_sort' => 18,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'abandoned_carts' => [
            'class' => \App\Filament\Widgets\AbandonedCartWidget::class,
            'label' => 'Abandoned Carts',
            'default_visible' => true,
            'default_sort' => 19,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'coupon_usage' => [
            'class' => \App\Filament\Widgets\CouponUsageWidget::class,
            'label' => 'Coupon Usage',
            'default_visible' => false,
            'default_sort' => 20,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'parts_inquiry' => [
            'class' => \App\Filament\Widgets\PartsInquiryWidget::class,
            'label' => 'Part Inquiries',
            'default_visible' => true,
            'default_sort' => 21,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'latest_customers' => [
            'class' => \App\Filament\Widgets\LatestCustomersWidget::class,
            'label' => 'Latest Customers',
            'default_visible' => true,
            'default_sort' => 22,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'manufacturing_stats' => [
            'class' => \App\Filament\Widgets\ManufacturingStatsWidget::class,
            'label' => 'Manufacturing Stats',
            'default_visible' => false,
            'default_sort' => 23,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'newsletter_growth' => [
            'class' => \App\Filament\Widgets\NewsletterGrowthWidget::class,
            'label' => 'Newsletter Growth',
            'default_visible' => false,
            'default_sort' => 24,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::LONG_TTL,
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'disk_space' => [
            'class' => \App\Filament\Widgets\DiskSpaceWidget::class,
            'label' => 'Disk Space Usage',
            'default_visible' => false,
            'default_sort' => 25,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
            'default_layout' => ['w' => 12, 'h' => 2],
        ],
        'request_metrics' => [
            'class' => \App\Filament\Widgets\RequestMetricsWidget::class,
            'label' => 'Request Metrics',
            'default_visible' => false,
            'default_sort' => 26,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'default_layout' => ['w' => 6, 'h' => 2],
        ],
    ];

    /**
     * Curated default canvas per role (ordered widget ids). Layout x/y is
     * computed row-major from each widget's default_layout at seed time by
     * DashboardLayoutService::ensureDefaultDashboard().
     */
    public const ROLE_DEFAULT_DASHBOARDS = [
        // Zone layout for super_admin / admin (all 26 widgets):
        // Row  1: dashboard_header (w12)
        // Row  2: alerts (w6) + health_strip (w6)
        // Row  3: kpi_stats (w12)
        // Row  4: recent_orders (w8) + quick_actions (w4)
        // Row  5: revenue_chart (w8) + order_status_distribution (w4)
        // Row  6: abandoned_carts (w6) + checkout_dropoff (w6)
        // Row  7: customer_growth (w6) + payment_method_split (w6)
        // Row  8: top_searches (w8) + failed_searches (w4)
        // Row  9: manufacturer_revenue (w8) + sales_by_country (w4)
        // Row 10: manufacturing_stats (w4) + parts_inquiry (w4) + newsletter_growth (w4)
        // Row 11: latest_customers (w6) + recent_activity (w6)
        // Row 12: coupon_usage (w6) + stock_alert (w6)
        // Row 13: activity_overview (w6) + request_metrics (w6)
        // Row 14: disk_space (w12)
        'super_admin' => [
            'dashboard_header',
            'alerts', 'health_strip',
            'kpi_stats',
            'recent_orders', 'quick_actions',
            'revenue_chart', 'order_status_distribution',
            'abandoned_carts', 'checkout_dropoff',
            'customer_growth', 'payment_method_split',
            'top_searches', 'failed_searches',
            'manufacturer_revenue', 'sales_by_country',
            'manufacturing_stats', 'parts_inquiry', 'newsletter_growth',
            'latest_customers', 'recent_activity',
            'coupon_usage', 'stock_alert',
            'activity_overview', 'request_metrics',
            'disk_space',
        ],
        'admin' => [
            'dashboard_header',
            'alerts', 'health_strip',
            'kpi_stats',
            'recent_orders', 'quick_actions',
            'revenue_chart', 'order_status_distribution',
            'abandoned_carts', 'checkout_dropoff',
            'customer_growth', 'payment_method_split',
            'top_searches', 'failed_searches',
            'manufacturer_revenue', 'sales_by_country',
            'manufacturing_stats', 'parts_inquiry', 'newsletter_growth',
            'latest_customers', 'recent_activity',
            'coupon_usage', 'stock_alert',
            'activity_overview', 'request_metrics',
            'disk_space',
        ],
        // Zone layout for manager (22 widgets, no system widgets):
        // Row  1: dashboard_header (w12)
        // Row  2: kpi_stats (w12)
        // Row  3: recent_orders (w8) + quick_actions (w4)
        // Row  4: revenue_chart (w8) + order_status_distribution (w4)
        // Row  5: abandoned_carts (w6) + checkout_dropoff (w6)
        // Row  6: customer_growth (w6) + payment_method_split (w6)
        // Row  7: top_searches (w8) + failed_searches (w4)
        // Row  8: manufacturer_revenue (w8) + sales_by_country (w4)
        // Row  9: manufacturing_stats (w4) + parts_inquiry (w4) + newsletter_growth (w4)
        // Row 10: latest_customers (w6) + coupon_usage (w6)
        // Row 11: stock_alert (w6) + activity_overview (w6)
        'manager' => [
            'dashboard_header',
            'kpi_stats',
            'recent_orders', 'quick_actions',
            'revenue_chart', 'order_status_distribution',
            'abandoned_carts', 'checkout_dropoff',
            'customer_growth', 'payment_method_split',
            'top_searches', 'failed_searches',
            'manufacturer_revenue', 'sales_by_country',
            'manufacturing_stats', 'parts_inquiry', 'newsletter_growth',
            'latest_customers', 'coupon_usage',
            'stock_alert', 'activity_overview',
        ],
        // Zone layout for catalog_admin (7 widgets):
        // Row 1: dashboard_header (w12)
        // Row 2: quick_actions (w4) + stock_alert (w6)
        // Row 3: top_searches (w8) + failed_searches (w4)
        // Row 4: manufacturing_stats (w4) + order_status_distribution (w4)
        'catalog_admin' => [
            'dashboard_header',
            'quick_actions', 'stock_alert',
            'top_searches', 'failed_searches',
            'manufacturing_stats', 'order_status_distribution',
        ],
        // Zone layout for support (9 widgets):
        // Row 1: dashboard_header (w12)
        // Row 2: recent_orders (w8) + quick_actions (w4)
        // Row 3: abandoned_carts (w6) + checkout_dropoff (w6)
        // Row 4: order_status_distribution (w4) + parts_inquiry (w4) + newsletter_growth (w4)
        // Row 5: latest_customers (w6)
        'support' => [
            'dashboard_header',
            'recent_orders', 'quick_actions',
            'abandoned_carts', 'checkout_dropoff',
            'order_status_distribution', 'parts_inquiry', 'newsletter_growth',
            'latest_customers',
        ],
    ];

    // ── Registry accessors ──────────────────────────────────────────────

    /** @return list<string> all known widget ids */
    public function widgetIds(): array
    {
        return array_keys(static::WIDGETS);
    }

    /** @return list<string> roles allowed to view the widget class */
    public static function rolesFor(string $widgetClass): array
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['roles'];
            }
        }

        // Unknown widget: restrict to management rather than failing open.
        return self::MGMT;
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

    public static function isPeriodCapable(string $widgetClass): bool
    {
        foreach (static::WIDGETS as $config) {
            if ($config['class'] === $widgetClass) {
                return $config['period'];
            }
        }

        return false;
    }

    /** @return array{w:int,h:int} */
    public function defaultLayoutFor(string $widgetId): array
    {
        return static::WIDGETS[$widgetId]['default_layout'] ?? ['w' => 6, 'h' => 4];
    }

    /** Ordered default widget ids for the admin's primary role. */
    public function roleDefaultWidgetIds(Admin $admin): array
    {
        foreach (array_keys(self::ROLE_DEFAULT_DASHBOARDS) as $role) {
            if ($admin->hasRole($role)) {
                return self::ROLE_DEFAULT_DASHBOARDS[$role];
            }
        }

        return self::ROLE_DEFAULT_DASHBOARDS['support'];
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

    // ── Active named dashboard (persisted in _meta) ─────────────────────

    public function getActiveDashboardId(): ?int
    {
        $id = $this->getMeta()['active_dashboard'] ?? null;

        return $id === null ? null : (int) $id;
    }

    public function saveActiveDashboardId(int $dashboardId): void
    {
        $this->saveMeta(['active_dashboard' => $dashboardId]);
    }

    // ── Visibility & ordering (legacy flat preferences) ─────────────────

    public function getEnabledWidgetClasses(): array
    {
        $admin = $this->getAdmin();
        if (!$admin) {
            return $this->getDefaultEnabledClasses();
        }

        $prefs = $this->getAdminPreferences();
        if (empty($prefs)) {
            return $this->getDefaultEnabledClasses();
        }

        $enabled = [];
        foreach (static::WIDGETS as $id => $config) {
            $hidden = $prefs[$id]['hidden'] ?? !$config['default_visible'];
            if (!$hidden) {
                $enabled[] = $config['class'];
            }
        }

        return $enabled;
    }

    public function getSortedWidgets(): array
    {
        $prefs = $this->getAdminPreferences();

        $widgets = [];
        foreach (static::WIDGETS as $id => $config) {
            $userSort = $prefs[$id]['sort'] ?? null;
            $widgets[] = [
                'id' => $id,
                'class' => $config['class'],
                'label' => $config['label'],
                'sort' => $userSort ?? $config['default_sort'],
                'hidden' => $prefs[$id]['hidden'] ?? !$config['default_visible'],
            ];
        }

        usort($widgets, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return $widgets;
    }

    public function getSortedEnabledClasses(): array
    {
        return array_values(array_map(
            fn ($w) => $w['class'],
            array_filter($this->getSortedWidgets(), fn ($w) => !$w['hidden'])
        ));
    }

    public function isEnabled(string $widgetClass): bool
    {
        $id = $this->getWidgetId($widgetClass);
        if (!$id) return true;

        $config = static::WIDGETS[$id];

        $admin = $this->getAdmin();
        if (!$admin) return $config['default_visible'];

        $prefs = $this->getAdminPreferences();
        return !($prefs[$id]['hidden'] ?? !$config['default_visible']);
    }

    public function toggle(string $widgetId, bool $visible): void
    {
        $normalizedId = self::LEGACY_ID_MAP[$widgetId] ?? $widgetId;

        if (! array_key_exists($normalizedId, static::WIDGETS)) {
            return;
        }

        $admin = $this->getAdmin();
        if (!$admin) return;

        $prefs = $this->getAdminPreferences();
        $prefs[$normalizedId] = array_merge($prefs[$normalizedId] ?? [], ['hidden' => !$visible]);
        $admin->dashboard_preferences = $this->withMeta($admin, $prefs);

        try {
            $admin->save();
        } catch (\Exception $e) {
            Log::error('Failed to toggle widget preference', [
                'admin_id' => $admin->id,
                'widget_id' => $widgetId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function setSortOrder(string $widgetId, int $sort): void
    {
        $normalizedId = self::LEGACY_ID_MAP[$widgetId] ?? $widgetId;

        if (! array_key_exists($normalizedId, static::WIDGETS)) {
            return;
        }

        $admin = $this->getAdmin();
        if (!$admin) return;

        $prefs = $this->getAdminPreferences();
        $prefs[$normalizedId] = array_merge($prefs[$normalizedId] ?? [], ['sort' => $sort]);
        $admin->dashboard_preferences = $this->withMeta($admin, $prefs);

        try {
            $admin->save();
        } catch (\Exception $e) {
            Log::error('Failed to set widget sort order', [
                'admin_id' => $admin->id,
                'widget_id' => $widgetId,
                'sort' => $sort,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function savePreferences(array $data): void
    {
        $admin = $this->getAdmin();
        if (!$admin) return;

        $prefs = $this->getAdminPreferences();

        foreach ($data as $id => $settings) {
            $normalizedId = self::LEGACY_ID_MAP[$id] ?? $id;

            if (! array_key_exists($normalizedId, static::WIDGETS)) {
                continue;
            }

            $prefs[$normalizedId] = array_merge($prefs[$normalizedId] ?? [], $settings);
        }

        $admin->dashboard_preferences = $this->withMeta($admin, $prefs);
        $admin->save();

        Log::debug('Widget preferences saved', ['admin' => auth('admin')->id()]);
    }

    public function getWidgetId(string $widgetClass): ?string
    {
        foreach (static::WIDGETS as $id => $config) {
            if ($config['class'] === $widgetClass) return $id;
        }
        return null;
    }

    public function getPreferences(): array
    {
        return $this->getAdminPreferences();
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function getMeta(): array
    {
        $admin = $this->getAdmin();
        if (!$admin) return [];

        $meta = ($admin->dashboard_preferences ?? [])[self::META_KEY] ?? [];

        return is_array($meta) ? $meta : [];
    }

    private function saveMeta(array $values): void
    {
        $admin = $this->getAdmin();
        if (!$admin) return;

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

    /** Re-attach stored _meta before persisting widget-id keyed prefs. */
    private function withMeta(Admin $admin, array $prefs): array
    {
        $meta = ($admin->dashboard_preferences ?? [])[self::META_KEY] ?? null;

        if (is_array($meta)) {
            $prefs[self::META_KEY] = $meta;
        }

        return $prefs;
    }

    /** Widget-id keyed preferences with legacy ids normalized and _meta stripped. */
    private function getAdminPreferences(): array
    {
        $admin = $this->getAdmin();
        if (!$admin) return [];

        $prefs = $admin->dashboard_preferences ?? [];

        unset($prefs[self::META_KEY]);

        foreach (self::LEGACY_ID_MAP as $legacy => $current) {
            if (isset($prefs[$legacy]) && !isset($prefs[$current])) {
                $prefs[$current] = $prefs[$legacy];
            }
            unset($prefs[$legacy]);
        }

        return $prefs;
    }

    private function getDefaultEnabledClasses(): array
    {
        $classes = [];
        foreach (static::WIDGETS as $id => $config) {
            if ($config['default_visible']) {
                $classes[] = $config['class'];
            }
        }
        return $classes;
    }

    private function getAdmin(): ?Admin
    {
        return auth('admin')->user();
    }
}
