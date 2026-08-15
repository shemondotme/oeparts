<?php

namespace App\Filament\Support;

use App\Filament\Pages\Settings\AppearanceSettings;
use App\Filament\Pages\Settings\CustomizationSettings;
use App\Filament\Pages\Settings\GeneralBrandSettings;
use App\Filament\Pages\Settings\IntegrationsSettings;
use App\Filament\Pages\Settings\LocalizationSettings;
use App\Filament\Pages\Settings\NewsletterSettings;
use App\Filament\Pages\Settings\PerformanceSettings;
use App\Filament\Pages\Settings\SearchCatalogSettings;
use App\Filament\Pages\Settings\SecurityAccessSettings;
use App\Filament\Pages\Settings\SeoControlCenter;
use App\Filament\Pages\Settings\StoreOperationsSettings;
use App\Filament\Pages\Settings\SystemMaintenanceSettings;
use App\Filament\Pages\System\BackupDashboard;
use App\Filament\Pages\System\CacheDashboard;
use App\Filament\Pages\System\ErrorMonitor;
use App\Filament\Pages\System\FailedJobsPage;
use App\Filament\Pages\System\HealthCheckDashboard;
use App\Filament\Pages\System\HelpPage;
use App\Filament\Pages\System\LogViewerPage;
use App\Filament\Pages\System\PermissionMatrix;
use App\Filament\Pages\System\QueueMonitor;
use App\Filament\Pages\System\ScheduledTasksPage;
use App\Filament\Pages\System\ServerMonitor;
use App\Filament\Pages\System\SystemUpdates;
use App\Filament\Pages\System\UpdateHistoryPage;

/**
 * Declarative source of truth for which settings pages/tools are reachable
 * from the Settings cluster grid (resources/views/filament/clusters/settings.blade.php)
 * and the Ctrl+K settings search index.
 *
 * Three-level schema: SUPER_GROUPS (4 top-level groupings) -> SECTIONS (11
 * categories, each tagged with its super_group) -> PAGES (individual tiles,
 * each tagged with its section and a 'type').
 *
 * 'type' => 'page' means the class is a App\Filament\Pages\Settings\SettingsPage
 * subclass, and tests/Feature/SettingsRegistryTest.php enforces a strict 1:1
 * match between every such disk-discovered class and its PAGES entry — the
 * same invariant this registry has always enforced. 'type' => 'tool' covers
 * everything else reachable from the hub that ISN'T a SettingsPage (Backup,
 * Updates, read-only system monitors, the Site Copy Library) — those are
 * exempt from the 1:1 discovery check (there's no common base class to
 * discover them by) but still required to resolve a real class + URL.
 *
 * Before this registry existed, the cluster Blade view hardcoded a $sections
 * array directly — a page absent from that array existed and saved data
 * correctly but was completely unreachable from the UI, with no error
 * anywhere (this happened to what's now UiSettings; see docs/ARCHITECTURE.md).
 */
final class SettingsRegistry
{
    public const SUPER_GROUPS = [
        'brand-storefront' => [
            'label' => 'Brand & Storefront',
            'description' => 'How the store looks and speaks to customers',
            'icon' => 'heroicon-o-swatch',
            'sort' => 10,
        ],
        'store-commerce' => [
            'label' => 'Store & Commerce',
            'description' => 'Orders, payments, shipping, and what customers can find',
            'icon' => 'heroicon-o-building-storefront',
            'sort' => 20,
        ],
        'growth' => [
            'label' => 'Growth',
            'description' => 'SEO, marketing integrations, and site speed',
            'icon' => 'heroicon-o-arrow-trending-up',
            'sort' => 30,
        ],
        'platform' => [
            'label' => 'Platform',
            'description' => 'Access control, languages, and system maintenance',
            'icon' => 'heroicon-o-cog-8-tooth',
            'sort' => 40,
        ],
    ];

