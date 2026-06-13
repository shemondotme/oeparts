<?php

namespace App\Services;

/**
 * TranslationService — resolve a value from a multilang JSON field.
 *
 * All multilang columns are stored as JSON arrays keyed by locale code:
 *   {"en": "Spare Part", "de": "Ersatzteil", "lt": "Atsarginė dalis", ...}
 *
 * This service is the authoritative resolver. The trans_field() global
 * helper delegates to this service.
 *
 * Fallback chain: requested locale → 'en' → first available value → ''
 */
class TranslationService
{
    /** @var string[] Supported locales in priority order */
    private array $locales;

    public function __construct()
    {
        $this->locales = config('app.supported_locales', ['en', 'de', 'lt', 'fr', 'es']);
    }

    /**
     * Resolve the translated value from a multilang field.
     *
     * @param  array|string|null  $field   The decoded JSON field (or raw JSON string)
     * @param  string|null        $locale  Target locale; defaults to app()->getLocale()
     */
    public function get(mixed $field, ?string $locale = null): string
    {
        if (empty($field)) {
            return '';
        }

        // Accept both already-decoded arrays and raw JSON strings
        if (is_string($field)) {
            $decoded = json_decode($field, true);
            $field   = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($field)) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();

        // Exact match
        if (isset($field[$locale]) && $field[$locale] !== '') {
            return $field[$locale];
        }

        // Fallback to English
        if ($locale !== 'en' && isset($field['en']) && $field['en'] !== '') {
            return $field['en'];
        }

        // Fallback to first non-empty value
        foreach ($this->locales as $fallback) {
            if (isset($field[$fallback]) && $field[$fallback] !== '') {
                return $field[$fallback];
            }
        }

        return '';
    }

    /**
     * Build a full multilang array from a scalar value applied to all locales.
     *
     * @param  string  $value  The value to apply to every supported locale
     */
    public function fill(string $value): array
    {
        return array_fill_keys($this->locales, $value);
    }

    /**
     * Merge a partial translation array with defaults.
     * Missing locales will fall back to the English value or empty string.
     *
     * @param  array  $translations  Partial ['en' => 'Foo', 'de' => 'Bar']
     */
    public function merge(array $translations): array
    {
        $fallback = $translations['en'] ?? '';
        $result   = [];

        foreach ($this->locales as $locale) {
            $result[$locale] = $translations[$locale] ?? $fallback;
        }

        return $result;
    }

    /**
     * Check if a multilang field has a non-empty value for any locale.
     */
    public function hasAny(mixed $field): bool
    {
        return $this->get($field) !== '';
    }
}
