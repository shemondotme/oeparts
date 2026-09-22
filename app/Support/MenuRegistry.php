<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the active, admin-configured Menu for a (location, locale) pair
 * into ready-to-render link data — until now, MenuResource/MenuItem was a
 * fully-built admin feature (CRUD, ordering, nesting, CMS-page linking)
 * with no code anywhere reading it: navbar.blade.php and footer.blade.php
 * both built their nav from a hardcoded PHP array, so building a menu in
 * the admin had no effect on the storefront at all.
 *
 * Returns null when no active menu is configured for that slot, so callers
 * can fall back to their existing default links — this only takes over
 * once an operator has actually built a menu for that location+locale.
 */
class MenuRegistry
{
    /**
     * @return array<int, array{label: string, url: string, target: string}>|null
     */
    public static function items(string $location, string $locale): ?array
    {
        $cacheKey = "menus.{$location}.{$locale}";

        // The try/catch wraps the WHOLE rememberForever call, not just the
        // callback — a failure must never get memoized as "no menu here"
        // (rememberForever caches whatever the callback returns, including
        // a value standing in for "broken"), or a single transient DB blip
        // would silently hide a real, correctly-configured menu until
        // someone happens to call forget()/forgetAll().
        try {
            $items = Cache::rememberForever($cacheKey, function () use ($location, $locale) {
                if (! Schema::hasTable('menus')) {
                    return null;
                }

                $menu = Menu::where('location', $location)
                    ->where('lang', $locale)
                    ->where('is_active', true)
                    ->with(['items' => function ($query) {
                        $query->whereNull('parent_id')->orderBy('sort_order');
                    }, 'items.page'])
                    ->first();

                if (! $menu || $menu->items->isEmpty()) {
                    return null;
                }

                return $menu->items
                    ->map(function ($item) use ($locale) {
                        $url = self::resolveUrl($item, $locale);

                        if ($url === null) {
                            return null;
                        }

                        return [
                            'label' => trans_field($item->label, $locale) ?: $url,
                            'url' => $url,
                            'target' => $item->target?->value ?? '_self',
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            });
        } catch (\Throwable $e) {
            Log::warning("MenuRegistry::items({$location}, {$locale}) failed: ".$e->getMessage());

            return null;
        }

        return $items === [] ? null : $items;
    }

    private static function resolveUrl($item, string $locale): ?string
    {
        if ($item->type === 'page') {
            $page = $item->page;

            if (! $page || $page->status !== ContentStatus::Published) {
                return null;
            }

            return url("/{$locale}/{$page->slug}");
        }

        return $item->url;
    }

    public static function forget(string $location, string $locale): void
    {
        Cache::forget("menus.{$location}.{$locale}");
    }

    public static function forgetAll(): void
    {
        foreach (['header', 'footer'] as $location) {
            foreach (LocaleRegistry::codes() as $locale) {
                self::forget($location, $locale);
            }
        }
    }

    /**
     * Pages with is_header/is_footer set — PageResource's own quick-toggle
     * ("Add this page link to the main header/footer navigation menu"),
     * independent of and additive to an operator-curated Menu. Also had no
     * reader anywhere before this fix.
     *
     * @return array<int, array{label: string, url: string, target: string}>
     */
    public static function pageFlaggedItems(string $column, string $locale): array
    {
        $cacheKey = "menus.pages.{$column}.{$locale}";

        // Same reasoning as items() above: the try/catch wraps the WHOLE
        // rememberForever call. Unlike items() (whose failure value is
        // null, which rememberForever's own is_null() check transparently
        // recomputes on the next read), this method's failure value used
        // to be [] — a non-null value rememberForever genuinely treats as
        // "already cached forever" — so a single transient DB blip would
        // have permanently hidden every page-flagged nav link until
        // someone happened to call forgetPageFlagged().
        try {
            return Cache::rememberForever($cacheKey, function () use ($column, $locale) {
                if (! Schema::hasTable('pages')) {
                    return [];
                }

                return Page::where($column, true)
                    ->where('status', ContentStatus::Published)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->orderBy('title')
                    ->get()
                    ->map(fn (Page $page) => [
                        'label' => trans_field($page->title, $locale) ?: $page->slug,
                        'url' => url("/{$locale}/{$page->slug}"),
                        'target' => '_self',
                    ])
                    ->all();
            });
        } catch (\Throwable $e) {
            Log::warning("MenuRegistry::pageFlaggedItems({$column}, {$locale}) failed: ".$e->getMessage());

            return [];
        }
    }

    public static function forgetPageFlagged(): void
    {
        foreach (['is_header', 'is_footer'] as $column) {
            foreach (LocaleRegistry::codes() as $locale) {
                Cache::forget("menus.pages.{$column}.{$locale}");
            }
        }
    }
}
