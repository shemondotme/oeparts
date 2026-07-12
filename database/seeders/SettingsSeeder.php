<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = self::definitions();

        foreach ($settings as $row) {
            Setting::updateOrCreate(
                ['group' => $row['group'], 'key' => $row['key']],
                [
                    'value' => $row['value'],
                    'type' => $row['type'],
                    'is_encrypted' => $row['encrypted'] ?? false,
                ]
            );
        }
    }

    public static function definitions(): array
    {
        $s = SettingType::String->value;
        $b = SettingType::Boolean->value;
        $i = SettingType::Integer->value;
        $j = SettingType::Json->value;
        $e = SettingType::Encrypted->value;

        $langs = ['en', 'de', 'lt', 'fr', 'es'];

        // Helper: build a multilang JSON string with the same value for all locales
        $ml = fn (string $text) => json_encode(array_fill_keys($langs, $text));

        return [
            // ── GENERAL ──────────────────────────────────────────────────────────
            ['group' => 'general', 'key' => 'site_name',       'value' => 'OeParts',                  'type' => $s],
            ['group' => 'general', 'key' => 'site_url',        'value' => 'http://localhost',         'type' => $s],
            ['group' => 'general', 'key' => 'site_email',      'value' => 'info@oeparts.lt',           'type' => $s],
            ['group' => 'general', 'key' => 'site_phone',      'value' => '+370 600 00000',           'type' => $s],
            ['group' => 'general', 'key' => 'logo_id',         'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'favicon_id',      'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'header_scripts',  'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'footer_scripts',  'value' => '',                         'type' => $s],
            ['group' => 'general', 'key' => 'site_tagline',    'value' => 'The central hub for genuine OEM auto parts in Europe.', 'type' => $s],
            ['group' => 'general', 'key' => 'default_locale',  'value' => 'en',                      'type' => $s],
            ['group' => 'general', 'key' => 'timezone',        'value' => 'Europe/Vilnius',           'type' => $s],
            ['group' => 'general', 'key' => 'date_format',     'value' => 'd/m/Y',                   'type' => $s],
            ['group' => 'general', 'key' => 'currency',        'value' => 'EUR',                      'type' => $s],
            ['group' => 'general', 'key' => 'currency_symbol', 'value' => '€',                        'type' => $s],

            // ── CONTACT ──────────────────────────────────────────────────────────
            ['group' => 'contact', 'key' => 'phone',        'value' => '+370 600 00000',    'type' => $s],
            ['group' => 'contact', 'key' => 'email',        'value' => 'info@oeparts.lt',    'type' => $s],
            ['group' => 'contact', 'key' => 'address',      'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'whatsapp',     'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'viber',        'value' => '',                  'type' => $s],
            ['group' => 'contact', 'key' => 'hours', 'value' => $ml('Mon–Fri 9:00–18:00'), 'type' => $j],

            // ── ANNOUNCEMENT ─────────────────────────────────────────────────────
            ['group' => 'announcement', 'key' => 'enabled',    'value' => '0',          'type' => $b],
            ['group' => 'announcement', 'key' => 'text',       'value' => $ml(''),       'type' => $j],
            ['group' => 'announcement', 'key' => 'color',      'value' => '#F59E0B',    'type' => $s],
            ['group' => 'announcement', 'key' => 'text_color', 'value' => '#1E293B',    'type' => $s],
            ['group' => 'announcement', 'key' => 'dismissable', 'value' => '1',          'type' => $b],
            ['group' => 'announcement', 'key' => 'url',        'value' => '',            'type' => $s],

            // ── APPEARANCE ───────────────────────────────────────────────────────
            ['group' => 'appearance', 'key' => 'primary_color',      'value' => '#0B3A68', 'type' => $s],
            ['group' => 'appearance', 'key' => 'accent_color',       'value' => '#F59E0B', 'type' => $s],
            ['group' => 'appearance', 'key' => 'custom_css',         'value' => '',        'type' => $s],
            ['group' => 'appearance', 'key' => 'custom_css_enabled', 'value' => '0',       'type' => $b],

            // ── TAX ──────────────────────────────────────────────────────────────
            ['group' => 'tax', 'key' => 'default_vat_rate',  'value' => '21',            'type' => $i],
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
            ['group' => 'shipping', 'key' => 'business_days',           'value' => json_encode([1, 2, 3, 4, 5]),    'type' => $j],
            ['group' => 'shipping', 'key' => 'default_origin_country',  'value' => 'LT',                        'type' => $s],
            ['group' => 'shipping', 'key' => 'handling_fee',            'value' => '0.00',                      'type' => $s],

            // ── ORDERS ───────────────────────────────────────────────────────────
            ['group' => 'orders', 'key' => 'bank_transfer_expiry_hours',    'value' => '48',  'type' => $i],
            ['group' => 'orders', 'key' => 'customer_cancel_window_hours',  'value' => '2',   'type' => $i],
            ['group' => 'orders', 'key' => 'refund_window_days',            'value' => '14',  'type' => $i],
            ['group' => 'orders', 'key' => 'urgent_processing_enabled',     'value' => '0',   'type' => $b],
            ['group' => 'orders', 'key' => 'urgent_processing_fee',         'value' => '5.00', 'type' => $s],
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
            ['group' => 'payment', 'key' => 'bank_account_holder',   'value' => 'OeParts UAB',         'type' => $s],
            ['group' => 'payment', 'key' => 'bank_reference_prefix', 'value' => 'OEM',     'type' => $s],
            ['group' => 'payment', 'key' => 'airwallex_environment', 'value' => 'sandbox', 'type' => $s],
            ['group' => 'payment', 'key' => 'airwallex_api_key',     'value' => '',        'type' => $e, 'encrypted' => true],
            ['group' => 'payment', 'key' => 'airwallex_client_id',   'value' => '',        'type' => $e, 'encrypted' => true],
            ['group' => 'payment', 'key' => 'airwallex_webhook_secret', 'value' => '',     'type' => $e, 'encrypted' => true],

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
            ['group' => 'email', 'key' => 'from_name',      'value' => 'OeParts',          'type' => $s],
            ['group' => 'email', 'key' => 'from_address',   'value' => 'no-reply@oeparts.lt', 'type' => $s],
            ['group' => 'email', 'key' => 'reply_to',       'value' => 'info@oeparts.lt',  'type' => $s],
            ['group' => 'email', 'key' => 'smtp_host',      'value' => 'smtp.mailtrap.io', 'type' => $s],
            ['group' => 'email', 'key' => 'smtp_port',      'value' => '587',             'type' => $i],
            ['group' => 'email', 'key' => 'smtp_encryption', 'value' => 'tls',             'type' => $s],
            ['group' => 'email', 'key' => 'smtp_username',  'value' => '',                'type' => $e, 'encrypted' => true],
            ['group' => 'email', 'key' => 'smtp_password',  'value' => '',                'type' => $e, 'encrypted' => true],
            ['group' => 'email', 'key' => 'admin_notify_new_order',  'value' => '1',      'type' => $b],
            ['group' => 'email', 'key' => 'admin_notify_new_inquiry', 'value' => '1',      'type' => $b],
            ['group' => 'email', 'key' => 'admin_notify_email',      'value' => '',       'type' => $s],

            // ── SEARCH ───────────────────────────────────────────────────────────
            ['group' => 'search', 'key' => 'min_chars',            'value' => '3',  'type' => $i],
            ['group' => 'search', 'key' => 'autocomplete_count',   'value' => '5',  'type' => $i],
            ['group' => 'search', 'key' => 'rate_limit_per_minute', 'value' => '30', 'type' => $i],
            ['group' => 'search', 'key' => 'log_searches',         'value' => '1',  'type' => $b],
            ['group' => 'search', 'key' => 'log_retention_days',   'value' => '90', 'type' => $i],
            ['group' => 'search', 'key' => 'cross_ref_enabled',    'value' => '1',  'type' => $b],
            ['group' => 'search', 'key' => 'partial_match_enabled', 'value' => '1',  'type' => $b],
            ['group' => 'search', 'key' => 'partial_match_min_length', 'value' => '4', 'type' => $i],

            // ── CART ─────────────────────────────────────────────────────────────
            ['group' => 'cart', 'key' => 'expiry_days',              'value' => '7',    'type' => $i],
            ['group' => 'cart', 'key' => 'max_items',                'value' => '50',   'type' => $i],
            ['group' => 'cart', 'key' => 'price_change_threshold',   'value' => '20',   'type' => $i],
            ['group' => 'cart', 'key' => 'otp_required_guest',       'value' => '1',    'type' => $b],
            ['group' => 'cart', 'key' => 'coupon_enabled',           'value' => '1',    'type' => $b],
            ['group' => 'cart', 'key' => 'merge_on_login',           'value' => '1',    'type' => $b],

            // ── PERFORMANCE ──────────────────────────────────────────────────────
            ['group' => 'performance', 'key' => 'cache_sections',          'value' => '1',     'type' => $b],
            ['group' => 'performance', 'key' => 'cache_ttl_sections',      'value' => '60',    'type' => $i],
            ['group' => 'performance', 'key' => 'cache_manufacturers',     'value' => '1',     'type' => $b],
            ['group' => 'performance', 'key' => 'cache_ttl_manufacturers', 'value' => '60',    'type' => $i],

            // ── SECURITY ─────────────────────────────────────────────────────────
            ['group' => 'security', 'key' => 'login_max_attempts',    'value' => '5',  'type' => $i],
            ['group' => 'security', 'key' => 'login_window_minutes',  'value' => '15', 'type' => $i],
            ['group' => 'security', 'key' => 'inquiry_max_per_email', 'value' => '10', 'type' => $i],
            ['group' => 'security', 'key' => 'session_lifetime',      'value' => '120', 'type' => $i],

            // ── INTEGRATIONS ─────────────────────────────────────────────────────
            ['group' => 'integrations', 'key' => 'gtm_id',           'value' => '', 'type' => $s],
            ['group' => 'integrations', 'key' => 'ga4_measurement_id', 'value' => '', 'type' => $s],
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
                'value' => 'Buy Genuine OEM Auto Parts Online | OeParts',
                'type' => $s],

            // Homepage meta description: 145-155 chars, primary keyword + USPs + CTA
            ['group' => 'seo', 'key' => 'home_description',
                'value' => 'Search 500,000+ genuine OEM auto parts by part number. Compare verified prices from EU sellers. Fast delivery to all 27 EU countries. B2B invoicing available.',
                'type' => $s],

            // OEM search results list (/{lang}/parts/{oem}) — keyword intent "buy {oem}"
            // + price anchor + brand; ≤60/155 chars. This page IS the OEM part page.
            ['group' => 'seo', 'key' => 'search_results_title_template',
                'value' => 'Buy OEM Part {oem} — From €{min} | OeParts',
                'type' => $s],
            ['group' => 'seo', 'key' => 'search_results_meta_template',
                'value' => 'Genuine OEM part {oem}. Verified EU suppliers. Prices from €{min}. Insured delivery in 1–5 days to all 27 EU countries. VAT invoice included.',
                'type' => $s],

            // Brand page title: brand + OEM keyword + platform
            ['group' => 'seo', 'key' => 'brand_title_template',
                'value' => 'Genuine {brand} OEM Parts — Buy Online | OeParts',
                'type' => $s],

            ['group' => 'seo', 'key' => 'google_ping_enabled',      'value' => '1',            'type' => $b],
            ['group' => 'seo', 'key' => 'default_robots',           'value' => 'index,follow', 'type' => $s],
            ['group' => 'seo', 'key' => 'default_og_image',         'value' => '',             'type' => $s],
            ['group' => 'seo', 'key' => 'twitter_handle',           'value' => '',             'type' => $s],

            // Announcement bar — high-intent visitors get a conversion nudge
            // (set enabled=1 in admin when ready to activate)
            ['group' => 'announcement', 'key' => 'cta_text',
                'value' => $ml('Free tracked EU shipping on orders over €150. 500,000+ genuine OEM parts in stock.'),
                'type' => $j],

            // ── PRELOADER (full-page splash) ───────────────────────────────────
            // enabled default off; path_mode include + locale slugs = homepage only; use * for all routes in a language (e.g. en*).
            ['group' => 'preloader', 'key' => 'enabled',         'value' => '0',   'type' => $b],
            ['group' => 'preloader', 'key' => 'path_mode',       'value' => 'include', 'type' => $s],
            ['group' => 'preloader', 'key' => 'path_patterns',   'value' => json_encode(['en', 'de', 'lt', 'fr', 'es']), 'type' => $j],
            ['group' => 'preloader', 'key' => 'min_display_ms',  'value' => '450',  'type' => $i],
            ['group' => 'preloader', 'key' => 'max_display_ms',  'value' => '6000', 'type' => $i],
            ['group' => 'preloader', 'key' => 'headline',        'value' => $ml('Oe·Parts.'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'spec_line',       'value' => $ml('§ SYS · INIT / EU'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'subline',         'value' => $ml('Genuine Parts Index'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'status_line',     'value' => $ml('Calibrating Index'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'foot_left',       'value' => $ml('OeParts · EU'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'foot_right',      'value' => $ml('LIVE CATALOGUE'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'aria_label',      'value' => $ml('Loading'), 'type' => $j],

            // ── UI (headlines / chrome editable without code — Admin → ui) ───────
            ['group' => 'ui', 'key' => 'hero_index_badge',       'value' => $ml('§ INDEX'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_live_status',     'value' => $ml('CATALOGUE LIVE'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_eyebrow',           'value' => $ml('Genuine OEM Parts Index · 1,000,000+'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_subtext_default',  'value' => $ml('Enter any OEM number. We return matches, cross-references, and verified suppliers across the European Union — or open a concierge inquiry if the part is rare.'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_title',       'value' => $ml('Specification'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_source_label',     'value' => $ml('Source'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_source_badge',     'value' => $ml('VERIFIED · EU'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_search_strip',      'value' => $ml('§ ENTER OEM NUMBER'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_search_meta_hint',  'value' => $ml('min :min chars · uppercase alphanumeric'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_indexed_label',     'value' => $ml('Indexed:'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_1',     'value' => $ml('Verified Suppliers'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_2',     'value' => $ml('TLS 1.3 · SSL'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_3',     'value' => $ml('27 EU Countries'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r1_label',     'value' => $ml('Catalogue'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r2_label',     'value' => $ml('Manufacturers'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r2_value',     'value' => $ml('214'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r3_label',     'value' => $ml('Cross-refs'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r3_value',     'value' => $ml('3.2M'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r4_label',     'value' => $ml('Avg. despatch'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r4_value',     'value' => $ml('24h'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r5_label',     'value' => $ml('Languages'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r5_value',     'value' => $ml('EN·DE·LT·FR·ES'), 'type' => $j],

            // ── MAINTENANCE ──────────────────────────────────────────────────────
            ['group' => 'maintenance', 'key' => 'enabled',             'value' => '0',                        'type' => $b],
            ['group' => 'maintenance', 'key' => 'message',             'value' => $ml("We'll be back soon."), 'type' => $j],
            ['group' => 'maintenance', 'key' => 'allowed_ips',         'value' => '',                         'type' => $s],
            ['group' => 'maintenance', 'key' => 'estimated_back_at',   'value' => '',                         'type' => $s],
            ['group' => 'maintenance', 'key' => 'show_estimated_time', 'value' => '0',                        'type' => $b],
            ['group' => 'maintenance', 'key' => 'contact_email',       'value' => '',                         'type' => $s],
            ['group' => 'maintenance', 'key' => 'retry_after',         'value' => '3600',                     'type' => $i],

            // ── STORE ─────────────────────────────────────────────────────────────
            ['group' => 'store', 'key' => 'currency',           'value' => 'EUR', 'type' => $s],
            ['group' => 'store', 'key' => 'currency_symbol',    'value' => '€',   'type' => $s],
            ['group' => 'store', 'key' => 'currency_position',  'value' => 'left', 'type' => $s],
            ['group' => 'store', 'key' => 'decimal_separator',  'value' => '.',    'type' => $s],
            ['group' => 'store', 'key' => 'thousand_separator', 'value' => ',',    'type' => $s],

            // ── CHECKOUT ──────────────────────────────────────────────────────────
            ['group' => 'checkout', 'key' => 'default_payment_method',    'value' => 'bank_transfer', 'type' => $s],
            ['group' => 'checkout', 'key' => 'timeout_minutes',           'value' => '30',            'type' => $i],
            ['group' => 'checkout', 'key' => 'max_steps',                 'value' => '5',             'type' => $i],
            ['group' => 'checkout', 'key' => 'payment_success_message',   'value' => $ml('Payment received. Thank you!'), 'type' => $j],
            ['group' => 'checkout', 'key' => 'payment_error_message',     'value' => $ml('Payment failed. Please try again.'), 'type' => $j],
            ['group' => 'checkout', 'key' => 'max_note_length',           'value' => '500',           'type' => $i],

            // ── SECTIONS ──────────────────────────────────────────────────────────
            ['group' => 'sections', 'key' => 'testimonials_limit',   'value' => '6',  'type' => $i],
            ['group' => 'sections', 'key' => 'faq_limit',            'value' => '20', 'type' => $i],
            ['group' => 'sections', 'key' => 'blog_limit',           'value' => '9',  'type' => $i],
            ['group' => 'sections', 'key' => 'manufacturers_limit',  'value' => '12', 'type' => $i],

            // ── NEWSLETTER ────────────────────────────────────────────────────────
            ['group' => 'newsletter', 'key' => 'rate_limit_per_hour',  'value' => '10',   'type' => $i],
            ['group' => 'newsletter', 'key' => 'rate_window_seconds',  'value' => '3600', 'type' => $i],
            ['group' => 'newsletter', 'key' => 'double_opt_in',        'value' => '1',    'type' => $b],

            // ── PART INQUIRY ──────────────────────────────────────────────────────
            ['group' => 'part_inquiry', 'key' => 'response_hours',          'value' => '24', 'type' => $i],
            ['group' => 'part_inquiry', 'key' => 'guest_inquiries_allowed', 'value' => '1',  'type' => $b],
            ['group' => 'part_inquiry', 'key' => 'rate_limit_per_hour',     'value' => '5',  'type' => $i],

            // ── COMPANY ───────────────────────────────────────────────────────────
            ['group' => 'company', 'key' => 'name',                 'value' => 'OeParts', 'type' => $s],
            ['group' => 'company', 'key' => 'vat_number',           'value' => '',         'type' => $s],
            ['group' => 'company', 'key' => 'registration_number',  'value' => '',         'type' => $s],
            ['group' => 'company', 'key' => 'email',                'value' => '',         'type' => $s],
            ['group' => 'company', 'key' => 'phone',                'value' => '',         'type' => $s],
            ['group' => 'company', 'key' => 'address',              'value' => '',         'type' => $s],

            // ── MENU ──────────────────────────────────────────────────────────────
            ['group' => 'menu', 'key' => 'footer_show_about',    'value' => '1', 'type' => $b],
            ['group' => 'menu', 'key' => 'footer_show_contact',  'value' => '1', 'type' => $b],
            ['group' => 'menu', 'key' => 'footer_show_faq',      'value' => '1', 'type' => $b],
            ['group' => 'menu', 'key' => 'footer_show_blog',     'value' => '1', 'type' => $b],

            // ── SOCIAL LINKS ──────────────────────────────────────────────────────
            ['group' => 'social_links', 'key' => 'facebook_url',      'value' => '',       'type' => $s],
            ['group' => 'social_links', 'key' => 'instagram_url',     'value' => '',       'type' => $s],
            ['group' => 'social_links', 'key' => 'twitter_url',       'value' => '',       'type' => $s],
            ['group' => 'social_links', 'key' => 'linkedin_url',      'value' => '',       'type' => $s],
            ['group' => 'social_links', 'key' => 'youtube_url',       'value' => '',       'type' => $s],
            ['group' => 'social_links', 'key' => 'tiktok_url',        'value' => '',       'type' => $s],
            ['group' => 'social_links', 'key' => 'show_in_footer',    'value' => '1',      'type' => $b],
            ['group' => 'social_links', 'key' => 'footer_icon_style', 'value' => 'outlined', 'type' => $s],

            // ── Missing individual keys in existing groups ────────────────────────
            ['group' => 'search', 'key' => 'log_failed',        'value' => '1',  'type' => $b],
            ['group' => 'seo', 'key' => 'google_verification',  'value' => '',   'type' => $s],
            ['group' => 'seo', 'key' => 'bing_verification',    'value' => '',   'type' => $s],

            // ── Phase 8 Option SS: settings() calls with no seed row + no UI ──────
            ['group' => 'cart', 'key' => 'rate_limit_per_minute', 'value' => '60',  'type' => $i],
            ['group' => 'cart', 'key' => 'max_quantity',          'value' => '999', 'type' => $i],
            ['group' => 'cart', 'key' => 'guest_cookie_days',     'value' => '7',   'type' => $i],
            ['group' => 'search', 'key' => 'supported_languages', 'value' => json_encode(['en', 'de', 'lt', 'fr', 'es']), 'type' => $j],
            ['group' => 'search', 'key' => 'results_limit',        'value' => '50', 'type' => $i],
            ['group' => 'search', 'key' => 'per_page',              'value' => '20', 'type' => $i],
            ['group' => 'search', 'key' => 'popular_days_window',  'value' => '30', 'type' => $i],
            ['group' => 'search', 'key' => 'popular_limit',        'value' => '8',  'type' => $i],
            ['group' => 'search', 'key' => 'cache_ttl_hours',      'value' => '6',  'type' => $i],
            ['group' => 'checkout', 'key' => 'allowed_payment_methods', 'value' => json_encode(['card', 'bank_transfer']), 'type' => $j],
            ['group' => 'checkout', 'key' => 'proof_max_size_kb',      'value' => '5120', 'type' => $i],
            ['group' => 'checkout', 'key' => 'guest_password_length',  'value' => '12',   'type' => $i],
            ['group' => 'checkout', 'key' => 'urgent_processing_enabled',    'value' => '0',    'type' => $b],
            ['group' => 'checkout', 'key' => 'urgent_processing_fee',        'value' => '9.99', 'type' => $s],
            ['group' => 'checkout', 'key' => 'urgent_processing_label',       'value' => $ml('Rush processing'), 'type' => $j],
            ['group' => 'checkout', 'key' => 'urgent_processing_description', 'value' => $ml('Priority same-day dispatch for orders placed before 2pm on a business day.'), 'type' => $j],
            ['group' => 'contact', 'key' => 'success_message', 'value' => 'Your message has been sent successfully. We will get back to you soon.', 'type' => $s],

            // ── DASHBOARD (widget thresholds — defaults mirrored in code) ─────────
            ['group' => 'dashboard', 'key' => 'cart_abandoned_hours',     'value' => '2',  'type' => $i],
            ['group' => 'dashboard', 'key' => 'orders_threshold',         'value' => '50', 'type' => $i],
            ['group' => 'dashboard', 'key' => 'pending_delayed_minutes',  'value' => '120', 'type' => $i],
        ];
    }
}
