<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Log;

/**
 * Admin sidebar / navigation service.
 * Provides:
 *  - Recent items (last 8 visited resources, stored in dashboard_preferences JSON on admins table)
 *
 * "Create X" / "Go to X" quick actions live in AdminUi::QUICK_CREATE_REGISTRY instead —
 * do not reintroduce a second registry here.
 */
final class AdminNavService
{
    public const MAX_RECENT = 8;

    public static function recent(?Admin $admin): array
    {
        if (! $admin) {
            return [];
        }

        $prefs = $admin->dashboard_preferences ?? [];
        return $prefs['recent_nav'] ?? [];
    }

    /**
     * Permission-safe view of recent() — drops any entry whose underlying
     * resource/page is no longer reachable in the admin's current (already
     * permission-filtered) navigation tree. Used by the topbar command
     * palette's idle state ("Recently Viewed"). See CLAUDE.md rule #28:
     * re-derive against live nav, never trust a stored URL/label as still
     * permission-valid.
     *
     * Validates at the resource/page level (an entry is dropped if no nav
     * item's URL is, or is a parent path of, the entry's URL) rather than
     * re-deriving the label, since Edit/View pages aren't separately
     * registered in the nav tree — only their List page is.
     *
     * @return array<int, array{key: string, label: string, url: string, group: ?string, icon: ?string}>
     */
    public static function validRecent(?Admin $admin): array
    {
        if (! $admin) {
            return [];
        }

        $homeUrl = filament()->getHomeUrl();

        $available = collect(filament()->getNavigation())
            ->flatMap(fn ($group) => collect($group->getItems())->map(fn ($item) => [
                'url' => $item->getUrl(),
                'icon' => $item->getIcon(),
            ]))
            ->reject(fn ($item) => $item['url'] === $homeUrl)
            ->unique('url')
            ->values();

        return collect(self::recent($admin))
            ->map(function ($item) use ($available) {
                $url = $item['url'] ?? null;
                $match = $url ? $available->first(fn ($nav) => $url === $nav['url'] || str_starts_with($url, $nav['url'] . '/')) : null;

                if (! $match) {
                    return null;
                }

                $item['icon'] = $match['icon'];

                return $item;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function recordVisit(?Admin $admin, string $key, string $label, string $url, ?string $group = null): void
    {
        if (! $admin) {
            return;
        }

        $prefs = $admin->dashboard_preferences ?? [];
        $recent = $prefs['recent_nav'] ?? [];

        $recent = array_values(array_filter($recent, fn ($i) => ($i['key'] ?? null) !== $key));

        array_unshift($recent, [
            'key' => $key,
            'label' => e(strip_tags($label)),
            'url' => $url,
            'group' => $group,
            'visited_at' => now()->toIso8601String(),
        ]);

        $prefs['recent_nav'] = array_slice($recent, 0, self::MAX_RECENT);
        $admin->dashboard_preferences = $prefs;

        try {
            $admin->save();
        } catch (\Exception $e) {
            Log::error('Failed to save recent nav visit', [
                'admin_id' => $admin->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
