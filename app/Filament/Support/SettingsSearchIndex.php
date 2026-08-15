<?php

namespace App\Filament\Support;

/**
 * Static search index powering the admin Ctrl+K settings palette
 * (SettingsSearchController + resources/views/filament/topbar/settings-search.blade.php).
 *
 * Filament's built-in global search only covers Eloquent Resources
 * (vendor/filament/filament/src/GlobalSearch/Providers/DefaultGlobalSearchProvider.php
 * iterates Filament::getResources() — Filament\Pages\Page, which every
 * SettingsPage is, has no search-registration hook at all), so this is a
 * hand-maintained index rather than something derived automatically.
 *
 * This is real ongoing maintenance debt: adding/renaming/removing a field
 * on any settings page does NOT automatically update this file — a
 * developer has to remember to update ENTRIES() too. The one thing that
 * IS enforced automatically is page-level coverage — see
 * tests/Feature/SettingsSearchIndexTest.php's assertion that every
 * SettingsRegistry::PAGES type:'page' entry appears at least once here.
 * There is no equivalent guarantee at the field level.
 *
 * Deep links use SettingsPage's own `?tab={1-based index}` convention
 * (each tabbed page's Tabs::make(...)->activeTab(fn () => (int)
 * request()->query('tab', 1))) — NOT Filament's persistTabInQueryString()
 * slug-keyed mechanism, which none of these pages opt into. Tab numbers
 * below must match each page's own tabs([...]) array order exactly.
 */
final class SettingsSearchIndex
{
    /**
     * @return array<int, array{section: string, page: string, tab: ?string, label: string, keywords: string, url: string}>
     */
    public static function entries(): array
    {
        return [
            ...self::generalBrand(),
            ...self::appearance(),
            ...self::customization(),
            ...self::storeOperations(),
            ...self::searchCatalog(),
            ...self::seo(),
            ...self::marketing(),
            ...self::performance(),
            ...self::securityAccess(),
            ...self::localization(),
            ...self::systemMaintenance(),
            ...self::tools(),
        ];
    }

    /**
     * Case-insensitive substring match against label + keywords + tab +
     * page + section. Label matches rank above keyword-only matches;
     * ties keep index order (roughly page/tab declaration order).
     *
     * @return array<int, array{section: string, page: string, tab: ?string, label: string, url: string}>
     */
    public static function search(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $needle = mb_strtolower($query);

        $scored = [];

        foreach (self::entries() as $entry) {
            $haystackLabel = mb_strtolower($entry['label']);
            $haystackAll = mb_strtolower($entry['label'] . ' ' . $entry['keywords'] . ' ' . ($entry['tab'] ?? '') . ' ' . $entry['page'] . ' ' . $entry['section']);

            if (str_contains($haystackLabel, $needle)) {
                $score = str_starts_with($haystackLabel, $needle) ? 0 : 1;
            } elseif (str_contains($haystackAll, $needle)) {
                $score = 2;
            } else {
                continue;
            }

            $scored[] = ['score' => $score, 'entry' => $entry];
        }

        usort($scored, fn (array $a, array $b) => $a['score'] <=> $b['score']);

        return collect($scored)
            ->take($limit)
            ->map(fn (array $row) => [
                'section' => $row['entry']['section'],
                'page' => $row['entry']['page'],
                'tab' => $row['entry']['tab'],
                'label' => $row['entry']['label'],
                'url' => $row['entry']['url'],
            ])
            ->values()
            ->all();
    }

