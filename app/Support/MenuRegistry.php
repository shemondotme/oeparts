<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
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

        $items = Cache::rememberForever($cacheKey, function () use ($location, $locale) {
            try {
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
            } catch (\Throwable) {
                return null;
            }
        });

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

        return Cache::rememberForever($cacheKey, function () use ($column, $locale) {
            try {
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
            } catch (\Throwable) {
                return [];
            }
        });
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
