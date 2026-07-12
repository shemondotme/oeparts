<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Configurability audit carry-forward (§5v): performance.cache_driver had
 * only one selectable option (Redis) and never gated anything (the active
 * driver is resolved from the CACHE_STORE env var at boot); performance.
 * query_cache_enabled/query_cache_ttl promised a query-result cache layer
 * that was never built; performance.cache_settings/cache_ttl_settings are
 * dead by design (SettingsService's own docblock: reading the settings()
 * cache TTL from settings() itself is a circular dependency, so it's
 * hardcoded); performance.queue_retry_after duplicated the real,
 * config-resolved-at-boot REDIS_QUEUE_RETRY_AFTER env var. All 6 were real,
 * saved, editable inputs with zero code-path consultation — retired outright
 * (no live replacement to carry a value into) in favor of Placeholder notes
 * on the settings page. Idempotent + reversible (rule #42); single-group op
 * (rule #44).
 */
return new class extends Migration
{
    private array $keys = [
        'cache_driver',
        'cache_settings',
        'cache_ttl_settings',
        'query_cache_enabled',
        'query_cache_ttl',
        'queue_retry_after',
    ];

    public function up(): void
    {
        $deleted = DB::table('settings')
            ->where('group', 'performance')
            ->whereIn('key', $this->keys)
            ->delete();

        if ($deleted > 0) {
            Cache::forget('settings.performance');
        }
    }

    public function down(): void
    {
        // Nothing safe to restore — these knobs never drove real behavior.
    }
};
