<?php

namespace App\Support;

use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for "which locales are live on the storefront" —
 * derived from the `languages` table (LanguageResource's is_active toggle)
 * instead of the five-locale array that used to be hardcoded independently
 * in SetLocale, routes/web.php's {lang} route constraint, SeoService, and
 * SitemapService. Before this, deactivating (or adding) a language in the
 * admin panel had no effect on the storefront whatsoever — the language
 * switcher, sitemap, hreflang tags, and route matching all still only ever
 * knew about the original five.
 *
 * Falls back to that original five-locale list whenever the `languages`
 * table isn't queryable (pre-migration during install, a fresh test DB
 * mid-setup, etc.) — this registry must never be the reason the app fails
 * to boot.
 */
class LocaleRegistry
{
    private const CACHE_KEY = 'locales.active';

    /** @var array<int, string> */
    private const FALLBACK_CODES = ['en', 'de', 'lt', 'fr', 'es'];

    /**
     * Active languages ordered by sort_order, as plain arrays (cache-safe —
     * avoids serializing Eloquent models into the cache store).
     *
     * @return array<int, array{code: string, name: string, native_name: string, flag_emoji: string, is_default: bool}>
     */
    public static function languages(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            try {
                if (! Schema::hasTable('languages')) {
                    return self::fallbackLanguages();
                }

                $rows = Language::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['code', 'name', 'native_name', 'flag_emoji', 'is_default'])
                    ->map(fn (Language $l) => [
                        'code' => $l->code,
                        'name' => $l->name,
                        'native_name' => $l->native_name,
                        'flag_emoji' => $l->flag_emoji,
                        'is_default' => (bool) $l->is_default,
                    ])
                    ->all();

                return $rows === [] ? self::fallbackLanguages() : $rows;
            } catch (\Throwable) {
                return self::fallbackLanguages();
            }
        });
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_column(self::languages(), 'code');
    }

    public static function defaultCode(): string
    {
        foreach (self::languages() as $language) {
            if ($language['is_default']) {
                return $language['code'];
            }
        }

        return self::codes()[0] ?? 'en';
    }

    /** Route-safe alternation pattern, e.g. "en|de|lt|fr|es". */
    public static function routePattern(): string
    {
        $codes = array_map(fn (string $code) => preg_quote($code, '#'), self::codes());

        return $codes === [] ? implode('|', self::FALLBACK_CODES) : implode('|', $codes);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<int, array{code: string, name: string, native_name: string, flag_emoji: string, is_default: bool}> */
    private static function fallbackLanguages(): array
    {
        $names = [
            'en' => ['English', 'English', '🇬🇧'],
            'de' => ['German', 'Deutsch', '🇩🇪'],
            'lt' => ['Lithuanian', 'Lietuvių', '🇱🇹'],
            'fr' => ['French', 'Français', '🇫🇷'],
            'es' => ['Spanish', 'Español', '🇪🇸'],
        ];

        return array_map(fn (string $code) => [
            'code' => $code,
            'name' => $names[$code][0],
            'native_name' => $names[$code][1],
            'flag_emoji' => $names[$code][2],
            'is_default' => $code === 'en',
        ], self::FALLBACK_CODES);
    }
}
