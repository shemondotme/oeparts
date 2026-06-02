<?php

namespace App\Services;

use App\Models\Admin;

class WidgetPreferenceService
{
    public const WIDGETS = [
        'kpi_stats' => [
            'class' => \App\Filament\Widgets\DashboardKpiStats::class,
            'label' => 'KPI Statistics',
            'default_visible' => true,
            'default_sort' => 1,
        ],
        'revenue_chart' => [
            'class' => \App\Filament\Widgets\RevenueChart::class,
            'label' => 'Revenue Chart',
            'default_visible' => true,
            'default_sort' => 2,
        ],
        'recent_orders' => [
            'class' => \App\Filament\Widgets\RecentOrdersList::class,
            'label' => 'Recent Orders',
            'default_visible' => true,
            'default_sort' => 3,
        ],
        'top_searches' => [
            'class' => \App\Filament\Widgets\TopSearchedOems::class,
            'label' => 'Top Searched OEMs',
            'default_visible' => true,
            'default_sort' => 4,
        ],
        'failed_searches' => [
            'class' => \App\Filament\Widgets\FailedSearchesWidget::class,
            'label' => 'Failed Searches (Sourcing)',
            'default_visible' => true,
            'default_sort' => 5,
        ],
        'alerts' => [
            'class' => \App\Filament\Widgets\DashboardAlerts::class,
            'label' => 'System Alerts',
            'default_visible' => true,
            'default_sort' => 6,
        ],
        'health_strip' => [
            'class' => \App\Filament\Widgets\HealthStrip::class,
            'label' => 'System Health',
            'default_visible' => true,
            'default_sort' => 7,
        ],
        'manufacturer_revenue' => [
            'class' => \App\Filament\Widgets\TopManufacturersRevenue::class,
            'label' => 'Top Manufacturers by Revenue',
            'default_visible' => false,
            'default_sort' => 8,
        ],
        'customer_growth' => [
            'class' => \App\Filament\Widgets\CustomerGrowthChart::class,
            'label' => 'Customer Growth',
            'default_visible' => false,
            'default_sort' => 9,
        ],
        'checkout_dropoff' => [
            'class' => \App\Filament\Widgets\CheckoutDropoffChart::class,
            'label' => 'Checkout Drop-off',
            'default_visible' => false,
            'default_sort' => 10,
        ],
        'sales_by_country' => [
            'class' => \App\Filament\Widgets\SalesByCountryChart::class,
            'label' => 'Sales by Country',
            'default_visible' => false,
            'default_sort' => 11,
        ],
        'order_status_distribution' => [
            'class' => \App\Filament\Widgets\OrderStatusDistribution::class,
            'label' => 'Order Status Distribution',
            'default_visible' => false,
            'default_sort' => 12,
        ],
        'payment_method_split' => [
            'class' => \App\Filament\Widgets\PaymentMethodSplit::class,
            'label' => 'Payment Method Split',
            'default_visible' => false,
            'default_sort' => 13,
        ],
        'recent_activity' => [
            'class' => \App\Filament\Widgets\RecentActivityLog::class,
            'label' => 'Recent Activity Log',
            'default_visible' => false,
            'default_sort' => 14,
        ],
    ];

    public function getEnabledWidgetClasses(): array
    {
        $admin = $this->getAdmin();
        if (!$admin) {
            return $this->getDefaultEnabledClasses();
        }

        $prefs = $admin->dashboard_preferences ?? [];
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
        $admin = $this->getAdmin();
        $prefs = $admin ? ($admin->dashboard_preferences ?? []) : [];

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

        $prefs = $admin->dashboard_preferences ?? [];
        return !($prefs[$id]['hidden'] ?? !$config['default_visible']);
    }

    public function toggle(string $widgetId, bool $visible): void
    {
        $admin = $this->getAdmin();
        if (!$admin) return;

        $prefs = $admin->dashboard_preferences ?? [];
        $prefs[$widgetId] = array_merge($prefs[$widgetId] ?? [], ['hidden' => !$visible]);
        $admin->dashboard_preferences = $prefs;
        $admin->save();
    }

    public function setSortOrder(string $widgetId, int $sort): void
    {
        $admin = $this->getAdmin();
        if (!$admin) return;

        $prefs = $admin->dashboard_preferences ?? [];
        $prefs[$widgetId] = array_merge($prefs[$widgetId] ?? [], ['sort' => $sort]);
        $admin->dashboard_preferences = $prefs;
        $admin->save();
    }

    public function savePreferences(array $data): void
    {
        $admin = $this->getAdmin();
        if (!$admin) return;

        $prefs = $admin->dashboard_preferences ?? [];

        foreach ($data as $id => $settings) {
            $prefs[$id] = array_merge($prefs[$id] ?? [], $settings);
        }

        $admin->dashboard_preferences = $prefs;
        $admin->save();
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
        $admin = $this->getAdmin();
        return $admin->dashboard_preferences ?? [];
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
