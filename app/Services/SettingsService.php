<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\EncryptedSettingValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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
    private const UNDECRYPTABLE_CACHE_KEY = 'settings.undecryptable';

    /**
     * Get a setting value by dot-notation key (group.key).
     *
     * @param  string  $key  e.g. 'tax.default_vat_rate'
     * @param  mixed  $default  fallback if setting not found
     */
    public function get(string $key, mixed $default = null): mixed
    {
        [$group, $settingKey] = array_pad(explode('.', $key, 2), 2, null);

        if (! $group || ! $settingKey) {
            return $default;
        }

        return $this->getGroup($group)[$settingKey] ?? $default;
    }

    /**
     * Fetch an entire settings group as a flat [key => decrypted value]
     * array, cached for 5 minutes under the same "settings.{group}" key
     * get() itself uses — anything that needs a whole group at once (e.g.
     * SettingsSyncServiceProvider's boot-time mail/OAuth/session config
     * sync) should call this instead of querying the Setting model
     * directly, so it shares this cache rather than running its own
     * uncached query on every single request.
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array
    {
        $cacheKey = "settings.{$group}";

        $fetchFromDb = function () use ($group) {
            try {
                return Setting::where('group', $group)
                    ->get()
                    ->keyBy('key')
                    ->map(function ($setting) {
                        $value = $setting->value;
                        if ($setting->is_encrypted && $value) {
                            // An unreadable secret becomes '' — never the raw
                            // ciphertext (see EncryptedSettingValue).
                            $value = EncryptedSettingValue::reveal($value, $setting->group.'.'.$setting->key)->value;
                        }

                        return $value;
                    })
                    ->toArray();
            } catch (\Throwable) {
                // Settings table may not exist yet (pre-install) or the query failed.
                return [];
            }
        };

        // Cache::remember() itself can throw (e.g. the configured cache store is
        // unreachable — Redis extension missing/server down). That failure must
        // degrade to a direct DB read rather than bubble up: settings() is called
        // eagerly at route-registration time in routes/web.php (rate-limit config),
        // so an uncaught cache-driver exception here takes down every single page,
        // including the web installer itself, before any request-level code runs.
        try {
            return Cache::remember($cacheKey, now()->addMinutes(5), $fetchFromDb);
        } catch (\Throwable $e) {
            // \Throwable, not \Exception: a missing Redis extension surfaces as
            // \Error ("Class \"Redis\" not found"), which \Exception does not catch.
            Log::warning('Settings cache store unreachable, falling back to direct DB read', [
                'group' => $group,
                'error' => $e->getMessage(),
            ]);

            return $fetchFromDb();
        }
    }

    /**
     * Forget (invalidate) a cached settings group.
     */
    public function forget(string $group): void
    {
        Cache::forget("settings.{$group}");
        Cache::forget(self::UNDECRYPTABLE_CACHE_KEY);
    }

    /**
     * Dotted keys ("group.key") of stored secrets this server cannot decrypt
     * (a restored/migrated site with a different APP_KEY, a rotated key, or
     * corrupted ciphertext). Cached 5 minutes with the same hard-coded TTL as
     * the rest of this service; saving any setting forgets it.
     *
     * @return array<int, string>
     */
    public function undecryptableSecrets(): array
    {
        try {
            return Cache::remember(self::UNDECRYPTABLE_CACHE_KEY, now()->addMinutes(5), function () {
                $keys = [];

                Setting::where('is_encrypted', true)
                    ->whereNotNull('value')
                    ->where('value', '!=', '')
                    ->get(['group', 'key', 'value'])
                    ->each(function ($setting) use (&$keys) {
                        if (! EncryptedSettingValue::reveal($setting->value, '', true)->ok) {
                            $keys[] = $setting->group.'.'.$setting->key;
                        }
                    });

                sort($keys);

                return $keys;
            });
        } catch (\Throwable $e) {
            Log::warning('Could not scan settings for undecryptable secrets', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * True when this one key holds an encrypted value that cannot be read with
     * this server's key. Reads the row directly (uncached) — callers use it to
     * tell "never configured" (false) apart from "configured but unreadable".
     */
    public function isStoredSecretUnreadable(string $key): bool
    {
        [$group, $settingKey] = array_pad(explode('.', $key, 2), 2, null);

        if (! $group || ! $settingKey) {
            return false;
        }

        try {
            $setting = Setting::where('group', $group)->where('key', $settingKey)->first(['value', 'is_encrypted']);
        } catch (\Throwable) {
            return false; // Settings table not there yet (pre-install).
        }

        if (! $setting || ! $setting->is_encrypted || ! $setting->value) {
            return false;
        }

        return ! EncryptedSettingValue::reveal($setting->value, '', true)->ok;
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

        $setting = Setting::where('group', $group)->where('key', $settingKey)->first();

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
                $value = Crypt::encryptString((string) $value);
            } catch (\Exception $e) {
                Log::critical('Failed to encrypt setting value', [
                    'group' => $group,
                    'key' => $settingKey,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        Setting::updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            ['value' => $value, 'is_encrypted' => $shouldEncrypt]
        );

        Log::info('Setting updated', ['group' => $group, 'key' => $settingKey, 'admin' => auth('admin')->id()]);

        $this->forget($group);
    }
}
