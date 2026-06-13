<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin sidebar / navigation service.
 * Provides:
 *  - Pinned items (admin-specific, persisted in dashboard_preferences JSON on admins table)
 *  - Recent items (last 8 visited resources, also stored in dashboard_preferences)
 *  - Quick action verbs (for command palette)
 *
 * Per CLAUDE.md: never Cache::flush(); use specific Cache::forget() only.
 */
final class AdminNavService
{
    public const MAX_RECENT = 8;

    public static function pinned(?Admin $admin): array
    {
        if (! $admin) {
            return [];
        }

        $prefs = $admin->dashboard_preferences ?? [];
        return $prefs['pinned_nav'] ?? [];
    }

    public static function recent(?Admin $admin): array
    {
        if (! $admin) {
            return [];
        }

        $prefs = $admin->dashboard_preferences ?? [];
        return $prefs['recent_nav'] ?? [];
    }

    public static function pin(?Admin $admin, string $key, string $label, string $url, ?string $icon = null): void
    {
        if (! $admin) {
            return;
        }

        DB::transaction(function () use ($admin, $key, $label, $url, $icon) {
            $prefs = $admin->dashboard_preferences ?? [];
            $pinned = $prefs['pinned_nav'] ?? [];

            // Avoid duplicates
            $pinned = array_values(array_filter($pinned, fn ($i) => ($i['key'] ?? null) !== $key));

            array_unshift($pinned, [
                'key' => $key,
                'label' => e(strip_tags($label)),
                'url' => $url,
                'icon' => $icon,
                'pinned_at' => now()->toIso8601String(),
            ]);

            $prefs['pinned_nav'] = array_slice($pinned, 0, 5);
            $admin->dashboard_preferences = $prefs;

            try {
                $admin->save();
            } catch (\Exception $e) {
                Log::error('Failed to save pinned nav', [
                    'admin_id' => $admin->id,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public static function unpin(?Admin $admin, string $key): void
    {
        if (! $admin) {
            return;
        }

        $prefs = $admin->dashboard_preferences ?? [];
        $pinned = $prefs['pinned_nav'] ?? [];
        $pinned = array_values(array_filter($pinned, fn ($i) => ($i['key'] ?? null) !== $key));
        $prefs['pinned_nav'] = $pinned;
        $admin->dashboard_preferences = $prefs;

        try {
            $admin->save();
        } catch (\Exception $e) {
            Log::error('Failed to save unpinned nav', [
                'admin_id' => $admin->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
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

    /**
     * Quick action verbs for command palette.
     * Returns array of [key, label, hint, url, group, icon].
     */
    public static function quickActions(): array
    {
        return [
            ['key' => 'new.product',  'label' => 'Create new product',    'group' => 'Create',  'icon' => 'heroicon-o-cube',                'url' => '/admin/products/create'],
            ['key' => 'new.order',    'label' => 'Create new order',      'group' => 'Create',  'icon' => 'heroicon-o-shopping-bag',        'url' => '/admin/orders/create'],
            ['key' => 'new.coupon',   'label' => 'Create new coupon',     'group' => 'Create',  'icon' => 'heroicon-o-ticket',              'url' => '/admin/coupons/create'],
            ['key' => 'new.blog',     'label' => 'Create new blog post',  'group' => 'Create',  'icon' => 'heroicon-o-document-text',       'url' => '/admin/blog-posts/create'],
            ['key' => 'new.page',     'label' => 'Create new page',       'group' => 'Create',  'icon' => 'heroicon-o-document',            'url' => '/admin/pages/create'],
            ['key' => 'new.customer', 'label' => 'Create new customer',   'group' => 'Create',  'icon' => 'heroicon-o-user-plus',           'url' => '/admin/customers/create'],
            ['key' => 'go.orders',    'label' => 'Go to Orders',          'group' => 'Navigate', 'icon' => 'heroicon-o-shopping-bag',        'url' => '/admin/orders'],
            ['key' => 'go.products',  'label' => 'Go to Products',        'group' => 'Navigate', 'icon' => 'heroicon-o-cube',                'url' => '/admin/products'],
            ['key' => 'go.reports',   'label' => 'Go to Reports',         'group' => 'Navigate', 'icon' => 'heroicon-o-presentation-chart-line','url' => '/admin/reports'],
            ['key' => 'go.settings',  'label' => 'Go to Settings',        'group' => 'Navigate', 'icon' => 'heroicon-o-cog-6-tooth',         'url' => '/admin/settings'],
            ['key' => 'go.health',    'label' => 'Go to Health Check',    'group' => 'Navigate', 'icon' => 'heroicon-o-heart',               'url' => '/admin/system/health-check'],
        ];
    }
}
