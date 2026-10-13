<?php

namespace App\Services\Instance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Clears the application caches of a site that has just been moved in, without ever flushing a store that
 * other sites may share.
 *
 * Flushing Redis or Memcached empties the WHOLE server, not just this site's prefix, so it would silently
 * wipe the cache and sessions of every other application using it. Those stores are therefore left alone and
 * reported as skipped: a fresh cache prefix (written when the copy is armed) already gives this site a clean
 * view of them. Stores that belong to this installation alone (file, array, database) are cleared.
 */
final class InstanceCacheReset
{
    /** Stores that live on this installation alone. */
    private const OWN_DRIVERS = ['file', 'array', 'database', 'null'];

    /**
     * @return array{cleared: array<int, string>, skipped: array<int, array{store: string, reason: string}>}
     */
    public function reset(): array
    {
        $cleared = [];
        $skipped = [];

        foreach ((array) config('cache.stores', []) as $name => $store) {
            $driver = (string) ($store['driver'] ?? '');

            if (! in_array($driver, self::OWN_DRIVERS, true)) {
                $skipped[] = ['store' => (string) $name, 'reason' => "a {$driver} store can be shared with other sites; it is never flushed"];

                continue;
            }

            if ($driver === 'database' && ! $this->tableExists((string) ($store['table'] ?? 'cache'), $store['connection'] ?? null)) {
                continue;
            }

            try {
                Cache::store((string) $name)->flush();
                $cleared[] = (string) $name;
            } catch (\Throwable $e) {
                Log::warning("Could not clear the {$name} cache store: ".$e->getMessage());
                $skipped[] = ['store' => (string) $name, 'reason' => 'it could not be cleared: '.$e->getMessage()];
            }
        }

        return ['cleared' => $cleared, 'skipped' => $skipped];
    }

    private function tableExists(string $table, ?string $connection): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
