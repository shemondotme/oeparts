<?php

use App\Models\Condition;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

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
 * Per-request Content-Security-Policy nonce.
 *
 * Bound by App\Http\Middleware\ContentSecurityPolicy before the response renders.
 * Used by inline <script>/<style> tags (e.g. settings-driven header/footer scripts,
 * custom CSS). Returns '' outside the HTTP middleware stack (console, tests) so
 * views never fatal with an undefined-function error.
 */
function csp_nonce(): string
{
    return app()->bound('csp-nonce') ? (string) app('csp-nonce') : '';
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

            return $out !== '' ? $out : $default;
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
 * Localized display name for an ISO 3166-1 alpha-2 country code, in the
 * current (or given) storefront locale — via PHP's intl extension, so
 * adding a checkout country never means hand-translating a name x4 locales.
 */
function localized_country_name(string $code, ?string $locale = null): string
{
    $locale = $locale ?? app()->getLocale();

    $name = \Locale::getDisplayRegion('und-'.strtoupper($code), $locale);

    return $name !== '' ? $name : $code;
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

/**
 * Translated display label for a Condition (New / Used / Remanufactured / ...).
 *
 * search.php has a full per-locale `condition_label_{slug}` set (New Old Stock,
 * Aftermarket, Used Grade A/B/C, etc.) that search results pages never actually
 * used — they printed Condition::name (a single plain-string DB column) verbatim,
 * so every locale saw whatever language the admin typed the condition name in.
 * Falls back to the raw DB name for any slug without a matching translation key,
 * so an admin can add a brand-new condition type without a code change breaking it.
 */
function condition_label(?Condition $condition): string
{
    if (! $condition) {
        return __('search.condition_label_new');
    }

    $key = 'condition_label_' . str_replace('-', '_', $condition->slug);

    return Lang::has("search.{$key}") ? __("search.{$key}") : $condition->name;
}

/**
 * Split a brand/company name into the [heavy, light] pair used by the
 * text-based wordmark (e.g. "OeParts" -> "Oe" + "Parts"), so the storefront
 * navbar, transactional emails, and the invoice PDF all render the exact
 * same split-weight lockup instead of drifting into inconsistent, hand-typed
 * variants. Splits at the first lowercase→uppercase boundary; falls back to
 * a single (heavy) weight with no light part when no such boundary exists.
 *
 * @return array{0: string, 1: string}
 */
function brand_wordmark_parts(string $name): array
{
    if (preg_match('/^([A-Z][a-z]+?)([A-Z].*)$/', $name, $m)) {
        return [$m[1], $m[2]];
    }

    return [$name, ''];
}