    private static function generalBrand(): array
    {
        $page = 'General & Brand';
        $section = 'Brand & Storefront';
        $base = '/admin/settings/general-brand-settings';

        return [
            self::row($section, $page, 'Site Identity', 'Site Logo', 'branding upload image organization schema', "$base?tab=1"),
            self::row($section, $page, 'Site Identity', 'Favicon', 'browser tab icon', "$base?tab=1"),
            self::row($section, $page, 'Site Identity', 'Site Name', 'brand title', "$base?tab=1"),
            self::row($section, $page, 'Site Identity', 'Site URL', 'domain canonical', "$base?tab=1"),
            self::row($section, $page, 'Site Identity', 'Public Contact Email', 'header footer email', "$base?tab=1"),
            self::row($section, $page, 'Site Identity', 'Public Contact Phone', 'header footer phone', "$base?tab=1"),
            self::row($section, $page, 'Site Identity', 'Homepage Routing', 'set as homepage page resource is_homepage', "$base?tab=1"),
            self::row($section, $page, 'Company & Legal', 'Company Name', 'invoice legal', "$base?tab=2"),
            self::row($section, $page, 'Company & Legal', 'VAT Number', 'company vat tax registration', "$base?tab=2"),
            self::row($section, $page, 'Company & Legal', 'Registration Number', 'company legal', "$base?tab=2"),
            self::row($section, $page, 'Company & Legal', 'Managing Director / Authorised Representative', 'legal notice tmg impressum', "$base?tab=2"),
            self::row($section, $page, 'Company & Legal', 'Company Email', 'legal invoice', "$base?tab=2"),
            self::row($section, $page, 'Company & Legal', 'Company Phone', 'legal invoice', "$base?tab=2"),
            self::row($section, $page, 'Company & Legal', 'Registered Address', 'legal invoice impressum', "$base?tab=2"),
            self::row($section, $page, 'Regional Defaults', 'Store Tagline', 'footer slogan', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'Default Frontend Language', 'locale i18n', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'System Timezone', '', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'System Date Format', '', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'Base Store Currency', 'eur usd gbp price', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'Currency Character', 'symbol', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'Symbol Position', 'currency before after', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'Decimal Separator', 'number format', "$base?tab=3"),
            self::row($section, $page, 'Regional Defaults', 'Thousand Separator', 'number format', "$base?tab=3"),
            self::row($section, $page, 'Script Injection', 'Header Injection Block', 'tracking script head custom code', "$base?tab=4"),
            self::row($section, $page, 'Script Injection', 'Footer Injection Block', 'tracking script body custom code', "$base?tab=4"),
        ];
    }

    private static function appearance(): array
    {
        $page = 'Appearance';
        $section = 'Brand & Storefront';
        $base = '/admin/settings/appearance-settings';

        return [
            self::row($section, $page, 'Colors & CSS', 'Primary Brand Color', 'theme color picker', "$base?tab=1"),
            self::row($section, $page, 'Colors & CSS', 'Accent/Highlight Color', 'theme color picker badges', "$base?tab=1"),
            self::row($section, $page, 'Colors & CSS', 'Enable Custom CSS Stylesheet', 'custom css inject', "$base?tab=1"),
            self::row($section, $page, 'Colors & CSS', 'Stylesheet Rules', 'custom css', "$base?tab=1"),
            self::row($section, $page, 'Preloader', 'Enable Preloader', 'loading screen animation spinner', "$base?tab=2"),
            self::row($section, $page, 'Preloader', 'Path Mode', 'preloader include exclude pages', "$base?tab=2"),
            self::row($section, $page, 'Preloader', 'Path Patterns', 'preloader urls', "$base?tab=2"),
            self::row($section, $page, 'Preloader', 'Minimum Display Time', 'preloader animation ms', "$base?tab=2"),
            self::row($section, $page, 'Preloader', 'Maximum Display Time', 'preloader animation ms', "$base?tab=2"),
            self::row($section, $page, 'Preloader', 'Preloader Text Locales', 'headline spec line subline status footer aria label translations', "$base?tab=2"),
            self::row($section, $page, 'Stats Counter', 'Total Active Customers', 'homepage hero trust numbers', "$base?tab=3"),
            self::row($section, $page, 'Stats Counter', 'Catalog Part Numbers Count', 'homepage hero oem numbers', "$base?tab=3"),
            self::row($section, $page, 'Stats Counter', 'Countries Served Count', 'homepage hero', "$base?tab=3"),
            self::row($section, $page, 'Stats Counter', 'Store Trust Rating', 'homepage hero rating stars', "$base?tab=3"),
            self::row($section, $page, 'Stats Counter', 'Processed Orders Count', 'homepage hero', "$base?tab=3"),
            self::row($section, $page, 'Stats Counter', 'Render Stats Section on Homepage', 'toggle visibility', "$base?tab=3"),
        ];
    }