    public const SECTIONS = [
        'general-brand' => [
            'super_group' => 'brand-storefront',
            'label' => 'General & Brand',
            'icon' => 'heroicon-o-identification',
            'keywords' => 'general brand company store currency locale legal script injection identity',
            'sort' => 10,
        ],
        'appearance' => [
            'super_group' => 'brand-storefront',
            'label' => 'Appearance',
            'icon' => 'heroicon-o-paint-brush',
            'keywords' => 'appearance theme colors css preloader stats counter styling',
            'sort' => 20,
        ],
        'customization' => [
            'super_group' => 'brand-storefront',
            'label' => 'Customization',
            'icon' => 'heroicon-o-rectangle-group',
            'keywords' => 'ui hero navbar footer announcement sections menu social links copy text homepage',
            'sort' => 30,
        ],
        'store-operations' => [
            'super_group' => 'store-commerce',
            'label' => 'Store Operations',
            'icon' => 'heroicon-o-shopping-bag',
            'keywords' => 'orders customers cart dashboard shipping tax checkout payment email contact inquiry invoice smtp bank transfer airwallex paysera',
            'sort' => 10,
        ],
        'search-catalog' => [
            'super_group' => 'store-commerce',
            'label' => 'Search & Catalog',
            'icon' => 'heroicon-o-magnifying-glass',
            'keywords' => 'search oem product page specifications warranty video reviews related buy now',
            'sort' => 20,
        ],
        'seo' => [
            'super_group' => 'growth',
            'label' => 'SEO',
            'icon' => 'heroicon-o-globe-alt',
            'keywords' => 'seo search engine meta opengraph structured data sitemap indexnow crawlers ai redirects',
            'sort' => 10,
        ],
        'marketing' => [
            'super_group' => 'growth',
            'label' => 'Marketing',
            'icon' => 'heroicon-o-megaphone',
            'keywords' => 'marketing integrations gtm google search console newsletter coupons upsell rush processing',
            'sort' => 20,
        ],
        'performance' => [
            'super_group' => 'growth',
            'label' => 'Performance',
            'icon' => 'heroicon-o-cpu-chip',
            'keywords' => 'performance cache redis optimization speed',
            'sort' => 30,
        ],
        'security-access' => [
            'super_group' => 'platform',
            'label' => 'Security & Access',
            'icon' => 'heroicon-o-lock-closed',
            'keywords' => 'auth security firewall ip ban otp password session login roles permissions admins',
            'sort' => 10,
        ],
        'localization' => [
            'super_group' => 'platform',
            'label' => 'Localization',
            'icon' => 'heroicon-o-language',
            'keywords' => 'localization language translation locale',
            'sort' => 20,
        ],
        'system-maintenance' => [
            'super_group' => 'platform',
            'label' => 'System & Maintenance',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'keywords' => 'maintenance backup update database about license system health cache queue log monitor',
            'sort' => 30,
        ],
    ];

