<?php

namespace App\Filament\Support;

use App\Filament\Pages\Settings\AboutLicenseSettings;
use App\Filament\Pages\Settings\AnnouncementSettings;
use App\Filament\Pages\Settings\AppearanceSettings;
use App\Filament\Pages\Settings\AuthSettings;
use App\Filament\Pages\Settings\CartSettings;
use App\Filament\Pages\Settings\CheckoutSettings;
use App\Filament\Pages\Settings\CompanySettings;
use App\Filament\Pages\Settings\ContactSettings;
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
use App\Filament\Pages\Settings\PerformanceSettings;
use App\Filament\Pages\Settings\PreloaderSettings;
use App\Filament\Pages\Settings\SearchSettings;
use App\Filament\Pages\Settings\SectionsSettings;
use App\Filament\Pages\Settings\SecuritySettings;
use App\Filament\Pages\Settings\SEOSettings;
use App\Filament\Pages\Settings\SettingsActivityLog;
use App\Filament\Pages\Settings\ShippingSettings;
use App\Filament\Pages\Settings\SocialLinkSettings;
use App\Filament\Pages\Settings\StatsCounterSettings;
use App\Filament\Pages\Settings\StoreSettings;
use App\Filament\Pages\Settings\TaxSettings;
use App\Filament\Pages\Settings\UISettings;

/**
 * Declarative source of truth for which settings pages are reachable from
 * the Settings cluster grid (resources/views/filament/clusters/settings.blade.php).
 *
 * Every settings page has $shouldRegisterNavigation = false, so Filament's
 * own nav tree never lists them, and $navigationSort on each page class is
 * vestigial. Before this registry existed, the cluster Blade view hardcoded
 * a $sections array directly — a page absent from that array existed and
 * saved data correctly but was completely unreachable from the UI, with no
 * error anywhere (this happened to UISettings; see ARCHITECTURE.md). This
 * registry plus tests/Feature/SettingsRegistryTest.php closes that gap by
 * making every SettingsPage subclass require an entry here, checked by a
 * test rather than relying on someone remembering to edit the Blade array.
 */
final class SettingsRegistry
{
    public const SECTIONS = [
        'General & Brand' => [
            'icon' => 'heroicon-o-identification',
            'keywords' => 'general brand appearance contact announcement logo name theme',
        ],
        'Store Operations' => [
            'icon' => 'heroicon-o-cog',
            'keywords' => 'orders cart shipping payment tax email smtp bank transfer airwallex company store checkout',
        ],
        'SEO & Marketing' => [
            'icon' => 'heroicon-o-globe-alt',
            'keywords' => 'seo search engine performance cache redis stats counter meta opengraph preloader newsletter sections ui hero homepage',
        ],
        'System & Security' => [
            'icon' => 'heroicon-o-shield-check',
            'keywords' => 'auth security firewall ip ban otp password maintenance backup api integration gtm checkout inquiry activity log audit',
        ],
        'Menus & Social' => [
            'icon' => 'heroicon-o-globe-alt',
            'keywords' => 'menu navigation footer social facebook instagram twitter links',
        ],
        'About & Info' => [
            'icon' => 'heroicon-o-information-circle',
            'keywords' => 'about license version database info system',
        ],
    ];

