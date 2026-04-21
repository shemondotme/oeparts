<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = $this->definitions();

        foreach ($settings as $row) {
            Setting::updateOrCreate(
                ['group' => $row['group'], 'key' => $row['key']],
                [
                    'value'        => $row['value'],
                    'type'         => $row['type'],
                    'is_encrypted' => $row['encrypted'] ?? false,
                ]
            );
        }
    }

    private function definitions(): array
    {
        $s = SettingType::String->value;
        $b = SettingType::Boolean->value;
        $i = SettingType::Integer->value;
        $j = SettingType::Json->value;
        $e = SettingType::Encrypted->value;

        $langs = ['en', 'de', 'lt', 'fr', 'es'];

        // Helper: build a multilang JSON string with the same value for all locales
        $ml = fn(string $text) => json_encode(array_fill_keys($langs, $text));

        return [
            // ── GENERAL ──────────────────────────────────────────────────────────
            ['group' => 'general', 'key' => 'site_name',       'value' => 'OEMHub',                  'type' => $s],
            ['group' => 'general', 'key' => 'site_url',        'value' => 'http://localhost',         'type' => $s],
            ['group' => 'general', 'key' => 'site_email',      'value' => 'info@oemhub.eu',           'type' => $s],
            ['group' => 'general', 'key' => 'site_phone',      'value' => '+370 600 00000',           'type' => $s],
            ['group' => 'general', 'key' => 'site_address',    'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'logo_id',         'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'favicon_id',      'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'header_scripts',  'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'footer_scripts',  'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'tagline',         'value' => 'Genuine OEM Auto Parts',  'type' => $s],
            ['group' => 'general', 'key' => 'default_locale',  'value' => 'en',                      'type' => $s],
            ['group' => 'general', 'key' => 'timezone',        'value' => 'Europe/Vilnius',           'type' => $s],
            ['group' => 'general', 'key' => 'date_format',     'value' => 'd/m/Y',                   'type' => $s],
            ['group' => 'general', 'key' => 'currency',        'value' => 'EUR',                      'type' => $s],
            ['group' => 'general', 'key' => 'currency_symbol', 'value' => '€',                        'type' => $s],

            // ── CONTACT ──────────────────────────────────────────────────────────
            ['group' => 'contact', 'key' => 'phone',        'value' => '+370 600 00000',    'type' => $s],
            ['group' => 'contact', 'key' => 'email',        'value' => 'info@oemhub.eu',    'type' => $s],
            ['group' => 'contact', 'key' => 'address',      'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'whatsapp',     'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'viber',        'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'facebook_url', 'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'linkedin_url', 'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'hours', 'value' => $ml('Mon–Fri 9:00–18:00'), 'type' => $j],

            // ── ANNOUNCEMENT ─────────────────────────────────────────────────────
            ['group' => 'announcement', 'key' => 'enabled',    'value' => '0',          'type' => $b],
            ['group' => 'announcement', 'key' => 'text',       'value' => $ml(''),       'type' => $j],
            ['group' => 'announcement', 'key' => 'color',      'value' => '#F59E0B',    'type' => $s],
            ['group' => 'announcement', 'key' => 'text_color', 'value' => '#1E293B',    'type' => $s],
            ['group' => 'announcement', 'key' => 'dismissable','value' => '1',          'type' => $b],
            ['group' => 'announcement', 'key' => 'url',        'value' => '',            'type' => $s],

            // ── APPEARANCE ───────────────────────────────────────────────────────
            ['group' => 'appearance', 'key' => 'primary_color',      'value' => '#0B3A68', 'type' => $s],
            ['group' => 'appearance', 'key' => 'accent_color',       'value' => '#F59E0B', 'type' => $s],
            ['group' => 'appearance', 'key' => 'custom_css',         'value' => '',        'type' => $s],
            ['group' => 'appearance', 'key' => 'custom_css_enabled', 'value' => '0',       'type' => $b],

            // ── TAX ──────────────────────────────────────────────────────────────
            ['group' => 'tax', 'key' => 'default_vat_rate',  'value' => '21',            'type' => $i],
            ['group' => 'tax', 'key' => 'company_vat_number','value' => '',               'type' => $s],
            ['group' => 'tax', 'key' => 'price_display',     'value' => 'inc_vat',        'type' => $s],
            ['group' => 'tax', 'key' => 'vat_rates', 'value' => json_encode([
                'AT' => 20, 'BE' => 21, 'BG' => 20, 'HR' => 25, 'CY' => 19,
                'CZ' => 21, 'DK' => 25, 'EE' => 22, 'FI' => 24, 'FR' => 20,
                'DE' => 19, 'GR' => 24, 'HU' => 27, 'IE' => 23, 'IT' => 22,
                'LV' => 21, 'LT' => 21, 'LU' => 17, 'MT' => 18, 'NL' => 21,
                'PL' => 23, 'PT' => 23, 'RO' => 19, 'SK' => 20, 'SI' => 22,
                'ES' => 21, 'SE' => 25,
            ]), 'type' => $j],
            ['group' => 'tax', 'key' => 'vat_validation_enabled', 'value' => '1',       'type' => $b],
            ['group' => 'tax', 'key' => 'b2b_exempt_on_valid_vat', 'value' => '1',      'type' => $b],

            // ── SHIPPING ─────────────────────────────────────────────────────────
            ['group' => 'shipping', 'key' => 'free_shipping_threshold', 'value' => '150.00',                   'type' => $s],
            ['group' => 'shipping', 'key' => 'nudge_enabled',           'value' => '1',                         'type' => $b],
            ['group' => 'shipping', 'key' => 'nudge_threshold',         'value' => '10.00',                     'type' => $s],
            ['group' => 'shipping', 'key' => 'nudge_text',              'value' => $ml('Add €{amount} more for free shipping!'), 'type' => $j],
            ['group' => 'shipping', 'key' => 'cutoff_time',             'value' => '15:00',                     'type' => $s],
            ['group' => 'shipping', 'key' => 'cutoff_timezone',         'value' => 'Europe/Vilnius',            'type' => $s],
            ['group' => 'shipping', 'key' => 'business_days',           'value' => json_encode([1,2,3,4,5]),    'type' => $j],
            ['group' => 'shipping', 'key' => 'default_origin_country',  'value' => 'LT',                        'type' => $s],
            ['group' => 'shipping', 'key' => 'handling_fee',            'value' => '0.00',                      'type' => $s],

            // ── ORDERS ───────────────────────────────────────────────────────────
            ['group' => 'orders', 'key' => 'bank_transfer_expiry_hours',    'value' => '48',  'type' => $i],
            ['group' => 'orders', 'key' => 'customer_cancel_window_hours',  'value' => '2',   'type' => $i],
            ['group' => 'orders', 'key' => 'refund_window_days',            'value' => '14',  'type' => $i],
            ['group' => 'orders', 'key' => 'urgent_processing_enabled',     'value' => '0',   'type' => $b],
            ['group' => 'orders', 'key' => 'urgent_processing_fee',         'value' => '5.00','type' => $s],
            ['group' => 'orders', 'key' => 'minimum_order_amount',          'value' => '0',   'type' => $s],
            ['group' => 'orders', 'key' => 'order_number_prefix',           'value' => 'ORD', 'type' => $s],
            ['group' => 'orders', 'key' => 'invoice_number_prefix',         'value' => 'INV', 'type' => $s],
            ['group' => 'orders', 'key' => 'rma_number_prefix',             'value' => 'RMA', 'type' => $s],
            ['group' => 'orders', 'key' => 'order_number_padding',          'value' => '6',   'type' => $i],
            ['group' => 'orders', 'key' => 'auto_complete_days',            'value' => '14',  'type' => $i],

            // ── PAYMENT ──────────────────────────────────────────────────────────
            ['group' => 'payment', 'key' => 'bank_name',             'value' => 'Demo Bank EU',           'type' => $s],
            ['group' => 'payment', 'key' => 'bank_iban',             'value' => 'DE89 3704 0044 0532 0130 00', 'type' => $s],
            ['group' => 'payment', 'key' => 'bank_bic',              'value' => 'COBADEFFXXX',            'type' => $s],
            ['group' => 'payment', 'key' => 'bank_account_holder',   'value' => 'OEMHub EU GmbH',         'type' => $s],
            ['group' => 'payment', 'key' => 'bank_reference_prefix', 'value' => 'OEM',     'type' => $s],
            ['group' => 'payment', 'key' => 'airwallex_environment', 'value' => 'sandbox', 'type' => $s],
            ['group' => 'payment', 'key' => 'airwallex_api_key',     'value' => '',        'type' => $e, 'encrypted' => true],
            ['group' => 'payment', 'key' => 'airwallex_client_id',   'value' => '',        'type' => $e, 'encrypted' => true],
            ['group' => 'payment', 'key' => 'airwallex_webhook_secret', 'value' => '',     'type' => $e, 'encrypted' => true],
            ['group' => 'payment', 'key' => 'card_enabled',          'value' => '1',       'type' => $b],
            ['group' => 'payment', 'key' => 'bank_transfer_enabled', 'value' => '1',       'type' => $b],

            // ── AUTH ─────────────────────────────────────────────────────────────
            ['group' => 'auth', 'key' => 'otp_length',                'value' => '6',   'type' => $i],
            ['group' => 'auth', 'key' => 'otp_expiry_minutes',        'value' => '10',  'type' => $i],
            ['group' => 'auth', 'key' => 'otp_max_attempts',          'value' => '3',   'type' => $i],
            ['group' => 'auth', 'key' => 'otp_resend_cooldown',       'value' => '60',  'type' => $i],
            ['group' => 'auth', 'key' => 'customer_session_lifetime', 'value' => '120', 'type' => $i],
            ['group' => 'auth', 'key' => 'customer_password_min',     'value' => '8',   'type' => $i],
            ['group' => 'auth', 'key' => 'admin_password_min',        'value' => '12',  'type' => $i],
            ['group' => 'auth', 'key' => 'guest_checkout_enabled',    'value' => '1',   'type' => $b],
            ['group' => 'auth', 'key' => 'registration_enabled',      'value' => '1',   'type' => $b],

            // ── EMAIL ────────────────────────────────────────────────────────────
            ['group' => 'email', 'key' => 'from_name',      'value' => 'OEMHub',          'type' => $s],
            ['group' => 'email', 'key' => 'from_address',   'value' => 'no-reply@oemhub.eu','type' => $s],
            ['group' => 'email', 'key' => 'reply_to',       'value' => 'info@oemhub.eu',  'type' => $s],
            ['group' => 'email', 'key' => 'smtp_host',      'value' => 'smtp.mailtrap.io','type' => $s],
            ['group' => 'email', 'key' => 'smtp_port',      'value' => '587',             'type' => $i],
            ['group' => 'email', 'key' => 'smtp_encryption','value' => 'tls',             'type' => $s],
            ['group' => 'email', 'key' => 'smtp_username',  'value' => '',                'type' => $e, 'encrypted' => true],
            ['group' => 'email', 'key' => 'smtp_password',  'value' => '',                'type' => $e, 'encrypted' => true],
            ['group' => 'email', 'key' => 'admin_notify_new_order',  'value' => '1',      'type' => $b],
            ['group' => 'email', 'key' => 'admin_notify_new_inquiry','value' => '1',      'type' => $b],
            ['group' => 'email', 'key' => 'admin_notify_email',      'value' => '',       'type' => $s],

            // ── SEARCH ───────────────────────────────────────────────────────────
            ['group' => 'search', 'key' => 'min_chars',            'value' => '3',  'type' => $i],
            ['group' => 'search', 'key' => 'autocomplete_count',   'value' => '5',  'type' => $i],
            ['group' => 'search', 'key' => 'rate_limit_per_minute','value' => '30', 'type' => $i],
            ['group' => 'search', 'key' => 'log_searches',         'value' => '1',  'type' => $b],
            ['group' => 'search', 'key' => 'log_retention_days',   'value' => '90', 'type' => $i],
            ['group' => 'search', 'key' => 'cross_ref_enabled',    'value' => '1',  'type' => $b],
            ['group' => 'search', 'key' => 'partial_match_enabled','value' => '1',  'type' => $b],
            ['group' => 'search', 'key' => 'partial_match_min_length', 'value' => '4', 'type' => $i],

            // ── CART ─────────────────────────────────────────────────────────────
            ['group' => 'cart', 'key' => 'expiry_days',              'value' => '7',    'type' => $i],
            ['group' => 'cart', 'key' => 'max_items',                'value' => '50',   'type' => $i],
            ['group' => 'cart', 'key' => 'price_change_threshold',   'value' => '20',   'type' => $i],
            ['group' => 'cart', 'key' => 'otp_required_guest',       'value' => '1',    'type' => $b],
            ['group' => 'cart', 'key' => 'checkout_timeout_minutes', 'value' => '30',   'type' => $i],
            ['group' => 'cart', 'key' => 'coupon_enabled',           'value' => '1',    'type' => $b],
            ['group' => 'cart', 'key' => 'merge_on_login',           'value' => '1',    'type' => $b],

            // ── PERFORMANCE ──────────────────────────────────────────────────────
            ['group' => 'performance', 'key' => 'cache_driver',            'value' => 'redis', 'type' => $s],
            ['group' => 'performance', 'key' => 'cache_sections',          'value' => '1',     'type' => $b],
            ['group' => 'performance', 'key' => 'cache_ttl_sections',      'value' => '60',    'type' => $i],
            ['group' => 'performance', 'key' => 'cache_settings',          'value' => '1',     'type' => $b],
            ['group' => 'performance', 'key' => 'cache_ttl_settings',      'value' => '5',     'type' => $i],
            ['group' => 'performance', 'key' => 'cache_manufacturers',     'value' => '1',     'type' => $b],
            ['group' => 'performance', 'key' => 'cache_ttl_manufacturers', 'value' => '60',    'type' => $i],
            ['group' => 'performance', 'key' => 'query_cache_enabled',     'value' => '1',     'type' => $b],
            ['group' => 'performance', 'key' => 'query_cache_ttl',         'value' => '10',    'type' => $i],

            // ── SECURITY ─────────────────────────────────────────────────────────
            ['group' => 'security', 'key' => 'login_max_attempts',    'value' => '5',  'type' => $i],
            ['group' => 'security', 'key' => 'login_window_minutes',  'value' => '15', 'type' => $i],
            ['group' => 'security', 'key' => 'inquiry_max_per_email', 'value' => '10', 'type' => $i],
            ['group' => 'security', 'key' => 'ip_blocklist_enabled',  'value' => '1',  'type' => $b],
            ['group' => 'security', 'key' => 'honeypot_enabled',      'value' => '1',  'type' => $b],
            ['group' => 'security', 'key' => 'csrf_enabled',          'value' => '1',  'type' => $b],
            ['group' => 'security', 'key' => 'force_https',           'value' => '0',  'type' => $b],
            ['group' => 'security', 'key' => 'admin_2fa_required',    'value' => '0',  'type' => $b],

            // ── INTEGRATIONS ─────────────────────────────────────────────────────
            ['group' => 'integrations', 'key' => 'gtm_id',           'value' => '', 'type' => $s],
            ['group' => 'integrations', 'key' => 'gsc_verification', 'value' => '', 'type' => $s],
            ['group' => 'integrations', 'key' => 'ga4_measurement_id','value' => '','type' => $s],
            ['group' => 'integrations', 'key' => 'fb_pixel_id',      'value' => '', 'type' => $s],
            ['group' => 'integrations', 'key' => 'crisp_website_id', 'value' => '', 'type' => $s],

            // ── STATS COUNTER ────────────────────────────────────────────────────
            ['group' => 'stats_counter', 'key' => 'customers_count', 'value' => '2500',   'type' => $i],
            ['group' => 'stats_counter', 'key' => 'parts_count',     'value' => '1000000', 'type' => $i],
            ['group' => 'stats_counter', 'key' => 'countries_count', 'value' => '27',     'type' => $i],
            ['group' => 'stats_counter', 'key' => 'rating',          'value' => '4.9',    'type' => $s],
            ['group' => 'stats_counter', 'key' => 'orders_count',    'value' => '120000', 'type' => $i],
            ['group' => 'stats_counter', 'key' => 'show_section',    'value' => '1',      'type' => $b],

            // ── SEO ──────────────────────────────────────────────────────────────
            // Homepage title: primary keyword first, brand last, ≤60 chars
            ['group' => 'seo', 'key' => 'home_title',
             'value' => 'Buy Genuine OEM Auto Parts Online | OEMHub',
             'type' => $s],

            // Homepage meta description: 145-155 chars, primary keyword + USPs + CTA
            ['group' => 'seo', 'key' => 'home_description',
             'value' => 'Search 500,000+ genuine OEM auto parts by part number. Compare verified prices from EU sellers. Fast delivery to all 27 EU countries. B2B invoicing available.',
             'type' => $s],

            // OEM part page title: keyword intent "buy {oem}" + price anchor + brand
            ['group' => 'seo', 'key' => 'oem_title_template',
             'value' => 'Buy OEM Part {oem} — From €{min} | Genuine {manufacturer} | OEMHub',
             'type' => $s],

            // OEM part page description: specific, answers search intent, ≤155 chars
            ['group' => 'seo', 'key' => 'oem_description_template',
             'value' => 'Genuine {manufacturer} OEM part {oem}. Verified EU suppliers. Prices from €{min}. Insured delivery in 1–5 days to all 27 EU countries. VAT invoice included.',
             'type' => $s],

            // OEM search results list (/{lang}/parts/{oem}) — empty = use lang files
            ['group' => 'seo', 'key' => 'search_results_title_template',
             'value' => '',
             'type' => $s],
            ['group' => 'seo', 'key' => 'search_results_meta_template',
             'value' => '',
             'type' => $s],

            // Brand page title: brand + OEM keyword + platform
            ['group' => 'seo', 'key' => 'brand_title_template',
             'value' => 'Genuine {brand} OEM Parts — Buy Online | OEMHub',
             'type' => $s],

            ['group' => 'seo', 'key' => 'sitemap_search_log_days',  'value' => '90',           'type' => $i],
            ['group' => 'seo', 'key' => 'google_ping_enabled',      'value' => '1',            'type' => $b],
            ['group' => 'seo', 'key' => 'default_robots',           'value' => 'index,follow', 'type' => $s],
            ['group' => 'seo', 'key' => 'maintenance_noindex',      'value' => '1',            'type' => $b],
            ['group' => 'seo', 'key' => 'default_og_image',         'value' => '',             'type' => $s],
            ['group' => 'seo', 'key' => 'twitter_handle',           'value' => '',             'type' => $s],

            // Announcement bar — high-intent visitors get a conversion nudge
            // (set enabled=1 in admin when ready to activate)
            ['group' => 'announcement', 'key' => 'cta_text',
             'value' => $ml('Free tracked EU shipping on orders over €150. 500,000+ genuine OEM parts in stock.'),
             'type' => $j],

            // ── MAINTENANCE ──────────────────────────────────────────────────────
            ['group' => 'maintenance', 'key' => 'enabled',             'value' => '0',                        'type' => $b],
            ['group' => 'maintenance', 'key' => 'message',             'value' => $ml("We'll be back soon."), 'type' => $j],
            ['group' => 'maintenance', 'key' => 'allowed_ips',         'value' => '',                         'type' => $s],
            ['group' => 'maintenance', 'key' => 'estimated_back_at',   'value' => '',                         'type' => $s],
            ['group' => 'maintenance', 'key' => 'show_estimated_time', 'value' => '0',                        'type' => $b],
            ['group' => 'maintenance', 'key' => 'contact_email',       'value' => '',                         'type' => $s],
        ];
    }
}
