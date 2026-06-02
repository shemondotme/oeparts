<?php

use App\Services\SettingsService;
use Illuminate\Http\Request;

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
 * String from a multilang JSON setting (or plain string) with current locale.
 * Settings values from DB for type=json may be stored as JSON string.
 */
function settings_trans(string $key, string $default = ''): string
{
    $raw = settings($key, null);
    if ($raw === null || $raw === '') {
        return $default;
    }
    if (is_array($raw)) {
        $t = trans_field($raw);

        return $t !== '' ? $t : $default;
    }
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && $decoded !== []) {
            $out = trans_field($decoded);
            if ($out !== '') {
                return $out;
            }
        }

        return $raw !== '' ? $raw : $default;
    }

    return $default;
}

/**
 * UI string from the `ui` settings group, with fallback to a lang file key.
 * Placeholders in the form :key are replaced from $replace (Laravel __() style).
 */
function ui_copy(string $uiKey, string $fallbackKey, array $replace = []): string
{
    $line = settings_trans('ui.'.$uiKey, '');
    if (! is_string($line) || $line === '') {
        return __($fallbackKey, $replace);
    }

    return ui_apply_string_replacements($line, $replace);
}

/**
 * @param  int|float  $number
 */
function ui_trans_choice(string $uiKey, string $fallbackKey, $number, array $replace = []): string
{
    $line = settings_trans('ui.'.$uiKey, '');
    if (! is_string($line) || $line === '') {
        return trans_choice($fallbackKey, $number, $replace);
    }

    return trans_choice($line, $number, $replace);
}

function ui_apply_string_replacements(string $line, array $replace): string
{
    if ($replace === []) {
        return $line;
    }
    foreach ($replace as $key => $value) {
        $k = ltrim((string) $key, ':');
        $line = str_replace(':'.$k, (string) $value, $line);
    }

    return $line;
}

/**
 * Get a translated field value from a JSON multilang column.
 *
 * The column is stored as: {"en": "...", "de": "...", "lt": "...", "fr": "...", "es": "..."}
 *
 * Usage in Blade: {{ trans_field($product->name) }}
 *         or:    {{ trans_field($product->name, 'de') }}
 */
function trans_field(array|string|null $field, ?string $locale = null): string
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
function detectBrowserLanguage(Request $request): string
{
    $supported = ['en', 'de', 'lt', 'fr', 'es'];
    $preferred = $request->getPreferredLanguage($supported);

    return in_array($preferred, $supported) ? $preferred : 'en';
}

/**
 * Format a price value with currency symbol and proper decimal places.
 * Numbers are stored as DECIMAL strings in the database and processed with bcmath.
 * The (float) cast is used only as the final step for NumberFormatter.
 *
 * Examples:
 *   EN: €1,234.56
 *   DE: 1.234,56 €
 *   LT: 1 234,56 €
 *   FR: 1 234,56 €
 *   ES: 1.234,56 €
 *
 * @param  string|int|float  $price  The price value (stored as string in database)
 * @param  string|null  $currencyCode  Optional currency code (defaults to settings('general.currency', 'EUR'))
 * @param  string|null  $locale  Optional locale (defaults to current app locale)
 * @return string Formatted price with currency symbol
 */
function format_price($price, ?string $currencyCode = null, ?string $locale = null): string
{
    // Ensure price is a string for bcmath operations
    $price = (string) $price;

    $locale = $locale ?? app()->getLocale();
    $currencyCode = $currencyCode ?? settings('general.currency', 'EUR');

    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

    if ($currencyCode !== 'EUR') {
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currencyCode);
    }

    // NumberFormatter requires a float; this is the only acceptable float conversion in the system
    return $formatter->formatCurrency((float) $price, $currencyCode);
}

/**
 * Alias for format_price for backward compatibility.
 * Some views use format_money instead of format_price.
 *
 * @param  string|int|float  $price  The price value
 * @param  string|null  $currencyCode  Optional currency code
 * @param  string|null  $locale  Optional locale
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
 * @param  DateTimeInterface|string|int|null  $date  Date to format
 * @param  int  $style  Date style (IntlDateFormatter::SHORT, MEDIUM, LONG, FULL)
 * @param  string|null  $locale  Optional locale (defaults to current app locale)
 * @return string Formatted date
 */
function format_date($date, int $style = IntlDateFormatter::MEDIUM, ?string $locale = null): string
{
    if (! $date) {
        return '';
    }

    // Convert to DateTime if needed
    if (is_string($date) || is_int($date)) {
        $date = new DateTime($date);
    }

    // Get current locale if not provided
    $locale = $locale ?? app()->getLocale();

    // Map locale codes (Laravel uses en, de, lt, etc.)
    $locale = str_replace('_', '-', $locale);

    $formatter = new IntlDateFormatter(
        $locale,
        $style,
        IntlDateFormatter::NONE
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
 * @param  DateTimeInterface|string|int|null  $datetime  DateTime to format
 * @param  int  $dateStyle  Date style (IntlDateFormatter::SHORT, MEDIUM, LONG, FULL)
 * @param  int  $timeStyle  Time style (IntlDateFormatter::SHORT, MEDIUM, LONG, FULL)
 * @param  string|null  $locale  Optional locale (defaults to current app locale)
 * @return string Formatted datetime
 */
function format_datetime($datetime, int $dateStyle = IntlDateFormatter::MEDIUM, int $timeStyle = IntlDateFormatter::SHORT, ?string $locale = null): string
{
    if (! $datetime) {
        return '';
    }

    // Convert to DateTime if needed
    if (is_string($datetime) || is_int($datetime)) {
        $datetime = new DateTime($datetime);
    }

    // Get current locale if not provided
    $locale = $locale ?? app()->getLocale();

    // Map locale codes
    $locale = str_replace('_', '-', $locale);

    $formatter = new IntlDateFormatter(
        $locale,
        $dateStyle,
        $timeStyle
    );

    return $formatter->format($datetime);
}