    public const PAGES = [
        'general-settings' => [
            'class' => GeneralSettings::class,
            'section' => 'General & Brand',
            'title' => 'General Settings',
            'url' => '/admin/settings/general-settings',
            'description' => 'Store name, logo, basic details',
            'icon' => 'heroicon-o-cog-6-tooth',
            'sort' => 10,
        ],
        'appearance-settings' => [
            'class' => AppearanceSettings::class,
            'section' => 'General & Brand',
            'title' => 'Appearance',
            'url' => '/admin/settings/appearance-settings',
            'description' => 'Custom colors, theme styling',
            'icon' => 'heroicon-o-paint-brush',
            'sort' => 20,
        ],
        'contact-settings' => [
            'class' => ContactSettings::class,
            'section' => 'General & Brand',
            'title' => 'Contact Info',
            'url' => '/admin/settings/contact-settings',
            'description' => 'Store location, support email, phone',
            'icon' => 'heroicon-o-phone',
            'sort' => 30,
        ],
        'announcement-settings' => [
            'class' => AnnouncementSettings::class,
            'section' => 'General & Brand',
            'title' => 'Announcement',
            'url' => '/admin/settings/announcement-settings',
            'description' => 'Site-wide marquee promo bar',
            'icon' => 'heroicon-o-megaphone',
            'sort' => 40,
        ],
        'company-settings' => [
            'class' => CompanySettings::class,
            'section' => 'Store Operations',
            'title' => 'Company Info',
            'url' => '/admin/settings/company-settings',
            'description' => 'Company details for invoices, legal, and emails',
            'icon' => 'heroicon-o-building-office-2',
            'sort' => 10,
        ],
        'store-settings' => [
            'class' => StoreSettings::class,
            'section' => 'Store Operations',
            'title' => 'Store Currency',
            'url' => '/admin/settings/store-settings',
            'description' => 'Currency, symbol, locale formatting',
            'icon' => 'heroicon-o-banknotes',
            'sort' => 20,
        ],
        'orders-settings' => [
            'class' => OrdersSettings::class,
            'section' => 'Store Operations',
            'title' => 'Orders Policy',
            'url' => '/admin/settings/orders-settings',
            'description' => 'Order expiry, prefix formats, status defaults',
            'icon' => 'heroicon-o-shopping-bag',
            'sort' => 30,
        ],
        'cart-settings' => [
            'class' => CartSettings::class,
            'section' => 'Store Operations',
            'title' => 'Cart Rules',
            'url' => '/admin/settings/cart-settings',
            'description' => 'Cart duration, timeout limits',
            'icon' => 'heroicon-o-shopping-cart',
            'sort' => 40,
        ],
        'shipping-settings' => [
            'class' => ShippingSettings::class,
            'section' => 'Store Operations',
            'title' => 'Shipping Engine',
            'url' => '/admin/settings/shipping-settings',
            'description' => 'Delivery zones, flat rates, thresholds',
            'icon' => 'heroicon-o-truck',
            'sort' => 50,
        ],
        'payment-settings' => [
            'class' => PaymentSettings::class,
            'section' => 'Store Operations',
            'title' => 'Payment Gateways',
            'url' => '/admin/settings/payment-settings',
            'description' => 'Airwallex card processing, B2B bank details',
            'icon' => 'heroicon-o-credit-card',
            'sort' => 60,
        ],
        'tax-settings' => [
            'class' => TaxSettings::class,
            'section' => 'Store Operations',
            'title' => 'Tax Configurations',
            'url' => '/admin/settings/tax-settings',
            'description' => 'EU VAT rates, company VAT verification',
            'icon' => 'heroicon-o-calculator',
            'sort' => 70,
        ],
        'email-settings' => [
            'class' => EmailSettings::class,
            'section' => 'Store Operations',
            'title' => 'Email Setup',
            'url' => '/admin/settings/email-settings',
            'description' => 'SMTP servers, from headers, admin alerts',
            'icon' => 'heroicon-o-envelope',
            'sort' => 80,
        ],
        'search-settings' => [
            'class' => SearchSettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'Search Engine',
            'url' => '/admin/settings/search-settings',
            'description' => 'OEM normalized search query controls',
            'icon' => 'heroicon-o-magnifying-glass',
            'sort' => 10,
        ],
        'seo-settings' => [
            'class' => SEOSettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'SEO & Meta',
            'url' => '/admin/settings/seo-settings',
            'description' => 'Global OpenGraph, robots tags, sitemap ping',
            'icon' => 'heroicon-o-globe-alt',
            'sort' => 20,
        ],
        'performance-settings' => [
            'class' => PerformanceSettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'Performance',
            'url' => '/admin/settings/performance-settings',
            'description' => 'Redis caching timeouts, optimization toggles',
            'icon' => 'heroicon-o-cpu-chip',
            'sort' => 30,
        ],
        'stats-counter-settings' => [
            'class' => StatsCounterSettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'Stats Counter',
            'url' => '/admin/settings/stats-counter-settings',
            'description' => 'Fake frontend counters (parts, clients)',
            'icon' => 'heroicon-o-presentation-chart-line',
            'sort' => 40,
        ],
        'preloader-settings' => [
            'class' => PreloaderSettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'Preloader',
            'url' => '/admin/settings/preloader-settings',
            'description' => 'Full-screen loading animation settings',
            'icon' => 'heroicon-o-arrow-path',
            'sort' => 50,
        ],
        'newsletter-settings' => [
            'class' => NewsletterSettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'Newsletter',
            'url' => '/admin/settings/newsletter-settings',
            'description' => 'Subscription rate limits and opt-in settings',
            'icon' => 'heroicon-o-envelope',
            'sort' => 60,
        ],
        'sections-settings' => [
            'class' => SectionsSettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'Sections',
            'url' => '/admin/settings/sections-settings',
            'description' => 'Homepage content section display limits',
            'icon' => 'heroicon-o-squares-2x2',
            'sort' => 70,
        ],
        'ui-settings' => [
            'class' => UISettings::class,
            'section' => 'SEO & Marketing',
            'title' => 'Homepage Hero & UI Text',
            'url' => '/admin/settings/ui-settings',
            'description' => 'Hero banner, spec table, and footer pill copy',
            'icon' => 'heroicon-o-paint-brush',
            'sort' => 80,
        ],
        'auth-security-settings' => [
            'class' => AuthSettings::class,
            'section' => 'System & Security',
            'title' => 'Auth & Security',
            'url' => '/admin/settings/auth-security-settings',
            'description' => 'OTP login limits, password complexity rules',
            'icon' => 'heroicon-o-lock-closed',
            'sort' => 10,
        ],
        'security-settings' => [
            'class' => SecuritySettings::class,
            'section' => 'System & Security',
            'title' => 'Firewall & Security',
            'url' => '/admin/settings/security-settings',
            'description' => 'Max attempts, IP bans, honeypot settings',
            'icon' => 'heroicon-o-shield-check',
            'sort' => 20,
        ],
        'checkout-settings' => [
            'class' => CheckoutSettings::class,
            'section' => 'System & Security',
            'title' => 'Checkout',
            'url' => '/admin/settings/checkout-settings',
            'description' => 'Payment methods, timeouts, customer messages',
            'icon' => 'heroicon-o-credit-card',
            'sort' => 30,
        ],
        'part-inquiry-settings' => [
            'class' => PartInquirySettings::class,
            'section' => 'System & Security',
            'title' => 'Part Inquiry',
            'url' => '/admin/settings/part-inquiry-settings',
            'description' => 'Response time SLA, guest inquiry limits',
            'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
            'sort' => 40,
        ],
        'integrations-settings' => [
            'class' => IntegrationsSettings::class,
            'section' => 'System & Security',
            'title' => 'Third-party APIs',
            'url' => '/admin/settings/integrations-settings',
            'description' => 'GTM, Google Search Console trackers',
            'icon' => 'heroicon-o-puzzle-piece',
            'sort' => 50,
        ],
        'maintenance-settings' => [
            'class' => MaintenanceSettings::class,
            'section' => 'System & Security',
            'title' => 'Maintenance & Backups',
            'url' => '/admin/settings/maintenance-settings',
            'description' => 'Maintenance display, backup triggers',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'sort' => 60,
        ],
        'settings-activity-log' => [
            'class' => SettingsActivityLog::class,
            'section' => 'System & Security',
            'title' => 'Settings Activity Log',
            'url' => '/admin/settings/settings-activity-log',
            'description' => 'Track who changed settings and when',
            'icon' => 'heroicon-o-clock',
            'sort' => 70,
        ],
        'menu-settings' => [
            'class' => MenuSettings::class,
            'section' => 'Menus & Social',
            'title' => 'Menu Settings',
            'url' => '/admin/settings/menu-settings',
            'description' => 'Configure storefront header and footer navigation',
            'icon' => 'heroicon-o-bars-3',
            'sort' => 10,
        ],
        'social-link-settings' => [
            'class' => SocialLinkSettings::class,
            'section' => 'Menus & Social',
            'title' => 'Social Links',
            'url' => '/admin/settings/social-link-settings',
            'description' => 'Social media profile URLs for footer and sharing',
            'icon' => 'heroicon-o-globe-alt',
            'sort' => 20,
        ],
        'about-license-settings' => [
            'class' => AboutLicenseSettings::class,
            'section' => 'About & Info',
            'title' => 'About & License',
            'url' => '/admin/settings/about-license-settings',
            'description' => 'Platform version, PHP/MySQL info, MIT license',
            'icon' => 'heroicon-o-information-circle',
            'sort' => 10,
        ],
        'database-settings' => [
            'class' => DatabaseSettings::class,
            'section' => 'About & Info',
            'title' => 'Database Info',
            'url' => '/admin/settings/database-settings',
            'description' => 'Connection status, table summary, maintenance',
            'icon' => 'heroicon-o-server-stack',
            'sort' => 20,
        ],
    ];

    /**
     * Builds the exact ['icon' => ..., 'keywords' => ..., 'items' => [[title,
     * url, description, icon], ...]] shape settings.blade.php already
     * consumes, grouped/ordered to match the cluster grid's prior hardcoded
     * layout byte-for-byte.
     */
    public static function sections(): array
    {
        $sections = [];

        foreach (self::SECTIONS as $heading => $meta) {
            $sections[$heading] = [
                'icon' => $meta['icon'],
                'keywords' => $meta['keywords'],
                'items' => [],
            ];
        }

        $pages = self::PAGES;
        uasort($pages, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        foreach ($pages as $page) {
            $sections[$page['section']]['items'][] = [
                $page['title'],
                $page['url'],
                $page['description'],
                $page['icon'],
            ];
        }

        return $sections;
    }

    /**
     * @return array<class-string>
     */
    public static function pageClasses(): array
    {
        return array_column(self::PAGES, 'class');
    }
}
