<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UpdateChecker (Module 21, Chunk 1.1) — Level 1 "is there a new version?" check.
 *
 * Reads the locally installed version from version.json, fetches the remote
 * release catalog (releases.json, falling back to version.json), compares with
 * SemVer, and resolves the sequential upgrade path (honouring each hop's
 * min_version_to_update_from). Result is cached; a forced check bypasses the
 * cache (manual "Check now"). Network failures degrade gracefully — never throw,
 * so a GitHub outage cannot break an admin page (rule #41 spirit).
 */
class UpdateChecker
{
    public const CACHE_KEY = 'oe_updates.status';

    /** The installed version, read from version.json at the app root. */
    public function currentVersion(): string
    {
        $path = base_path('version.json');

        if (! is_file($path)) {
            return 'unknown';
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? (string) ($data['version'] ?? 'unknown') : 'unknown';
    }

    /** The last cached result without triggering a network call, or null. */
    public function cached(): ?UpdateStatus
    {
        $status = Cache::get(self::CACHE_KEY);

        return $status instanceof UpdateStatus ? $status : null;
    }

    /**
     * Check for updates. Uses the cache unless $force is true.
     * 3-tier usage: scheduled command → check(true); lazy page load → check();
     * manual "Check now" → check(true).
     */
    public function check(bool $force = false): UpdateStatus
    {
        if (! config('updates.enabled', true)) {
            return new UpdateStatus(
                currentVersion: $this->currentVersion(),
                error: 'Updates are disabled.',
                checkedAt: now()->toIso8601String(),
            );
        }

        if ($force) {
            $this->forget();
        }

        $ttl = (int) config('updates.check.cache_ttl', 21600);

        return Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->performCheck());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function performCheck(): UpdateStatus
    {
        $current = $this->currentVersion();
        $channel = (string) config('updates.channel', 'stable');
        $now     = now()->toIso8601String();

        // Prefer the catalog (enables sequential-path resolution); fall back to
        // the single manifest.
        $catalog  = $this->fetch(config('updates.check.catalog_url'));
        $releases = is_array($catalog['releases'] ?? null) ? $catalog['releases'] : null;

        if ($releases === null) {
            $manifest = $this->fetch(config('updates.check.manifest_url'));

            if ($manifest === null) {
                return new UpdateStatus(
                    currentVersion: $current,
                    channel: $channel,
                    reachable: false,
                    error: 'Could not reach the update server.',
                    checkedAt: $now,
                );
            }

            $releases = [$manifest];
        }

        // Keep only well-formed releases for this channel.
        $releases = array_values(array_filter(
            $releases,
            fn ($r) => is_array($r)
                && ! empty($r['version'])
                && (($r['channel'] ?? $channel) === $channel)
        ));

        if ($releases === []) {
            return new UpdateStatus(
                currentVersion: $current,
                channel: $channel,
                reachable: true,
                error: 'No releases available for the "'.$channel.'" channel.',
                checkedAt: $now,
            );
        }

        // Ascending by version; latest = last.
        usort($releases, fn ($a, $b) => version_compare((string) $a['version'], (string) $b['version']));
        $latest        = end($releases);
        $latestVersion = (string) $latest['version'];

        $available = $current !== 'unknown'
            && version_compare($latestVersion, $current, '>');

        return new UpdateStatus(
            currentVersion: $current,
            latestVersion: $latestVersion,
            updateAvailable: $available,
            security: (bool) ($latest['security'] ?? false),
            channel: $channel,
            releaseDate: $latest['release_date'] ?? null,
            changelogUrl: $latest['changelog_url'] ?? null,
            downloadUrl: $latest['download_url'] ?? null,
            sha256: $latest['sha256'] ?? null,
            sizeBytes: isset($latest['size_bytes']) ? (int) $latest['size_bytes'] : null,
            migrationCount: (int) ($latest['migration_count'] ?? 0),
            minPhp: $latest['min_php'] ?? null,
            upgradePath: $available ? $this->resolveUpgradePath($current, $releases) : [],
            reachable: true,
            checkedAt: $now,
        );
    }

    /**
     * Walk from the current version to the latest, jumping as far as each hop's
     * min_version_to_update_from allows. Returns the ordered list of versions to
     * apply (usually just [latest]; multi-step only when a breaking hop forces it).
     *
     * @param  array<int,array<string,mixed>>  $releasesAsc  sorted ascending by version
     * @return string[]
     */
    private function resolveUpgradePath(string $current, array $releasesAsc): array
    {
        $path   = [];
        $cursor = $current;

        while (true) {
            $reachable = array_filter($releasesAsc, function ($r) use ($cursor) {
                $min = (string) ($r['min_version_to_update_from'] ?? '0.0.0');

                return version_compare((string) $r['version'], $cursor, '>')
                    && version_compare($min, $cursor, '<=');
            });

            if ($reachable === []) {
                break; // cannot proceed further from here
            }

            usort($reachable, fn ($a, $b) => version_compare((string) $a['version'], (string) $b['version']));
            $next   = end($reachable);
            $path[] = (string) $next['version'];
            $cursor = (string) $next['version'];
        }

        return $path;
    }

    /** Fetch + JSON-decode a URL, returning null on any failure (never throws). */
    private function fetch(?string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('updates.check.timeout', 10))
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->warning('Update check failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
    }
}
