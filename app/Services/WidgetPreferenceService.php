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

    /** Catalog roles */
    private const CATALOG = ['super_admin', 'admin', 'manager', 'catalog_admin'];

    public const WIDGETS = [

        // ── Command Center ──────────────────────────────────────────────

        'dashboard_header' => [
            'class' => \App\Filament\Widgets\DashboardHeader::class,
            'label' => 'Welcome Header',
            'default_visible' => true,
            'default_sort' => 1,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'header',
            'default_layout' => ['w' => 12, 'h' => 2],
        ],
        'health_strip' => [
            'class' => \App\Filament\Widgets\HealthStrip::class,
            'label' => 'System Health',
            'default_visible' => true,
            'default_sort' => 2,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
            'type' => 'strip',
            'default_layout' => ['w' => 12, 'h' => 1],
        ],
        'revenue_kpi' => [
            'class' => \App\Filament\Widgets\RevenueKpi::class,
            'label' => "Today's Revenue",
            'default_visible' => true,
            'default_sort' => 3,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'new_orders_kpi' => [
            'class' => \App\Filament\Widgets\NewOrdersKpi::class,
            'label' => 'New Orders',
            'default_visible' => true,
            'default_sort' => 4,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'pending_orders_kpi' => [
            'class' => \App\Filament\Widgets\PendingOrdersKpi::class,
            'label' => 'Pending Orders',
            'default_visible' => true,
            'default_sort' => 5,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'revenue_chart' => [
            'class' => \App\Filament\Widgets\RevenueChart::class,
            'label' => 'Revenue Trend',
            'default_visible' => true,
            'default_sort' => 6,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'chart',
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'order_volume_chart' => [
            'class' => \App\Filament\Widgets\OrderVolumeChart::class,
            'label' => 'Order Volume',
            'default_visible' => true,
            'default_sort' => 7,
            'roles' => self::MGMT,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'chart',
            'default_layout' => ['w' => 4, 'h' => 4],
        ],

        // ── Operations ──────────────────────────────────────────────────

        'recent_orders' => [
            'class' => \App\Filament\Widgets\RecentOrdersList::class,
            'label' => 'Recent Orders',
            'default_visible' => true,
            'default_sort' => 8,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 12, 'h' => 4],
        ],
        'abandoned_carts' => [
            'class' => \App\Filament\Widgets\AbandonedCartWidget::class,
            'label' => 'Abandoned Carts',
            'default_visible' => true,
            'default_sort' => 9,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'awaiting_confirmation' => [
            'class' => \App\Filament\Widgets\AwaitingConfirmationList::class,
            'label' => 'Awaiting Confirmation',
            'default_visible' => true,
            'default_sort' => 10,
            'roles' => ['super_admin', 'admin', 'manager', 'catalog_admin'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'refunds_pending' => [
            'class' => \App\Filament\Widgets\RefundsPendingList::class,
            'label' => 'Refunds Pending',
            'default_visible' => true,
            'default_sort' => 11,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'new_messages' => [
            'class' => \App\Filament\Widgets\NewMessagesInbox::class,
            'label' => 'New Messages',
            'default_visible' => true,
            'default_sort' => 12,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'failed_queue_jobs' => [
            'class' => \App\Filament\Widgets\FailedQueueJobsMonitor::class,
            'label' => 'Failed Queue Jobs',
            'default_visible' => true,
            'default_sort' => 13,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],

        // ── Inventory & Sourcing ────────────────────────────────────────

        'manufacturer_revenue' => [
            'class' => \App\Filament\Widgets\TopManufacturersRevenue::class,
            'label' => 'Top Manufacturers by Revenue',
            'default_visible' => true,
            'default_sort' => 14,
            'roles' => self::MGMT,
            'financial' => true,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'top_searches' => [
            'class' => \App\Filament\Widgets\TopSearchedOems::class,
            'label' => 'Top Searched OEMs',
            'default_visible' => true,
            'default_sort' => 15,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'failed_searches' => [
            'class' => \App\Filament\Widgets\FailedSearchesWidget::class,
            'label' => 'Failed Searches (Sourcing)',
            'default_visible' => true,
            'default_sort' => 16,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],
        'stock_alert' => [
            'class' => \App\Filament\Widgets\StockAlertWidget::class,
            'label' => 'Stock Alerts',
            'default_visible' => true,
            'default_sort' => 17,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'new_products_added' => [
            'class' => \App\Filament\Widgets\NewProductsAdded::class,
            'label' => 'New Products Added',
            'default_visible' => true,
            'default_sort' => 18,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'parts_inquiry' => [
            'class' => \App\Filament\Widgets\PartsInquiryWidget::class,
            'label' => 'Part Inquiries',
            'default_visible' => true,
            'default_sort' => 19,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'manufacturing_stats' => [
            'class' => \App\Filament\Widgets\ManufacturingStatsWidget::class,
            'label' => 'Manufacturing Stats',
            'default_visible' => false,
            'default_sort' => 20,
            'roles' => self::CATALOG,
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 4, 'h' => 2],
        ],

        'supplier_scorecard' => [
            'class' => \App\Filament\Widgets\SupplierPerformanceScorecardWidget::class,
            'label' => 'Supplier Performance',
            'default_visible' => true,
            'default_sort' => 21,
            'roles' => [...self::MGMT, ...self::CATALOG],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 6, 'h' => 4],
        ],

        // ── System & Admin ──────────────────────────────────────────────

        'recent_activity' => [
            'class' => \App\Filament\Widgets\RecentActivityLog::class,
            'label' => 'Admin Activity Feed',
            'default_visible' => true,
            'default_sort' => 21,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::LONG_TTL,
            'type' => 'table',
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'disk_space' => [
            'class' => \App\Filament\Widgets\DiskSpaceWidget::class,
            'label' => 'Disk Usage',
            'default_visible' => true,
            'default_sort' => 22,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
            'type' => 'chart',
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'cache_status' => [
            'class' => \App\Filament\Widgets\CacheStatusWidget::class,
            'label' => 'Cache Status',
            'default_visible' => true,
            'default_sort' => 23,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 4, 'h' => 2],
        ],
        'queue_worker_status' => [
            'class' => \App\Filament\Widgets\QueueWorkerStatusWidget::class,
            'label' => 'Queue Worker Status',
            'default_visible' => true,
            'default_sort' => 24,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::SHORT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 4, 'h' => 4],
        ],
        'newsletter_growth' => [
            'class' => \App\Filament\Widgets\NewsletterGrowthWidget::class,
            'label' => 'Newsletter Growth',
            'default_visible' => false,
            'default_sort' => 25,
            'roles' => ['super_admin', 'admin', 'manager', 'support'],
            'financial' => false,
            'period' => true,
            'ttl' => AdminCacheService::LONG_TTL,
            'type' => 'chart',
            'default_layout' => ['w' => 8, 'h' => 4],
        ],
        'request_metrics' => [
            'class' => \App\Filament\Widgets\RequestMetricsWidget::class,
            'label' => 'Request Metrics',
            'default_visible' => true,
            'default_sort' => 26,
            'roles' => self::SYSTEM,
            'financial' => false,
            'period' => false,
            'ttl' => AdminCacheService::DEFAULT_TTL,
            'type' => 'kpi',
            'default_layout' => ['w' => 12, 'h' => 1],
        ],
    ];

    /**
     * Curated default canvas per role (ordered widget ids). Layout x/y is
     * computed row-major from each widget's default_layout at seed time by
     * DashboardLayoutService::ensureDefaultDashboard().
     */
    public const ROLE_DEFAULT_DASHBOARDS = [
        'super_admin' => [
            // Row 1: dashboard_header (w12)
            // Row 2: health_strip (w12)
            // Row 3: revenue_kpi (w4) + new_orders_kpi (w4) + pending_orders_kpi (w4)
            // Row 4: revenue_chart (w8) + order_volume_chart (w4)
            'dashboard_header',
            'health_strip',
            'revenue_kpi', 'new_orders_kpi', 'pending_orders_kpi',
            'revenue_chart', 'order_volume_chart',
            // Operations
            'recent_orders',
            'abandoned_carts', 'awaiting_confirmation',
            'refunds_pending', 'new_messages',
            'failed_queue_jobs',
            // Inventory & Sourcing
            'manufacturer_revenue', 'failed_searches',
            'top_searches', 'new_products_added',
            'stock_alert', 'parts_inquiry',
            'manufacturing_stats', 'supplier_scorecard',
            // System & Admin
            'recent_activity', 'newsletter_growth',
            'disk_space', 'cache_status', 'queue_worker_status',
            'request_metrics',
        ],
        'admin' => [
            'dashboard_header',
            'health_strip',
            'revenue_kpi', 'new_orders_kpi', 'pending_orders_kpi',
            'revenue_chart', 'order_volume_chart',
            'recent_orders',
            'abandoned_carts', 'awaiting_confirmation',
            'refunds_pending', 'new_messages',
            'failed_queue_jobs',
            'manufacturer_revenue', 'failed_searches',
            'top_searches', 'new_products_added',
            'stock_alert', 'parts_inquiry',
            'manufacturing_stats', 'supplier_scorecard',
            'recent_activity', 'newsletter_growth',
            'disk_space', 'cache_status', 'queue_worker_status',
            'request_metrics',
        ],
        'manager' => [
            'dashboard_header',
            'revenue_kpi', 'new_orders_kpi', 'pending_orders_kpi',
            'revenue_chart', 'order_volume_chart',
            'recent_orders',
            'abandoned_carts', 'awaiting_confirmation',
            'refunds_pending', 'new_messages',
            'manufacturer_revenue', 'failed_searches',
            'top_searches', 'new_products_added',
            'stock_alert', 'parts_inquiry',
            'manufacturing_stats', 'supplier_scorecard',
            'newsletter_growth',
        ],
        'catalog_admin' => [
            'dashboard_header',
            'awaiting_confirmation',
            'failed_searches',
            'top_searches', 'new_products_added',
            'stock_alert', 'parts_inquiry',
            'manufacturing_stats', 'supplier_scorecard',
        ],
        'support' => [
            'dashboard_header',
            'recent_orders',
            'abandoned_carts',
            'refunds_pending', 'new_messages',
            'parts_inquiry',
        ],
    ];

    public const WIDGET_TABS = [
        'Command Center' => [
            'dashboard_header',
            'health_strip',
            'revenue_kpi',
            'new_orders_kpi',
            'pending_orders_kpi',
            'revenue_chart',
            'order_volume_chart',
        ],
        'Operations' => [
            'recent_orders',
            'abandoned_carts',
            'awaiting_confirmation',
            'refunds_pending',
            'new_messages',
            'failed_queue_jobs',
        ],
        'Inventory & Sourcing' => [
            'manufacturer_revenue',
            'top_searches',
            'failed_searches',
            'stock_alert',
            'new_products_added',
            'parts_inquiry',
            'manufacturing_stats',
            'supplier_scorecard',
        ],
        'System & Admin' => [
            'recent_activity',
            'disk_space',
            'cache_status',
            'queue_worker_status',
            'newsletter_growth',
            'request_metrics',
        ],
    ];

    /** Map legacy retired widget IDs to their closest new equivalent. */
    private const LEGACY_ID_MAP = [
        'kpi_stats' => 'revenue_kpi',
        'quick_actions' => 'dashboard_header',
        'activity_overview' => 'recent_activity',
        'alerts' => 'health_strip',
        'customer_growth' => 'newsletter_growth',
        'checkout_dropoff' => 'recent_orders',
        'sales_by_country' => 'manufacturer_revenue',
        'order_status_distribution' => 'order_volume_chart',
        'payment_method_split' => 'revenue_chart',
        'coupon_usage' => 'revenue_kpi',
        'latest_customers' => 'new_orders_kpi',
        'stock-alert' => 'stock_alert',
        'abandoned-carts' => 'abandoned_carts',
        'coupon-usage' => 'revenue_kpi',
        'parts-inquiry' => 'parts_inquiry',
        'latest-customers' => 'new_orders_kpi',
        'manufacturing-stats' => 'manufacturing_stats',
        'newsletter-growth' => 'newsletter_growth',
        'disk-space' => 'disk_space',
        'request-metrics' => 'request_metrics',
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

    /** @return array<string, list<string>> */
    public function roleDefaultTabs(Admin $admin): array
    {
        $tabs = [];

        foreach (self::WIDGET_TABS as $label => $ids) {
            $viewable = array_values(array_filter(
                $ids,
                fn (string $id): bool => isset(static::WIDGETS[$id])
                    && $admin->hasAnyRole(static::WIDGETS[$id]['roles'])
            ));

            if ($viewable !== []) {
                $tabs[$label] = $viewable;
            }
        }

        return $tabs;
    }

    /** @return list<string>|null */
    public function roleDefaultTabWidgetIds(Admin $admin, string $slug): ?array
    {
        foreach ($this->roleDefaultTabs($admin) as $label => $ids) {
            if (\Illuminate\Support\Str::slug($label) === $slug) {
                return $ids;
            }
        }

        return null;
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

    // ── Active tab (persisted in _meta) ─────────────────────────────────

    public function getActiveTab(): string
    {
        return $this->getMeta()['active_tab'] ?? 'Command Center';
    }

    public function saveActiveTab(string $tab): void
    {
        $this->saveMeta(['active_tab' => $tab]);
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
