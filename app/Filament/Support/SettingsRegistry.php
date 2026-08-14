<?php

namespace App\Filament\Support;

use App\Filament\Pages\Settings\AboutLicenseSettings;
use App\Filament\Pages\Settings\AnnouncementSettings;
use App\Filament\Pages\Settings\AppearanceSettings;
use App\Filament\Pages\Settings\CartSettings;
use App\Filament\Pages\Settings\CheckoutSettings;
use App\Filament\Pages\Settings\CustomersSettings;
use App\Filament\Pages\Settings\FooterSettings;
use App\Filament\Pages\Settings\NavbarSettings;
use App\Filament\Pages\Settings\CompanySettings;
use App\Filament\Pages\Settings\ContactSettings;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Pages\Settings\DatabaseSettings;
use App\Filament\Pages\Settings\EmailSettings;
use App\Filament\Pages\Settings\GeneralSettings;
use App\Filament\Pages\Settings\IntegrationsSettings;
use App\Filament\Pages\Settings\MaintenanceSettings;
use App\Filament\Pages\Settings\MenuSettings;
use App\Filament\Pages\Settings\NewsletterSettings;
use App\Filament\Pages\Settings\OrdersSettings;
use App\Filament\Pages\Settings\PartInquirySettings;
use App\Filament\Pages\Settings\PaymentSettings;
use App\Filament\Pages\Settings\PdpSettings;
use App\Filament\Pages\Settings\PerformanceSettings;
use App\Filament\Pages\Settings\PreloaderSettings;
use App\Filament\Pages\Settings\SearchSettings;
use App\Filament\Pages\Settings\SectionsSettings;
use App\Filament\Pages\Settings\SecurityAccessSettings;
use App\Filament\Pages\Settings\SeoControlCenter;
use App\Filament\Pages\Settings\ShippingSettings;
use App\Filament\Pages\Settings\SocialLinkSettings;
use App\Filament\Pages\Settings\StatsCounterSettings;
use App\Filament\Pages\Settings\StoreSettings;
use App\Filament\Pages\Settings\TaxSettings;
use App\Filament\Pages\Settings\UiSettings;

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
        'general-settings' => [
            'class' => GeneralSettings::class,
            'type' => 'page',
            'section' => 'general-brand',
            'title' => 'General Settings',
            'url' => '/admin/settings/general-settings',
            'description' => 'Store name, logo, basic details',
            'icon' => 'heroicon-o-cog-6-tooth',
            'sort' => 10,
        ],
        'company-settings' => [
            'class' => CompanySettings::class,
            'type' => 'page',
            'section' => 'general-brand',
            'title' => 'Company Info',
            'url' => '/admin/settings/company-settings',
            'description' => 'Company details for invoices, legal, and emails',
            'icon' => 'heroicon-o-building-office-2',
            'sort' => 20,
        ],
        'store-settings' => [
            'class' => StoreSettings::class,
            'type' => 'page',
            'section' => 'general-brand',
            'title' => 'Store Currency',
            'url' => '/admin/settings/store-settings',
            'description' => 'Currency, symbol, locale formatting',
            'icon' => 'heroicon-o-banknotes',
            'sort' => 30,
        ],
        'contact-settings' => [
            'class' => ContactSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Contact Info',
            'url' => '/admin/settings/contact-settings',
            'description' => 'Store location, support email, phone',
            'icon' => 'heroicon-o-phone',
            'sort' => 100,
        ],
        'appearance-settings' => [
            'class' => AppearanceSettings::class,
            'type' => 'page',
            'section' => 'appearance',
            'title' => 'Appearance',
            'url' => '/admin/settings/appearance-settings',
            'description' => 'Custom colors, theme styling',
            'icon' => 'heroicon-o-paint-brush',
            'sort' => 10,
        ],
        'stats-counter-settings' => [
            'class' => StatsCounterSettings::class,
            'type' => 'page',
            'section' => 'appearance',
            'title' => 'Stats Counter',
            'url' => '/admin/settings/stats-counter-settings',
            'description' => 'Fake frontend counters (parts, clients)',
            'icon' => 'heroicon-o-presentation-chart-line',
            'sort' => 20,
        ],
        'preloader-settings' => [
            'class' => PreloaderSettings::class,
            'type' => 'page',
            'section' => 'appearance',
            'title' => 'Preloader',
            'url' => '/admin/settings/preloader-settings',
            'description' => 'Full-screen loading animation settings',
            'icon' => 'heroicon-o-arrow-path',
            'sort' => 30,
        ],
        'ui-settings' => [
            'class' => UiSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Homepage Hero & UI Text',
            'url' => '/admin/settings/ui-settings',
            'description' => 'Hero banner, spec table, and footer pill copy',
            'icon' => 'heroicon-o-paint-brush',
            'sort' => 10,
        ],
        'navbar-settings' => [
            'class' => NavbarSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Navbar & Mini-Cart',
            'url' => '/admin/settings/navbar-settings',
            'description' => 'Navigation and mini-cart labels',
            'icon' => 'heroicon-o-bars-3-bottom-left',
            'sort' => 20,
        ],
        'footer-settings' => [
            'class' => FooterSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Footer Badges',
            'url' => '/admin/settings/footer-settings',
            'description' => 'Trust badges, stats, payment labels',
            'icon' => 'heroicon-o-rectangle-group',
            'sort' => 30,
        ],
        'announcement-settings' => [
            'class' => AnnouncementSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Announcement',
            'url' => '/admin/settings/announcement-settings',
            'description' => 'Site-wide marquee promo bar',
            'icon' => 'heroicon-o-megaphone',
            'sort' => 40,
        ],
        'sections-settings' => [
            'class' => SectionsSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Sections',
            'url' => '/admin/settings/sections-settings',
            'description' => 'Homepage content section display limits',
            'icon' => 'heroicon-o-squares-2x2',
            'sort' => 50,
        ],
        'menu-settings' => [
            'class' => MenuSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Menu Settings',
            'url' => '/admin/settings/menu-settings',
            'description' => 'Configure storefront header and footer navigation',
            'icon' => 'heroicon-o-bars-3',
            'sort' => 60,
        ],
        'social-link-settings' => [
            'class' => SocialLinkSettings::class,
            'type' => 'page',
            'section' => 'customization',
            'title' => 'Social Links',
            'url' => '/admin/settings/social-link-settings',
            'description' => 'Social media profile URLs for footer and sharing',
            'icon' => 'heroicon-o-globe-alt',
            'sort' => 70,
        ],
        'orders-settings' => [
            'class' => OrdersSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Orders Policy',
            'url' => '/admin/settings/orders-settings',
            'description' => 'Order expiry, prefix formats, status defaults',
            'icon' => 'heroicon-o-shopping-bag',
            'sort' => 10,
        ],
        'customers-settings' => [
            'class' => CustomersSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Customers',
            'url' => '/admin/settings/customers-settings',
            'description' => 'VIP and repeat segment thresholds',
            'icon' => 'heroicon-o-users',
            'sort' => 20,
        ],
        'cart-settings' => [
            'class' => CartSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Cart Rules',
            'url' => '/admin/settings/cart-settings',
            'description' => 'Cart duration, timeout limits',
            'icon' => 'heroicon-o-shopping-cart',
            'sort' => 30,
        ],
        'dashboard-settings' => [
            'class' => DashboardSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Dashboard Thresholds',
            'url' => '/admin/settings/dashboard-settings',
            'description' => 'Pending order and cart abandonment alert thresholds',
            'icon' => 'heroicon-o-presentation-chart-line',
            'sort' => 40,
        ],
        'shipping-settings' => [
            'class' => ShippingSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Shipping Engine',
            'url' => '/admin/settings/shipping-settings',
            'description' => 'Delivery zones, flat rates, thresholds',
            'icon' => 'heroicon-o-truck',
            'sort' => 50,
        ],
        'tax-settings' => [
            'class' => TaxSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Tax Configurations',
            'url' => '/admin/settings/tax-settings',
            'description' => 'EU VAT rates, company VAT verification',
            'icon' => 'heroicon-o-calculator',
            'sort' => 60,
        ],
        'checkout-settings' => [
            'class' => CheckoutSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Checkout',
            'url' => '/admin/settings/checkout-settings',
            'description' => 'Payment methods, timeouts, customer messages',
            'icon' => 'heroicon-o-credit-card',
            'sort' => 70,
        ],
        'payment-settings' => [
            'class' => PaymentSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Payment Gateways',
            'url' => '/admin/settings/payment-settings',
            'description' => 'Airwallex / Paysera card processing, B2B bank details',
            'icon' => 'heroicon-o-credit-card',
            'sort' => 80,
        ],
        'email-settings' => [
            'class' => EmailSettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Email Setup',
            'url' => '/admin/settings/email-settings',
            'description' => 'SMTP servers, from headers, admin alerts',
            'icon' => 'heroicon-o-envelope',
            'sort' => 90,
        ],
        'part-inquiry-settings' => [
            'class' => PartInquirySettings::class,
            'type' => 'page',
            'section' => 'store-operations',
            'title' => 'Part Inquiry',
            'url' => '/admin/settings/part-inquiry-settings',
            'description' => 'Response time SLA, guest inquiry limits',
            'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
            'sort' => 110,
        ],
        'search-settings' => [
            'class' => SearchSettings::class,
            'type' => 'page',
            'section' => 'search-catalog',
            'title' => 'Search Engine',
            'url' => '/admin/settings/search-settings',
            'description' => 'OEM normalized search query controls',
            'icon' => 'heroicon-o-magnifying-glass',
            'sort' => 10,
        ],
        'pdp-settings' => [
            'class' => PdpSettings::class,
            'type' => 'page',
            'section' => 'search-catalog',
            'title' => 'Product Page Sections',
            'url' => '/admin/settings/pdp-settings',
            'description' => 'Specifications, warranty, video, reviews, related products, Buy Now',
            'icon' => 'heroicon-o-document-text',
            'sort' => 20,
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
        'maintenance-settings' => [
            'class' => MaintenanceSettings::class,
            'type' => 'page',
            'section' => 'system-maintenance',
            'title' => 'Maintenance & Backups',
            'url' => '/admin/settings/maintenance-settings',
            'description' => 'Maintenance display, backup triggers',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'sort' => 10,
        ],
        'about-license-settings' => [
            'class' => AboutLicenseSettings::class,
            'type' => 'page',
            'section' => 'system-maintenance',
            'title' => 'About & License',
            'url' => '/admin/settings/about-license-settings',
            'description' => 'Platform version, PHP/MySQL info, MIT license',
            'icon' => 'heroicon-o-information-circle',
            'sort' => 20,
        ],
        'database-settings' => [
            'class' => DatabaseSettings::class,
            'type' => 'page',
            'section' => 'system-maintenance',
            'title' => 'Database Info',
            'url' => '/admin/settings/database-settings',
            'description' => 'Connection status, table summary, maintenance',
            'icon' => 'heroicon-o-server-stack',
            'sort' => 30,
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
