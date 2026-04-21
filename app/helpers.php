<?php

use App\Services\SettingsService;

/**
 * Get a setting value by dot-notation key.
 *
 * Usage: settings('tax.default_vat_rate', 21)
 * NEVER hardcode thresholds, rates, or config values — always use this.
 */
function settings(string $key, mixed $default = null): mixed
{
    return app(SettingsService::class)->get($key, $default);
}

/**
 * Get a translated field value from a JSON multilang column.
 *
 * The column is stored as: {"en": "...", "de": "...", "lt": "...", "fr": "...", "es": "..."}
 *
 * Usage in Blade: {{ trans_field($product->name) }}
 *         or:    {{ trans_field($product->name, 'de') }}
 */
function trans_field(array|string|null $field, string $locale = null): string
{
    if (is_string($field)) {
        return $field;
    }

    if (empty($field)) {
        return '';
    }

    $locale = $locale ?? app()->getLocale();

    return $field[$locale] ?? $field['en'] ?? reset($field) ?? '';
}

/**
 * Detect the preferred language from the browser Accept-Language header.
 * Falls back to 'en' if no supported language is found.
 */
function detectBrowserLanguage(\Illuminate\Http\Request $request): string
{
    $supported = ['en', 'de', 'lt', 'fr', 'es'];
    $preferred = $request->getPreferredLanguage($supported);

    return in_array($preferred, $supported) ? $preferred : 'en';
}

/**
 * Format a price value with currency symbol and proper decimal places.
 * Uses bcmath for safe formatting (bcscale(2) is set globally in AppServiceProvider).
 * Uses NumberFormatter for locale-aware formatting.
 *
 * Examples:
 *   EN: €1,234.56
 *   DE: 1.234,56 €
 *   LT: 1 234,56 €
 *   FR: 1 234,56 €
 *   ES: 1.234,56 €
 *
 * @param string|int|float $price The price value (stored as string in database)
 * @param string|null $currencyCode Optional currency code (defaults to settings('general.currency', 'EUR'))
 * @param string|null $locale Optional locale (defaults to current app locale)
 * @return string Formatted price with currency symbol
 */
function format_price($price, ?string $currencyCode = null, ?string $locale = null): string
{
    // Ensure price is a string for bcmath operations
    $price = (string) $price;
    
    // Get current locale if not provided
    $locale = $locale ?? app()->getLocale();
    
    // Get currency settings
    $currencyCode = $currencyCode ?? settings('general.currency', 'EUR');

    // Use NumberFormatter for locale-aware formatting
    $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    
    // Set currency code if different from default
    if ($currencyCode !== 'EUR') {
        $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currencyCode);
    }
    
    // Format the price
    return $formatter->formatCurrency((float) $price, $currencyCode);
}

/**
 * Alias for format_price for backward compatibility.
 * Some views use format_money instead of format_price.
 *
 * @param string|int|float $price The price value
 * @param string|null $currencyCode Optional currency code
 * @param string|null $locale Optional locale
 * @return string Formatted price with currency symbol
 */
function format_money($price, ?string $currencyCode = null, ?string $locale = null): string
{
    return format_price($price, $currencyCode, $locale);
}

/**
 * Format a date/timestamp using locale-aware formatting.
 * Uses IntlDateFormatter for proper localization.
 *
 * Examples:
 *   EN: Mar 14, 2025
 *   DE: 14.03.2025
 *   LT: 2025-03-14
 *   FR: 14 mars 2025
 *   ES: 14 mar 2025
 *
 * @param \DateTimeInterface|string|int|null $date Date to format
 * @param int $style Date style (IntlDateFormatter::SHORT, MEDIUM, LONG, FULL)
 * @param string|null $locale Optional locale (defaults to current app locale)
 * @return string Formatted date
 */
function format_date($date, int $style = \IntlDateFormatter::MEDIUM, ?string $locale = null): string
{
    if (!$date) {
        return '';
    }
    
    // Convert to DateTime if needed
    if (is_string($date) || is_int($date)) {
        $date = new \DateTime($date);
    }
    
    // Get current locale if not provided
    $locale = $locale ?? app()->getLocale();
    
    // Map locale codes (Laravel uses en, de, lt, etc.)
    $locale = str_replace('_', '-', $locale);
    
    $formatter = new \IntlDateFormatter(
        $locale,
        $style,
        \IntlDateFormatter::NONE
    );
    
    return $formatter->format($date);
}

/**
 * Format a datetime with both date and time using locale-aware formatting.
 *
 * Examples:
 *   EN: Mar 14, 2025, 2:30 PM
 *   DE: 14.03.2025, 14:30
 *   LT: 2025-03-14 14:30
 *
 * @param \DateTimeInterface|string|int|null $datetime DateTime to format
 * @param int $dateStyle Date style (IntlDateFormatter::SHORT, MEDIUM, LONG, FULL)
 * @param int $timeStyle Time style (IntlDateFormatter::SHORT, MEDIUM, LONG, FULL)
 * @param string|null $locale Optional locale (defaults to current app locale)
 * @return string Formatted datetime
 */
function format_datetime($datetime, int $dateStyle = \IntlDateFormatter::MEDIUM, int $timeStyle = \IntlDateFormatter::SHORT, ?string $locale = null): string
{
    if (!$datetime) {
        return '';
    }
    
    // Convert to DateTime if needed
    if (is_string($datetime) || is_int($datetime)) {
        $datetime = new \DateTime($datetime);
    }
    
    // Get current locale if not provided
    $locale = $locale ?? app()->getLocale();
    
    // Map locale codes
    $locale = str_replace('_', '-', $locale);
    
    $formatter = new \IntlDateFormatter(
        $locale,
        $dateStyle,
        $timeStyle
    );
    
    return $formatter->format($datetime);
}
