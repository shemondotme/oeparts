<?php

namespace App\Services\Instance;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Makes a change that was just written to .env true in THIS process too. A running request or command read
 * .env once, at boot, so without this the code that follows would still see the old value (the new public
 * disk URL, the new mode, the new cache prefix) until the next request.
 *
 * It only applies what InstanceEnv is allowed to write. Workers are a separate matter: they are told to
 * restart by InstanceEnv, before anything is written.
 */
final class ConfigReloader
{
    /**
     * @param  array<string, string>  $values  the .env keys that changed, with their new values
     * @return array<int, string> what was reloaded, for the report
     */
    public function apply(array $values): array
    {
        $done = [];

        if (array_key_exists('APP_URL', $values)) {
            $url = rtrim($values['APP_URL'], '/');

            config(['app.url' => $url, 'filesystems.disks.public.url' => $url.'/storage']);
            Storage::forgetDisk('public');

            if (app()->runningInConsole() && $url !== '') {
                URL::forceRootUrl($url);
            }

            $done[] = 'app.url';
        }

        foreach (['OE_MODE' => 'instance.mode', 'OE_INSTANCE_ID' => 'instance.id', 'OE_MAIL_POLICY' => 'instance.mail.policy', 'OE_MAIL_ALLOWLIST' => 'instance.mail.allowlist'] as $key => $config) {
            if (array_key_exists($key, $values)) {
                config([$config => $values[$key] === '' ? null : $values[$key]]);
                $done[] = $config;
            }
        }

        if (array_key_exists('OE_NONPROD_HTTP_CRON', $values)) {
            config(['instance.nonprod_http_cron' => filter_var($values['OE_NONPROD_HTTP_CRON'], FILTER_VALIDATE_BOOLEAN)]);
            $done[] = 'instance.nonprod_http_cron';
        }

        if (array_key_exists('CRON_FALLBACK_ENABLED', $values)) {
            config(['app.cron_fallback_enabled' => filter_var($values['CRON_FALLBACK_ENABLED'], FILTER_VALIDATE_BOOLEAN)]);
            $done[] = 'app.cron_fallback_enabled';
        }

        $prefixChanged = false;

        if (array_key_exists('CACHE_PREFIX', $values)) {
            config(['cache.prefix' => $values['CACHE_PREFIX']]);
            $done[] = 'cache.prefix';
            $prefixChanged = true;
        }

        if (array_key_exists('REDIS_PREFIX', $values)) {
            config(['database.redis.options.prefix' => $values['REDIS_PREFIX']]);
            $done[] = 'database.redis.options.prefix';
            $prefixChanged = true;
        }

        if ($prefixChanged) {
            $this->dropResolvedStores();
        }

        $this->clearConfigCache();

        return $done;
    }

    /** Stores and connections already built keep the old prefix for the rest of the process. */
    private function dropResolvedStores(): void
    {
        try {
            app('cache')->forgetDriver();
            app('cache')->forgetDriver((string) config('cache.default'));
        } catch (\Throwable $e) {
            Log::warning('Could not drop the resolved cache store after a prefix change: '.$e->getMessage());
        }

        try {
            if (app()->bound('redis')) {
                app('redis')->purge();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not drop the resolved Redis connection after a prefix change: '.$e->getMessage());
        }
    }

    /** A cached config would put the old values back on the next request. */
    private function clearConfigCache(): void
    {
        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            Log::warning('config:clear failed after an instance .env change: '.$e->getMessage());
        }
    }
}
