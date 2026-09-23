<?php

namespace App\Support;

/**
 * A cheap, per-request identifier for "which deployed build is this?" —
 * combines version.json's version (bumps on a full self-update release)
 * with the Vite manifest's mtime+size (bumps on an asset-only redeploy
 * that doesn't go through the versioned release flow). Used to detect
 * a deploy that happened while a tab was open (see resources/js/build-
 * freshness.js and filament.hooks.build-freshness-check) and to seed
 * Livewire's release_token so stale admin tabs get a clean 419 instead
 * of a corrupt-payload error.
 *
 * Deliberately not cached via Cache::remember() — config/updates.php's
 * post-swap step does not guarantee the cache store gets flushed, so a
 * cached value could stay stale indefinitely. Memoized per-request only.
 */
final class AppBuildId
{
    private static ?string $cached = null;

    public static function current(): string
    {
        return self::$cached ??= self::compute();
    }

    private static function compute(): string
    {
        $manifest = public_path('build/manifest.json');

        $assetTag = is_file($manifest)
            ? substr(sha1((string) @filemtime($manifest).'|'.(string) @filesize($manifest)), 0, 8)
            : '0';

        return self::readVersion().'+'.$assetTag;
    }

    private static function readVersion(): string
    {
        $path = base_path('version.json');
        if (! file_exists($path)) {
            return 'unknown';
        }
        $data = json_decode(file_get_contents($path), true);

        return $data['version'] ?? 'unknown';
    }
}
