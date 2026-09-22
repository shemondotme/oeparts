<?php

namespace App\Support;

use App\Models\LanguageString;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Decorates Laravel's normal file-based translation loader with an
 * override layer read from the `language_strings` table (edited via
 * TranslationResource) — without this, that entire admin screen had zero
 * effect on any __()/trans() call anywhere in the app; the real, live
 * strings only ever came from the static lang/ files.
 *
 * File-loaded lines are the base; a DB row for the same
 * (locale, group, key) wins. Only the default namespace (app-level
 * translations, not package/vendor ones) is overridden.
 */
class DatabaseTranslationLoader implements Loader
{
    public function __construct(private Loader $files) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->files->load($locale, $group, $namespace);

        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        $overrides = $this->overridesFor($locale, $group);

        return $overrides === [] ? $lines : array_replace($lines, $overrides);
    }

    /** @return array<string, string> */
    private function overridesFor(string $locale, string $group): array
    {
        $cacheKey = "translations.db.{$locale}.{$group}";

        // The try/catch wraps the WHOLE rememberForever call, not just the
        // callback: the failure value here is [], a non-null value
        // rememberForever genuinely treats as "already cached forever" —
        // a single transient DB blip on THIS one (locale, group) pair
        // would otherwise have permanently hidden every admin-edited
        // translation override for it, silently falling back to the
        // static lang/ file's text, until someone happened to call
        // forget() for that exact pair.
        try {
            return Cache::rememberForever($cacheKey, function () use ($locale, $group) {
                if (! Schema::hasTable('language_strings')) {
                    return [];
                }

                return LanguageString::where('lang_code', $locale)
                    ->where('group', $group)
                    ->pluck('value', 'key')
                    ->all();
            });
        } catch (\Throwable $e) {
            Log::warning("DatabaseTranslationLoader::overridesFor({$locale}, {$group}) failed: ".$e->getMessage());

            return [];
        }
    }

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->files->namespaces();
    }

    public static function forget(string $locale, string $group): void
    {
        Cache::forget("translations.db.{$locale}.{$group}");
    }
}