    public const PAGES = [
        'general-brand-settings' => [
            'class' => GeneralBrandSettings::class,
            'type' => 'page',
            'section' => 'general-brand',
            'title' => 'General & Brand',
            'url' => '/admin/settings/general-brand-settings',
            'description' => 'Site identity, company/legal info, regional defaults, script injection',
            'icon' => 'heroicon-o-identification',
            'sort' => 10,
        ],
        'appearance-settings' => [
            'class' => AppearanceSettings::class,
            'type' => 'page',
            'section' => 'appearance',
            'title' => 'Appearance',
            'url' => '/admin/settings/appearance-settings',
            'description' => 'Colors, custom CSS, preloader, stats counter',
            'icon' => 'heroicon-o-paint-brush',
            'sort' => 10,
        ],
        'customization-settings' => [
            'class' => CustomizationSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Customization',
            'url' => '/admin/settings/customization-settings',
            'description' => 'Hero/UI copy, navbar, footer, announcement bar, section limits, menus & social',
            'icon' => 'heroicon-o-rectangle-group',
            'sort' => 10,
        ],
        'store-operations-settings' => [
            'class' => StoreOperationsSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Store Operations',
            'url' => '/admin/settings/store-operations-settings',
            'description' => 'Orders, customers, cart, dashboard alerts, shipping, tax, checkout & payments, email, customer service, invoice',
            'icon' => 'heroicon-o-shopping-bag',
            'sort' => 10,
        ],
        'search-catalog-settings' => [
            'class' => SearchCatalogSettings::class,
            'type' => 'page',
            'section' => 'search-catalog',
            'title' => 'Search & Catalog',
            'url' => '/admin/settings/search-catalog-settings',
            'description' => 'Internal search behavior and product detail page sections',
            'icon' => 'heroicon-o-magnifying-glass',
            'sort' => 10,
        ],
        'seo-settings' => [
            'class' => SeoControlCenter::class,
            'type' => 'page',
            'section' => 'seo',
            'title' => 'SEO Control Center',
            'url' => '/admin/settings/seo-settings',
            'description' => 'Detail pages, structured data, crawlers/AI, sitemap, IndexNow, OpenGraph',
            'icon' => 'heroicon-o-globe-alt',
            'sort' => 10,
        ],
        'integrations-settings' => [
            'class' => IntegrationsSettings::class,
            'type' => 'page',
            'section' => 'marketing',
            'title' => 'Third-party APIs',
            'url' => '/admin/settings/integrations-settings',
            'description' => 'GTM, Google Search Console trackers',
            'icon' => 'heroicon-o-puzzle-piece',
            'sort' => 10,
        ],
        'newsletter-settings' => [
            'class' => NewsletterSettings::class,
            'type' => 'page',
            'section' => 'marketing',
            'title' => 'Newsletter',
            'url' => '/admin/settings/newsletter-settings',
            'description' => 'Subscription rate limits and opt-in settings',
            'icon' => 'heroicon-o-envelope',
            'sort' => 20,
        ],
        'performance-settings' => [
            'class' => PerformanceSettings::class,
            'type' => 'page',
            'section' => 'performance',
            'title' => 'Performance',
            'url' => '/admin/settings/performance-settings',
            'description' => 'Redis caching timeouts, optimization toggles',
            'icon' => 'heroicon-o-cpu-chip',
            'sort' => 10,
        ],
        'security-access-settings' => [
            'class' => SecurityAccessSettings::class,
            'type' => 'page',
            'section' => 'security-access',
            'title' => 'Security & Access',
            'url' => '/admin/settings/security-access-settings',
            'description' => 'OTP login rules, password policy, rate limiting, session lifetime',
            'icon' => 'heroicon-o-lock-closed',
            'sort' => 10,
        ],
        'localization-settings' => [
            'class' => LocalizationSettings::class,
            'type' => 'page',
            'section' => 'localization',
            'title' => 'Localization',
            'url' => '/admin/settings/localization-settings',
            'description' => 'Languages and translation strings (cross-links)',
            'icon' => 'heroicon-o-language',
            'sort' => 10,
        ],
        'system-maintenance-settings' => [
            'class' => SystemMaintenanceSettings::class,
            'type' => 'page',
            'section' => 'system-maintenance',
            'title' => 'Maintenance Mode',
            'url' => '/admin/settings/system-maintenance-settings',
            'description' => 'Storefront maintenance mode, platform/database info',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'sort' => 10,
        ],
        'backup-dashboard' => [
            'class' => BackupDashboard::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Backup Management',
            'url' => '/admin/system/backup-dashboard',
            'description' => 'Encrypted database + file backups, restore, retention',
            'icon' => 'heroicon-o-archive-box',
            'sort' => 20,
        ],
        'system-updates' => [
            'class' => SystemUpdates::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'System Updates',
            'url' => '/admin/system/system-updates',
            'description' => 'Release channel, apply/rollback updates',
            'icon' => 'heroicon-o-arrow-up-circle',
            'sort' => 30,
        ],
        'update-history-page' => [
            'class' => UpdateHistoryPage::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Update History',
            'url' => '/admin/system/update-history-page',
            'description' => 'Past update runs and their outcomes',
            'icon' => 'heroicon-o-clock',
            'sort' => 40,
        ],
        'health-check-dashboard' => [
            'class' => HealthCheckDashboard::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Health Check',
            'url' => '/admin/system/health-check-dashboard',
            'description' => 'Live system health status snapshot',
            'icon' => 'heroicon-o-heart',
            'sort' => 50,
        ],
        'cache-dashboard' => [
            'class' => CacheDashboard::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Cache Dashboard',
            'url' => '/admin/system/cache-dashboard',
            'description' => 'Cache driver status and metrics',
            'icon' => 'heroicon-o-server-stack',
            'sort' => 60,
        ],
        'queue-monitor' => [
            'class' => QueueMonitor::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Queue Monitor',
            'url' => '/admin/system/queue-monitor',
            'description' => 'Queued and processing job status',
            'icon' => 'heroicon-o-server-stack',
            'sort' => 70,
        ],
        'failed-jobs-page' => [
            'class' => FailedJobsPage::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Failed Jobs',
            'url' => '/admin/system/failed-jobs-page',
            'description' => 'Failed queue jobs — retry or delete',
            'icon' => 'heroicon-o-x-circle',
            'sort' => 80,
        ],
        'scheduled-tasks-page' => [
            'class' => ScheduledTasksPage::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Scheduled Tasks',
            'url' => '/admin/system/scheduled-tasks-page',
            'description' => 'Cron schedule and last-run status',
            'icon' => 'heroicon-o-clock',
            'sort' => 90,
        ],
        'error-monitor' => [
            'class' => ErrorMonitor::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Error Monitor',
            'url' => '/admin/system/error-monitor',
            'description' => 'Recent application errors and exceptions',
            'icon' => 'heroicon-o-exclamation-triangle',
            'sort' => 100,
        ],
        'log-viewer-page' => [
            'class' => LogViewerPage::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Log Viewer',
            'url' => '/admin/system/log-viewer-page',
            'description' => 'Browse application log files',
            'icon' => 'heroicon-o-document-text',
            'sort' => 110,
        ],
        'server-monitor' => [
            'class' => ServerMonitor::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Server Monitor',
            'url' => '/admin/system/server-monitor',
            'description' => 'CPU, memory, disk, and PHP process status',
            'icon' => 'heroicon-o-cpu-chip',
            'sort' => 120,
        ],
        'permission-matrix' => [
            'class' => PermissionMatrix::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Permission Matrix',
            'url' => '/admin/system/permission-matrix',
            'description' => 'Role-to-permission grants across the panel',
            'icon' => 'heroicon-o-key',
            'sort' => 130,
        ],
        'help-page' => [
            'class' => HelpPage::class,
            'type' => 'tool',
            'section' => 'system-maintenance',
            'title' => 'Help & Documentation',
            'url' => '/admin/system/help-page',
            'description' => 'Admin panel usage documentation',
            'icon' => 'heroicon-o-question-mark-circle',
            'sort' => 140,
        ],
    ];

