<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * SettingsService — reads settings from DB, cached per group for 5 minutes.
 *
 * IMPORTANT: This service MUST NOT call settings() or CacheService for its own
 * cache TTL — that would create a circular dependency. It uses a hardcoded 5-minute
 * TTL for its own cache and reads Setting model directly.
 */
class SettingsService
{
    /**
     * Get a setting value by dot-notation key (group.key).
     *
     * @param  string  $key      e.g. 'tax.default_vat_rate'
     * @param  mixed   $default  fallback if setting not found
     */
    public function get(string $key, mixed $default = null): mixed
    {
        [$group, $settingKey] = array_pad(explode('.', $key, 2), 2, null);

        if (! $group || ! $settingKey) {
            return $default;
        }

        $cacheKey = "settings.{$group}";

        $groupSettings = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($group) {
            try {
                return \App\Models\Setting::where('group', $group)
                    ->get()
                    ->keyBy('key')
                    ->map(function ($setting) {
                        $value = $setting->value;
                        if ($setting->is_encrypted && $value) {
                            try {
                                $value = \Illuminate\Support\Facades\Crypt::decryptString($value);
                            } catch (\Exception $e) {
                            }
                        }
                        return $value;
                    })
                    ->toArray();
            } catch (\Exception) {
                return [];
            }
        });

        return $groupSettings[$settingKey] ?? $default;
    }

    /**
     * Forget (invalidate) a cached settings group.
     */
    public function forget(string $group): void
    {
        Cache::forget("settings.{$group}");
    }

    /**
     * Set a setting value and invalidate its group cache.
     */
    public function set(string $key, mixed $value): void
    {
        [$group, $settingKey] = array_pad(explode('.', $key, 2), 2, null);

        if (! $group || ! $settingKey) {
            return;
        }

        \App\Models\Setting::updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            ['value' => $value]
        );

        $this->forget($group);
    }
}
