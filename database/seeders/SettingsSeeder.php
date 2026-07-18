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
            ['group' => 'contact', 'key' => 'hours', 'value' => json_encode([
                'en' => 'Mon–Fri 9:00–18:00',
                'de' => 'Mo–Fr 9:00–18:00 Uhr',
                'lt' => 'I–V 9:00–18:00',
                'fr' => 'Lun–Ven 9h00–18h00',
                'es' => 'Lun–Vie 9:00–18:00',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'contact', 'key' => 'success_message', 'value' => json_encode([
                'en' => 'Your message has been sent successfully. We will get back to you soon.',
                'de' => 'Ihre Nachricht wurde erfolgreich gesendet. Wir melden uns in Kürze bei Ihnen.',
                'lt' => 'Jūsų žinutė sėkmingai išsiųsta. Netrukus su jumis susisieksime.',
                'fr' => 'Votre message a été envoyé avec succès. Nous reviendrons vers vous sous peu.',
                'es' => 'Su mensaje se ha enviado correctamente. Nos pondremos en contacto con usted en breve.',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],

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
            ['group' => 'tax', 'key' => 'vat_validation_enabled', 'value' => '1',       'type' => $b],

            // ── SHIPPING ─────────────────────────────────────────────────────────
            ['group' => 'shipping', 'key' => 'nudge_enabled',           'value' => '1',                         'type' => $b],
            ['group' => 'shipping', 'key' => 'nudge_threshold',         'value' => '10.00',                     'type' => $s],
            ['group' => 'shipping', 'key' => 'nudge_text', 'value' => json_encode([
                'en' => 'Add €{amount} more for free shipping!',
                'de' => 'Fügen Sie noch €{amount} hinzu für kostenlosen Versand!',
                'lt' => 'Pridėkite dar €{amount} nemokamam pristatymui!',
                'fr' => 'Ajoutez €{amount} de plus pour la livraison gratuite !',
                'es' => '¡Añade €{amount} más para envío gratis!',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            // step3.blade.php read this key with a hardcoded English default and no
            // admin field ever existed for it — could never actually be changed.
            ['group' => 'shipping', 'key' => 'note_text', 'value' => json_encode([
                'en' => 'All shipments tracked and insured. Delivery times are estimates from dispatch.',
                'de' => 'Alle Sendungen werden verfolgt und sind versichert. Lieferzeiten sind Schätzungen ab Versand.',
                'lt' => 'Visos siuntos sekamos ir apdraustos. Pristatymo laikas skaičiuojamas nuo išsiuntimo.',
                'fr' => 'Tous les envois sont suivis et assurés. Les délais de livraison sont estimés à partir de l\'expédition.',
                'es' => 'Todos los envíos son rastreados y están asegurados. Los plazos de entrega son estimaciones desde el envío.',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
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
            // Per-locale (type=Json) — NOT $ml()'d, these carry real translations
            // per language, not the same English string duplicated 5x. {oem}/{min}/
            // {max}/{site}/{brand} placeholder tokens stay literal in every locale.
            // Homepage title: primary keyword first, brand last, ≤60 chars
            ['group' => 'seo', 'key' => 'home_title',
                'value' => json_encode([
                    'en' => 'Genuine OEM Auto Parts by Part Number | OeParts',
                    'de' => 'Original OEM-Autoteile nach Teilenummer | OeParts',
                    'lt' => 'Originalios OEM automobilio dalys pagal numerį | OeParts',
                    'fr' => 'Pièces auto OEM d\'origine par numéro | OeParts',
                    'es' => 'Piezas de auto OEM originales por número | OeParts',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $j],

            // Homepage meta description: 130-155 chars, primary keyword + USPs + CTA
            ['group' => 'seo', 'key' => 'home_description',
                'value' => json_encode([
                    'en' => 'Find genuine OEM auto parts fast — search by part number, get guaranteed fitment, and ship to all 27 EU countries. Trade accounts available for workshops.',
                    'de' => 'OEM-Autoteile schnell finden — nach Teilenummer suchen, garantierte Passform, Versand in alle 27 EU-Länder. Geschäftskonten für Werkstätten verfügbar.',
                    'lt' => 'Originalios OEM dalys greitai — ieškokite pagal numerį, garantuotas tikslumas, pristatymas į 27 ES šalis. Verslo paskyros dirbtuvėms.',
                    'fr' => 'Pièces auto OEM d\'origine rapidement — recherchez par numéro, ajustement garanti, livraison dans les 27 pays UE. Comptes pro pour ateliers.',
                    'es' => 'Piezas de auto OEM originales rápido — busque por número, ajuste garantizado, envío a los 27 países UE. Cuentas comerciales para talleres.',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $j],

            // OEM search results list (/{lang}/parts/{oem}) — keyword intent "buy {oem}"
            // + price anchor + brand; ≤100/200 chars. This page IS the OEM part page.
            ['group' => 'seo', 'key' => 'search_results_title_template',
                'value' => json_encode([
                    'en' => 'Buy OEM Part {oem} — From €{min} | OeParts',
                    'de' => 'OEM-Teil {oem} kaufen — Ab €{min} | OeParts',
                    'lt' => 'Pirkti OEM dalį {oem} — Nuo €{min} | OeParts',
                    'fr' => 'Acheter la pièce OEM {oem} — Dès €{min} | OeParts',
                    'es' => 'Comprar pieza OEM {oem} — Desde €{min} | OeParts',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $j],
            ['group' => 'seo', 'key' => 'search_results_meta_template',
                'value' => json_encode([
                    'en' => 'Genuine OEM part {oem}. Verified EU suppliers. Prices from €{min}. Insured EU-wide delivery. VAT invoice included.',
                    'de' => 'Original OEM-Teil {oem}. Geprüfte EU-Lieferanten. Preise ab €{min}. Versicherte EU-weite Lieferung. Mit USt-Rechnung.',
                    'lt' => 'Originali OEM dalis {oem}. Patikrinti ES tiekėjai. Kainos nuo €{min}. Draustas pristatymas visoje ES. Su PVM sąskaita.',
                    'fr' => 'Pièce OEM d\'origine {oem}. Fournisseurs UE vérifiés. Prix dès €{min}. Livraison assurée dans toute l\'UE. Facture TVA incluse.',
                    'es' => 'Pieza OEM original {oem}. Proveedores UE verificados. Precios desde €{min}. Envío asegurado en toda la UE. Factura IVA incluida.',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $j],

            // Brand page title: brand + OEM keyword + platform (currently unwired —
            // no controller reads this yet; kept multilingual-correct for whenever
            // manufacturer-page SEO titles are built)
            ['group' => 'seo', 'key' => 'brand_title_template',
                'value' => json_encode([
                    'en' => 'Genuine {brand} OEM Parts — Buy Online | OeParts',
                    'de' => 'Original {brand} OEM-Teile — Online kaufen | OeParts',
                    'lt' => 'Originalios {brand} OEM dalys — Pirkti internetu | OeParts',
                    'fr' => 'Pièces OEM {brand} d\'origine — Achat en ligne | OeParts',
                    'es' => 'Piezas OEM {brand} originales — Comprar online | OeParts',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $j],

            // Parts Search Console (/{lang}/parts) title/description — this page IS
            // indexed (unlike zero-results, which is noindex), but previously had no
            // admin override at all, unlike the results-page templates above.
            ['group' => 'seo', 'key' => 'console_title_template',
                'value' => json_encode([
                    'en' => 'Parts Search Console | {site}',
                    'de' => 'Teile-Suchkonsole | {site}',
                    'lt' => 'Dalių paieškos konsolė | {site}',
                    'fr' => 'Console de recherche de pièces | {site}',
                    'es' => 'Consola de búsqueda de piezas | {site}',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $j],
            ['group' => 'seo', 'key' => 'console_meta_template',
                'value' => json_encode([
                    'en' => 'Search genuine OEM car parts by part number — fast cross-reference across verified European manufacturers.',
                    'de' => 'Original-OEM-Autoteile per Teilenummer suchen — schnelle Kreuzreferenz bei geprüften europäischen Herstellern.',
                    'lt' => 'Ieškokite originalių OEM dalių pagal numerį — greita kryžminė paieška tarp patvirtintų Europos gamintojų.',
                    'fr' => 'Recherchez des pièces OEM d\'origine par numéro — recoupement rapide auprès de fabricants européens vérifiés.',
                    'es' => 'Busque piezas OEM originales por número — referencia cruzada rápida entre fabricantes europeos verificados.',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $j],

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
            // hero_index_badge/hero_live_status are unused (no blade reference found)
            // — left $ml()'d. Everything else below carries real per-locale copy,
            // not an English string duplicated 5x (that was the bug fixed 2026-07-16).
            ['group' => 'ui', 'key' => 'hero_index_badge',       'value' => $ml('§ INDEX'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_live_status',     'value' => $ml('CATALOGUE LIVE'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_eyebrow', 'value' => json_encode([
                'en' => 'Genuine OEM Parts Index', 'de' => 'Original OEM-Teile-Index',
                'lt' => 'Originalių OEM dalių indeksas', 'fr' => 'Index de pièces OEM d\'origine',
                'es' => 'Índice de piezas OEM originales',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_subtext_default', 'value' => json_encode([
                'en' => 'Enter any OEM number. We return matches, cross-references, and verified suppliers across the European Union — or open a concierge inquiry if the part is rare.',
                'de' => 'Geben Sie eine beliebige OEM-Nummer ein. Wir liefern Treffer, Querverweise und geprüfte Lieferanten aus der gesamten Europäischen Union — oder starten Sie eine persönliche Anfrage, wenn das Teil selten ist.',
                'lt' => 'Įveskite bet kokį OEM numerį. Rasite atitikmenis, kryžmines nuorodas ir patikrintus tiekėjus visoje Europos Sąjungoje — arba pateikite asmeninę užklausą, jei dalis reta.',
                'fr' => 'Entrez n\'importe quel numéro OEM. Nous affichons les correspondances, références croisées et fournisseurs vérifiés dans toute l\'Union européenne — ou ouvrez une demande personnalisée si la pièce est rare.',
                'es' => 'Introduzca cualquier número OEM. Mostramos coincidencias, referencias cruzadas y proveedores verificados en toda la Unión Europea — o abra una consulta personalizada si la pieza es poco común.',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_title', 'value' => json_encode([
                'en' => 'Specification', 'de' => 'Spezifikation', 'lt' => 'Specifikacija',
                'fr' => 'Spécification', 'es' => 'Especificación',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_source_label', 'value' => json_encode([
                'en' => 'Source', 'de' => 'Quelle', 'lt' => 'Šaltinis', 'fr' => 'Source', 'es' => 'Fuente',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_source_badge', 'value' => json_encode([
                'en' => 'VERIFIED · EU', 'de' => 'GEPRÜFT · EU', 'lt' => 'PATIKRINTA · ES',
                'fr' => 'VÉRIFIÉ · UE', 'es' => 'VERIFICADO · UE',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_search_strip', 'value' => json_encode([
                'en' => '§ ENTER OEM NUMBER', 'de' => '§ OEM-NUMMER EINGEBEN', 'lt' => '§ ĮVESKITE OEM NUMERĮ',
                'fr' => '§ SAISIR LE NUMÉRO OEM', 'es' => '§ INTRODUZCA EL NÚMERO OEM',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_search_meta_hint', 'value' => json_encode([
                'en' => 'min :min chars · uppercase alphanumeric',
                'de' => 'min. :min Zeichen · Großbuchstaben, alphanumerisch',
                'lt' => 'min. :min simb. · didžiosios raidės, raidės ir skaičiai',
                'fr' => 'min. :min caractères · alphanumérique majuscule',
                'es' => 'mín. :min caracteres · alfanumérico en mayúsculas',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_indexed_label', 'value' => json_encode([
                'en' => 'Indexed:', 'de' => 'Indiziert:', 'lt' => 'Indeksuota:',
                'fr' => 'Indexé :', 'es' => 'Indexado:',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_1', 'value' => json_encode([
                'en' => 'Verified Suppliers', 'de' => 'Geprüfte Lieferanten', 'lt' => 'Patikrinti tiekėjai',
                'fr' => 'Fournisseurs vérifiés', 'es' => 'Proveedores verificados',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_2',     'value' => $ml('TLS 1.3 · SSL'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_3', 'value' => json_encode([
                'en' => '27 EU Countries', 'de' => '27 EU-Länder', 'lt' => '27 ES šalys',
                'fr' => '27 pays UE', 'es' => '27 países UE',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            // Was "In Stock Now" — mislabeled: the underlying figure is total active
            // catalog size, not a live in-stock count (fixed 2026-07-16).
            ['group' => 'ui', 'key' => 'hero_spec_r1_label', 'value' => json_encode([
                'en' => 'OEM Parts Indexed', 'de' => 'Indizierte OEM-Teile', 'lt' => 'Indeksuotos OEM dalys',
                'fr' => 'Références OEM indexées', 'es' => 'Piezas OEM indexadas',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r2_label', 'value' => json_encode([
                'en' => 'Manufacturers', 'de' => 'Hersteller', 'lt' => 'Gamintojai',
                'fr' => 'Fabricants', 'es' => 'Fabricantes',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r2_value',     'value' => $ml('214'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r3_label', 'value' => json_encode([
                'en' => 'Cross-refs', 'de' => 'Querverweise', 'lt' => 'Kryžminės nuorodos',
                'fr' => 'Références croisées', 'es' => 'Referencias cruzadas',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r3_value',     'value' => $ml('3.2M'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r4_label', 'value' => json_encode([
                'en' => 'Avg. despatch', 'de' => 'Ø Versand', 'lt' => 'Vid. išsiuntimas',
                'fr' => 'Exp. moyenne', 'es' => 'Envío medio',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r4_value',     'value' => $ml('24h'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r5_label', 'value' => json_encode([
                'en' => 'Languages', 'de' => 'Sprachen', 'lt' => 'Kalbos',
                'fr' => 'Langues', 'es' => 'Idiomas',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
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
            // currency / currency_symbol retired — StoreSettings.php's own
            // "Store Currency" field is a read-only reference to
            // general.currency(_symbol); see the retire-dead-store-currency
            // migration. Every real reader points at general.* now.
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
            ['group' => 'company', 'key' => 'managing_director',    'value' => '',         'type' => $s],
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
            ['group' => 'checkout', 'key' => 'urgent_processing_label', 'value' => json_encode([
                'en' => 'Rush processing',
                'de' => 'Express-Bearbeitung',
                'lt' => 'Skubus apdorojimas',
                'fr' => 'Traitement express',
                'es' => 'Procesamiento urgente',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'checkout', 'key' => 'urgent_processing_description', 'value' => json_encode([
                'en' => 'Priority same-day dispatch for orders placed before 2pm on a business day.',
                'de' => 'Bevorzugter Versand am selben Tag für Bestellungen, die vor 14 Uhr an einem Werktag aufgegeben werden.',
                'lt' => 'Prioritetinis išsiuntimas tą pačią dieną užsakymams, pateiktiems iki 14 val. darbo dieną.',
                'fr' => 'Expédition prioritaire le jour même pour les commandes passées avant 14h un jour ouvré.',
                'es' => 'Envío prioritario el mismo día para pedidos realizados antes de las 14:00 en un día laborable.',
            ], JSON_UNESCAPED_UNICODE), 'type' => $j],
            ['group' => 'contact', 'key' => 'rate_limit_per_minute', 'value' => '5', 'type' => $i],

            // ── DASHBOARD (widget thresholds — defaults mirrored in code) ─────────
            ['group' => 'dashboard', 'key' => 'cart_abandoned_hours',     'value' => '2',  'type' => $i],
            ['group' => 'dashboard', 'key' => 'orders_threshold',         'value' => '50', 'type' => $i],
            ['group' => 'dashboard', 'key' => 'pending_delayed_minutes',  'value' => '120', 'type' => $i],
        ];
    }
}