    /**
     * Builds a nested [super_group => ['label', 'icon', 'description', 'sections'
     * => [section => ['label', 'icon', 'keywords', 'items' => [[title, url,
     * description, icon], ...]]]]] shape for the 2-level cluster grid.
     * Sections with zero registered items are omitted entirely — a category
     * with nothing in it yet (e.g. Localization until a later phase seeds it)
     * has nothing useful to render.
     */
    public static function sections(): array
    {
        $superGroups = [];

        foreach (self::SUPER_GROUPS as $superGroupKey => $superMeta) {
            $superGroups[$superGroupKey] = [
                'label' => $superMeta['label'],
                'description' => $superMeta['description'],
                'icon' => $superMeta['icon'],
                'sections' => [],
            ];
        }

        foreach (self::SECTIONS as $sectionKey => $sectionMeta) {
            $superGroups[$sectionMeta['super_group']]['sections'][$sectionKey] = [
                'label' => $sectionMeta['label'],
                'icon' => $sectionMeta['icon'],
                'keywords' => $sectionMeta['keywords'],
                'items' => [],
            ];
        }

        $pages = self::PAGES;
        uasort($pages, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        foreach ($pages as $page) {
            $superGroupKey = self::SECTIONS[$page['section']]['super_group'];
            $superGroups[$superGroupKey]['sections'][$page['section']]['items'][] = [
                $page['title'],
                $page['url'],
                $page['description'],
                $page['icon'],
            ];
        }

        foreach ($superGroups as $superGroupKey => $superGroup) {
            $superGroups[$superGroupKey]['sections'] = array_filter(
                $superGroup['sections'],
                fn (array $section) => ! empty($section['items'])
            );
        }

        return array_filter($superGroups, fn (array $superGroup) => ! empty($superGroup['sections']));
    }

    /**
     * @return array<class-string> Classes for 'type' => 'page' entries only —
     *                              the set tests/Feature/SettingsRegistryTest.php
     *                              cross-checks 1:1 against disk-discovered
     *                              SettingsPage subclasses.
     */
    public static function pageClasses(): array
    {
        return array_column(
            array_filter(self::PAGES, fn (array $page) => $page['type'] === 'page'),
            'class'
        );
    }
}
