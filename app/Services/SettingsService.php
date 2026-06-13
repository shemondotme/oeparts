<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
                                Log::warning('Failed to decrypt setting value', [
                                    'group' => $setting->group,
                                    'key' => $setting->key,
                                    'error' => $e->getMessage(),
                                ]);
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

        $setting = \App\Models\Setting::where('group', $group)->where('key', $settingKey)->first();

        $sensitivePatterns = ['password', 'secret', 'key', 'token', 'api_key', 'access_key'];
        $shouldEncrypt = $setting
            ? $setting->is_encrypted
            : in_array($settingKey, $sensitivePatterns)
                || str_contains(strtolower($settingKey), 'password')
                || str_contains(strtolower($settingKey), 'secret')
                || str_contains(strtolower($settingKey), '_key')
                || str_contains(strtolower($settingKey), 'token');

        if ($shouldEncrypt && $value) {
            try {
                $value = \Illuminate\Support\Facades\Crypt::encryptString((string) $value);
            } catch (\Exception $e) {
                Log::critical('Failed to encrypt setting value', [
                    'group' => $group,
                    'key' => $settingKey,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        \App\Models\Setting::updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            ['value' => $value, 'is_encrypted' => $shouldEncrypt]
        );

        Log::info('Setting updated', ['group' => $group, 'key' => $settingKey, 'admin' => auth('admin')->id()]);

        $this->forget($group);
    }
}