    private static function customization(): array
    {
        $page = 'Customization';
        $section = 'Brand & Storefront';
        $base = '/admin/settings/customization-settings';

        return [
            self::row($section, $page, 'Hero & UI Copy', 'Hero Text Locales', 'index badge live status eyebrow subtext search strip indexed label homepage banner', "$base?tab=1"),
            self::row($section, $page, 'Hero & UI Copy', 'Spec Table Locales', 'homepage hero specification rows catalogue manufacturers cross-refs despatch languages', "$base?tab=1"),
            self::row($section, $page, 'Hero & UI Copy', 'Footer Pills Locales', 'homepage hero verified suppliers secure checkout countries', "$base?tab=1"),
            self::row($section, $page, 'Navbar Copy', 'Account Menu Label', 'header navigation', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'My Account Link', 'header navigation', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'Sign In Link', 'header navigation login', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'Sign In / Register Link', 'header navigation', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'Cart Label', 'mini-cart header', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'Mini-Cart Title', 'header dropdown', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'Subtotal Label', 'mini-cart', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'View Cart Button', 'mini-cart', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'Checkout Button', 'mini-cart', "$base?tab=2"),
            self::row($section, $page, 'Navbar Copy', 'Remove Item Label', 'mini-cart', "$base?tab=2"),
            self::row($section, $page, 'Footer Badges & Stats', 'OEM Badge', 'trust footer genuine parts', "$base?tab=3"),
            self::row($section, $page, 'Footer Badges & Stats', 'Shipping Badge', 'trust footer', "$base?tab=3"),
            self::row($section, $page, 'Footer Badges & Stats', 'Returns Badge', 'trust footer', "$base?tab=3"),
            self::row($section, $page, 'Footer Badges & Stats', 'Security Badge', 'trust footer', "$base?tab=3"),
            self::row($section, $page, 'Footer Badges & Stats', 'Stat — OEM Numbers', 'footer stats parts count', "$base?tab=3"),
            self::row($section, $page, 'Footer Badges & Stats', 'Stat — Countries', 'footer stats', "$base?tab=3"),
            self::row($section, $page, 'Footer Badges & Stats', 'Stat — Languages', 'footer stats', "$base?tab=3"),
            self::row($section, $page, 'Footer Badges & Stats', 'Accepted Payment Labels', 'footer visa mastercard sepa badges', "$base?tab=3"),
            self::row($section, $page, 'Announcement Bar', 'Enable Announcement Bar', 'marquee banner promo top bar', "$base?tab=4"),
            self::row($section, $page, 'Announcement Bar', 'Allow Users to Dismiss', 'announcement close', "$base?tab=4"),
            self::row($section, $page, 'Announcement Bar', 'Announcement Text', 'banner promo message', "$base?tab=4"),
            self::row($section, $page, 'Announcement Bar', 'CTA Button Text', 'announcement call to action', "$base?tab=4"),
            self::row($section, $page, 'Announcement Bar', 'Background Color', 'announcement banner', "$base?tab=4"),
            self::row($section, $page, 'Announcement Bar', 'Text Color', 'announcement banner', "$base?tab=4"),
            self::row($section, $page, 'Announcement Bar', 'Link URL', 'announcement banner clickable', "$base?tab=4"),
            self::row($section, $page, 'Homepage Section Limits', 'Testimonials Section Limit', 'homepage count', "$base?tab=5"),
            self::row($section, $page, 'Homepage Section Limits', 'FAQ Section Limit', 'homepage count', "$base?tab=5"),
            self::row($section, $page, 'Homepage Section Limits', 'Blog Section Limit', 'homepage count', "$base?tab=5"),
            self::row($section, $page, 'Homepage Section Limits', 'Manufacturers Section Limit', 'homepage count brands', "$base?tab=5"),
            self::row($section, $page, 'Menus & Social', 'Active Menus', 'navigation header footer content menus resource', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Show "About Us" in Footer', 'footer links', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Show "Contact" in Footer', 'footer links', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Show "FAQ" in Footer', 'footer links', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Show "Blog" in Footer', 'footer links', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Facebook URL', 'social media', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Instagram URL', 'social media', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'X (Twitter) URL', 'social media', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'LinkedIn URL', 'social media', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'YouTube URL', 'social media', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'TikTok URL', 'social media', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Show Social Icons in Footer', 'social display', "$base?tab=6"),
            self::row($section, $page, 'Menus & Social', 'Icon Style', 'social filled outlined', "$base?tab=6"),
        ];
    }

    private static function storeOperations(): array
    {
        $page = 'Store Operations';
        $section = 'Store & Commerce';
        $base = '/admin/settings/store-operations-settings';

        return [
            self::row($section, $page, 'Orders Policy', 'Bank Transfer Payment Limit', 'expiry hours cancellation', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'Client Cancel Grace Period', 'customer cancel window', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'Portal RMA Return Limit', 'refund window days returns', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'Minimum Store Order Value', 'minimum order amount', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'Auto-Complete Order Fulfillment', 'shipped delivered nightly', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'Order Number Prefix', 'ORD numbering', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'Number Sequence Zero Padding', 'order numbering digits', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'Invoice Number Prefix', 'INV numbering', "$base?tab=1"),
            self::row($section, $page, 'Orders Policy', 'RMA Return Ticket Prefix', 'numbering', "$base?tab=1"),
            self::row($section, $page, 'Customers', 'VIP — Minimum Orders', 'segment badge threshold', "$base?tab=2"),
            self::row($section, $page, 'Customers', 'VIP — Minimum Total Spend', 'segment badge threshold', "$base?tab=2"),
            self::row($section, $page, 'Customers', 'Repeat — Minimum Orders', 'segment badge threshold', "$base?tab=2"),
            self::row($section, $page, 'Cart Rules', 'Inactive Cart Lifespan', 'abandoned cart expiry days', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Maximum Unique Items per Cart', '', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Price Change Alert Threshold', 'cart warn customer', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Require OTP for Guest Checkouts', 'verification code', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Enable Checkout Coupons', 'promo code cart', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Merge Guest Cart on Login', '', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Cart Add Rate Limit', 'per minute throttle', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Maximum Quantity per Line Item', '', "$base?tab=3"),
            self::row($section, $page, 'Cart Rules', 'Guest Cart Cookie Lifetime', 'days', "$base?tab=3"),
            self::row($section, $page, 'Dashboard Alert Thresholds', 'Pending Orders Alert Threshold', 'dashboard widget', "$base?tab=4"),
            self::row($section, $page, 'Dashboard Alert Thresholds', 'Delayed Order Threshold', 'dashboard minutes', "$base?tab=4"),
            self::row($section, $page, 'Dashboard Alert Thresholds', 'Cart Abandonment Threshold', 'dashboard recovery email hours', "$base?tab=4"),
            self::row($section, $page, 'Dashboard Alert Thresholds', 'Scheduler Heartbeat Stale Threshold', 'health check cron', "$base?tab=4"),
            self::row($section, $page, 'Dashboard Alert Thresholds', 'Backup Stale Threshold', 'health check hours', "$base?tab=4"),
            self::row($section, $page, 'Dashboard Alert Thresholds', 'Cache Hit Rate Alert Threshold', 'redis percent', "$base?tab=4"),
            self::row($section, $page, 'Shipping', 'Free Shipping Threshold', 'shipping zones resource nudge', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Standard Order Handling Fee', '', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Enable Free Shipping Nudge Alert', 'cart notice', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Nudge Trigger Remaining Amount', 'free shipping cart', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Free Shipping Nudge Text', 'cart message translatable', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Shipping Note', 'checkout step 3 delivery insured tracked', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Business Days', 'processing shipped delivery', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Default Origin Country', 'ship-from rate calculation', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Fulfillment Cutoff Time', 'business day dispatch', "$base?tab=5"),
            self::row($section, $page, 'Shipping', 'Operational Timezone', 'cutoff', "$base?tab=5"),
            self::row($section, $page, 'Tax', 'Default Store VAT Percent', 'fallback rate', "$base?tab=6"),
            self::row($section, $page, 'Tax', 'Storefront Catalog Price Display', 'inc vat ex vat b2b b2c', "$base?tab=6"),
            self::row($section, $page, 'Tax', 'Enable Country-Based VAT', 'per-country rates tax rate resource', "$base?tab=6"),
            self::row($section, $page, 'Tax', 'Enable VIES VAT Validation', 'eu vat number verification api', "$base?tab=6"),
            self::row($section, $page, 'Checkout & Payments', 'Default Payment Method', 'card paysera bank transfer', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Checkout Timeout', 'minutes session', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Maximum Checkout Steps', '', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Allowed Payment Methods', 'card paysera bank transfer enable disable', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Enable Apple Pay', 'airwallex drop-in', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Enable Google Pay', 'airwallex drop-in', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Payment Success Message', 'checkout feedback', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Payment Error Message', 'checkout feedback', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Customer Note Max Length', '', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Payment Proof Max File Size', 'bank transfer upload kb', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Guest Account Generated Password Length', '', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Airwallex Gateway API Environment', 'sandbox live production card', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Airwallex Client ID Key', 'card gateway credentials', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Airwallex API Private Key', 'card gateway credentials secret', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Airwallex Webhook Signoff Secret', 'card gateway', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Airwallex Merchant Country Code', 'apple pay google pay', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Hold Funds Until Shipment', 'airwallex manual capture authorize', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Paysera Gateway API Environment', 'sandbox live card', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Paysera Client ID', 'card gateway credentials', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Paysera Client Secret', 'card gateway credentials', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Paysera Webhook Signoff Secret', 'card gateway hmac', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'B2B Bank Transfer Details', 'iban bic swift bank name account holder reference prefix', "$base?tab=7"),
            self::row($section, $page, 'Checkout & Payments', 'Rush Processing Upsell (moved)', 'urgent processing marketing cross-link', "$base?tab=7"),
            self::row($section, $page, 'Email Setup', 'Sender Name', 'from name outgoing', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'Sender Email (From)', 'from address outgoing', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'Reply-To Email Address', '', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'SMTP Server Host', 'mail server', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'SMTP Port', 'mail server 587 465', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'Connection Encryption', 'smtp tls ssl', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'SMTP Auth Username', 'mail credentials', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'SMTP Auth Password', 'mail credentials encrypted', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'Notify Admin on New Order', 'email alert', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'Notify Admin on New Parts Inquiry', 'email alert', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'Recipient Admin Email', 'notifications', "$base?tab=8"),
            self::row($section, $page, 'Email Setup', 'Send Test Email', 'smtp verify connection', "$base?tab=8"),
            self::row($section, $page, 'Customer Service', 'Support Phone Number', 'contact', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Support Contact Email', 'contact', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Business Address', 'contact support location', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Business Hours', 'operating hours footer contact', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'WhatsApp Number', 'chat hotline', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Viber Number', 'chat hotline', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Social Profiles (cross-link)', 'facebook linkedin customization', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Contact Form Success Message', 'translatable', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Contact Form Rate Limit', 'submissions per minute abuse protection', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Expected Response Time', 'part inquiry hours', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Allow Guest Inquiries', 'part inquiry', "$base?tab=9"),
            self::row($section, $page, 'Customer Service', 'Max Inquiries Per IP Per Hour', 'part inquiry rate limit', "$base?tab=9"),
            self::row($section, $page, 'Invoice', 'Payment Due (Days After Invoice Date)', 'payment terms', "$base?tab=10"),
            self::row($section, $page, 'Invoice', 'Thank You Text', 'invoice pdf translatable', "$base?tab=10"),
        ];
    }

    private static function searchCatalog(): array
    {
        $page = 'Search & Catalog';
        $section = 'Store & Commerce';
        $base = '/admin/settings/search-catalog-settings';

        return [
            self::row($section, $page, 'Internal Search', 'Minimum Query Characters', 'search bar', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Max Autocomplete Dropdown Options', 'search bar', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Rate Limit (Queries per Minute)', 'search bot protection', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Max Results per Search Page', '', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Log All Keyword Search Queries', 'analytics', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Log Failed Searches', 'zero-result queries analytics', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Enable Cross-Reference Identifiers', 'oem replacement codes', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Enable Partial Code Matching', 'oem search', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Partial Match Code Min Length', '', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Supported Autocomplete Languages', 'locale codes api', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Default Results Limit', '', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Results per Page', 'pagination', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Popular Searches Window', 'days trending', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Popular Results Limit', 'trending', "$base?tab=1"),
            self::row($section, $page, 'Internal Search', 'Popular Results Cache TTL', 'trending hours', "$base?tab=1"),
            self::row($section, $page, 'Product Page Sections', 'Specifications Table', 'product detail page pdp', "$base?tab=2"),
            self::row($section, $page, 'Product Page Sections', 'Warranty Block', 'product detail page pdp', "$base?tab=2"),
            self::row($section, $page, 'Product Page Sections', 'Product Video', 'product detail page pdp', "$base?tab=2"),
            self::row($section, $page, 'Product Page Sections', 'Customer Reviews', 'product detail page pdp approval', "$base?tab=2"),
            self::row($section, $page, 'Product Page Sections', 'Related Products', 'product detail page pdp fitment', "$base?tab=2"),
            self::row($section, $page, 'Product Page Sections', 'Sticky Mobile Add-to-Cart Bar', 'product detail page pdp buy now', "$base?tab=2"),
            self::row($section, $page, 'Product Page Sections', 'Buy Now One-Click Checkout Button', 'product detail page pdp skip cart', "$base?tab=2"),
            self::row($section, $page, 'Product Page Sections', 'Review Submission Rate Limit', 'spam protection', "$base?tab=2"),
        ];
    }

    private static function seo(): array
    {
        $page = 'SEO Control Center';
        $section = 'Growth';
        $base = '/admin/settings/seo-settings';

        return [
            self::row($section, $page, 'General', 'Homepage & Brand Meta Locales', 'title description templates', "$base?tab=1"),
            self::row($section, $page, 'General', 'Enable per-product detail pages', 'parts oem url 301', "$base?tab=1"),
            self::row($section, $page, 'Search Results', 'Search Results Meta', 'title description templates oem', "$base?tab=2"),
            self::row($section, $page, 'Structured Data', 'Structured Data / Schema.org', 'json-ld organization product', "$base?tab=3"),
            self::row($section, $page, 'Crawlers & AI', 'Robots / Crawler Rules', 'ai bots gptbot googlebot', "$base?tab=4"),
            self::row($section, $page, 'Sitemap & Indexing', 'Search Engine Pings', 'google bing sitemap regenerate', "$base?tab=5"),
            self::row($section, $page, 'Sitemap & Indexing', 'Canonical Host', 'www redirect domain', "$base?tab=5"),
            self::row($section, $page, 'Sitemap & Indexing', 'Redirects (cross-link)', '301 retired moved urls redirect resource', "$base?tab=5"),
            self::row($section, $page, 'Sitemap & Indexing', 'SEO Meta (cross-link)', 'per-page title description overrides seo meta resource', "$base?tab=5"),
            self::row($section, $page, 'Social', 'Open Graph Defaults', 'facebook linkedin discord share image', "$base?tab=6"),
            self::row($section, $page, 'General', 'Google/Bing Search Console Verification', 'webmaster gsc bing verification meta tag', "$base?tab=1"),
        ];
    }

    private static function marketing(): array
    {
        $page = 'Marketing';
        $section = 'Growth';
        $base = '/admin/settings/marketing-settings';

        return [
            self::row($section, $page, 'Integrations', 'Google Tag Manager Container ID', 'gtm tracking script', "$base?tab=1"),
            self::row($section, $page, 'Integrations', 'Search Console Verification (cross-link)', 'gsc seo', "$base?tab=1"),
            self::row($section, $page, 'Integrations', 'GA4 Stream Measurement ID', 'google analytics tracking', "$base?tab=1"),
            self::row($section, $page, 'Integrations', 'Facebook Pixel ID', 'tracking ads', "$base?tab=1"),
            self::row($section, $page, 'Integrations', 'Crisp Website ID', 'live chat widget support', "$base?tab=1"),
            self::row($section, $page, 'Newsletter', 'Max Subscriptions Per IP Per Hour', 'rate limit', "$base?tab=2"),
            self::row($section, $page, 'Newsletter', 'Rate Window', 'seconds newsletter', "$base?tab=2"),
            self::row($section, $page, 'Newsletter', 'Double Opt-In', 'email confirmation subscribe', "$base?tab=2"),
            self::row($section, $page, 'Rush Processing Upsell', 'Offer Rush Processing at Checkout', 'urgent processing fast-track fee', "$base?tab=3"),
            self::row($section, $page, 'Rush Processing Upsell', 'Rush Processing Fee', 'urgent processing', "$base?tab=3"),
            self::row($section, $page, 'Rush Processing Upsell', 'Rush Processing Copy', 'checkout option label description translatable', "$base?tab=3"),
            self::row($section, $page, 'Rush Processing Upsell', 'Coupons (cross-link)', 'discount codes promo campaigns coupon resource', "$base?tab=3"),
        ];
    }

    private static function performance(): array
    {
        $page = 'Performance & Cache';
        $section = 'Growth';
        $base = '/admin/settings/performance-settings';

        return [
            self::row($section, $page, null, 'Cache Page Sections', 'ttl navigation', $base),
            self::row($section, $page, null, 'Cache Brand/Manufacturer Queries', 'ttl', $base),
            self::row($section, $page, null, 'Cache Condition List', 'new used refurbished', $base),
            self::row($section, $page, null, 'Cache Homepage Hero Stats', 'ttl', $base),
            self::row($section, $page, null, 'Cache Search Console Status Panel', 'ttl', $base),
            self::row($section, $page, null, 'Optimize Uploaded Images', 'compression resize', $base),
            self::row($section, $page, null, 'Convert to WebP', 'image format', $base),
            self::row($section, $page, null, 'Compression Quality', 'images', $base),
            self::row($section, $page, null, 'Max Width / Max Height', 'image upload cap', $base),
            self::row($section, $page, null, 'Lazy-Load Below-the-Fold Images', 'performance loading', $base),
            self::row($section, $page, null, 'Cloudflare Integration', 'cdn zone id api token purge cache', $base),
            self::row($section, $page, null, 'Test Cache Driver', 'redis verify', $base),
            self::row($section, $page, null, 'Test Cloudflare Connection', 'cdn', $base),
            self::row($section, $page, null, 'Purge Cloudflare Cache', 'cdn edge', $base),
        ];
    }

    private static function securityAccess(): array
    {
        $page = 'Security & Access';
        $section = 'Platform';
        $base = '/admin/settings/security-access-settings';

        return [
            self::row($section, $page, 'Authentication', 'OTP Code Length', 'one-time password digits', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'OTP Code Lifetime', 'expiry minutes', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Maximum Login Attempts (OTP)', '', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Resend Cooldown', 'otp seconds', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Customer Password Min Length', '', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Admin Panel Password Min Length', '', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Portal Session Lifetime', 'customer minutes', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Allow Guest Checkout', '', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Enable Customer Registration', 'signup', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Google Client ID / Secret', 'oauth social login', "$base?tab=1"),
            self::row($section, $page, 'Authentication', 'Facebook App ID / Secret', 'oauth social login', "$base?tab=1"),
            self::row($section, $page, 'Firewall & Sessions', 'Enable OTP / Two-Step Verification', 'storefront master switch', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'Max Login Attempts', 'brute force throttle', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'Login Attempt Time Window', 'minutes throttle', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'Max Daily Custom Quotes per Email', 'part inquiry throttle', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'IP Blocklist (cross-link)', 'banned ip cidr', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'Permission Matrix (cross-link)', 'role permission grants', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'Roles/Admins (cross-link)', 'admin accounts named roles', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'Admin Session Lifetime', 'minutes', "$base?tab=2"),
            self::row($section, $page, 'Firewall & Sessions', 'Log Retention', 'audit gdpr days login activity', "$base?tab=2"),
        ];
    }

    private static function localization(): array
    {
        $page = 'Localization';
        $section = 'Platform';
        $base = '/admin/settings/localization-settings';

        return [
            self::row($section, $page, null, 'Languages (cross-link)', 'locale add remove supported', $base),
            self::row($section, $page, null, 'Translations (cross-link)', 'language strings translation resource', $base),
        ];
    }

    private static function systemMaintenance(): array
    {
        $page = 'System & Maintenance';
        $section = 'Platform';
        $base = '/admin/settings/system-maintenance-settings';

        return [
            self::row($section, $page, 'Maintenance Mode', 'Enable Maintenance Mode', 'offline storefront down', "$base?tab=1"),
            self::row($section, $page, 'Maintenance Mode', 'Emergency Support Email', 'maintenance page', "$base?tab=1"),
            self::row($section, $page, 'Maintenance Mode', 'Maintenance Message', 'translatable', "$base?tab=1"),
            self::row($section, $page, 'Maintenance Mode', 'Bypass IP Whitelist', 'maintenance allowed ips', "$base?tab=1"),
            self::row($section, $page, 'Maintenance Mode', 'Estimated Return Time', 'maintenance countdown', "$base?tab=1"),
            self::row($section, $page, 'Maintenance Mode', 'HTTP Retry-After Header', 'maintenance seo crawler', "$base?tab=1"),
            self::row($section, $page, 'About & Database', 'Application / Laravel / PHP / MySQL Version', 'about platform info', "$base?tab=2"),
            self::row($section, $page, 'About & Database', 'License', 'mit about', "$base?tab=2"),
            self::row($section, $page, 'About & Database', 'Database Connection Status', 'driver name host', "$base?tab=2"),
            self::row($section, $page, 'About & Database', 'Table Summary', 'row counts size', "$base?tab=2"),
        ];
    }

    private static function tools(): array
    {
        $section = 'Platform';
        $tab = null;

        return [
            self::row($section, 'Backup Management', $tab, 'Backup Management', 'encrypted database files restore retention schedule', '/admin/system/backup-dashboard'),
            self::row($section, 'System Updates', $tab, 'System Updates', 'release channel apply rollback', '/admin/system/system-updates'),
            self::row($section, 'Update History', $tab, 'Update History', 'past update runs outcomes', '/admin/system/update-history-page'),
            self::row($section, 'Health Check', $tab, 'Health Check', 'live system status snapshot', '/admin/system/health-check-dashboard'),
            self::row($section, 'Cache Dashboard', $tab, 'Cache Dashboard', 'redis driver status metrics hit rate', '/admin/system/cache-dashboard'),
            self::row($section, 'Queue Monitor', $tab, 'Queue Monitor', 'jobs processing status', '/admin/system/queue-monitor'),
            self::row($section, 'Failed Jobs', $tab, 'Failed Jobs', 'retry delete queue', '/admin/system/failed-jobs-page'),
            self::row($section, 'Scheduled Tasks', $tab, 'Scheduled Tasks', 'cron schedule last run', '/admin/system/scheduled-tasks-page'),
            self::row($section, 'Error Monitor', $tab, 'Error Monitor', 'recent application errors exceptions', '/admin/system/error-monitor'),
            self::row($section, 'Log Viewer', $tab, 'Log Viewer', 'application log files', '/admin/system/log-viewer-page'),
            self::row($section, 'Server Monitor', $tab, 'Server Monitor', 'cpu memory disk php process', '/admin/system/server-monitor'),
            self::row($section, 'Permission Matrix', $tab, 'Permission Matrix', 'role permission grants', '/admin/system/permission-matrix'),
            self::row($section, 'Help & Documentation', $tab, 'Help & Documentation', 'admin panel usage docs', '/admin/system/help-page'),
        ];
    }

    /**
     * @return array{section: string, page: string, tab: ?string, label: string, keywords: string, url: string}
     */
    private static function row(string $section, string $page, ?string $tab, string $label, string $keywords, string $url): array
    {
        return compact('section', 'page', 'tab', 'label', 'keywords', 'url');
    }
}
