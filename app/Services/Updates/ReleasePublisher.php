<?php

namespace App\Services\Updates;

/**
 * ReleasePublisher (Module 21, Chunk 5.2) — turns a built zip into the served
 * release metadata: it folds the build result (sha256, size, download URL) into the
 * release manifest (version.json) and upserts the ordered catalog (releases.json)
 * the in-app updater fetches to resolve a sequential upgrade path.
 *
 * Pure transformation (no I/O, no app boot) so it's fully unit-tested; the
 * `oeparts:release:manifest` command handles reading/writing the files, and the
 * GitHub Actions workflow (`.github/workflows/release.yml`) runs it on a v*.*.* tag.
 */
class ReleasePublisher
{
    private string $urlTemplate;

    private string $assetName;

    /** @param array<string,mixed>|null $config defaults to config('updates.build') */
    public function __construct(?array $config = null)
    {
        $config ??= (array) config('updates.build', []);
        $this->urlTemplate = (string) ($config['release_url_template']
            ?? 'https://github.com/oeparts/oeparts/releases/download/v{version}/{asset}');
        $this->assetName = (string) ($config['asset_name'] ?? 'oeparts-{version}.zip');
    }

    /**
     * Fold the zip's build result into the release manifest.
     *
     * @param  array<string,mixed>  $manifest  the release's version.json (declared fields)
     * @param  array<string,mixed>  $build     { version, sha256, size_bytes, download_url? }
     * @return array<string,mixed>  the completed manifest
     */
    public function finalize(array $manifest, array $build): array
    {
        $version = (string) ($manifest['version'] ?? '');

        if (isset($build['sha256'])) {
            $manifest['sha256'] = (string) $build['sha256'];
        }
        if (isset($build['size_bytes'])) {
            $manifest['size_bytes'] = (int) $build['size_bytes'];
        }

        $manifest['download_url'] = (string) ($build['download_url'] ?? $this->downloadUrl($version));

        return $manifest;
    }

    /** The versioned download URL for a release, from the config template. */
    public function downloadUrl(string $version): string
    {
        $asset = str_replace('{version}', $version, $this->assetName);

        return str_replace(['{version}', '{asset}'], [$version, $asset], $this->urlTemplate);
    }

    /**
     * Project a full manifest into the compact catalog entry the updater reads
     * (see UpdateChecker::performCheck / resolveUpgradePath for the consumed fields).
     *
     * @param  array<string,mixed>  $manifest
     * @return array<string,mixed>
     */
    public function toCatalogEntry(array $manifest): array
    {
        return [
            'version'                    => $manifest['version'] ?? null,
            'codename'                   => $manifest['codename'] ?? null,
            'release_date'               => $manifest['release_date'] ?? null,
            'channel'                    => $manifest['channel'] ?? 'stable',
            'security'                   => (bool) ($manifest['security'] ?? false),
            'min_php'                    => $manifest['min_php'] ?? null,
            'min_mysql'                  => $manifest['min_mysql'] ?? null,
            'min_version_to_update_from' => $manifest['min_version_to_update_from'] ?? '0.0.0',
            'migration_count'            => (int) ($manifest['migration_count'] ?? 0),
            'sha256'                     => $manifest['sha256'] ?? null,
            'size_bytes'                 => isset($manifest['size_bytes']) ? (int) $manifest['size_bytes'] : null,
            'signature'                  => $manifest['signature'] ?? null,
            'changelog_url'              => $manifest['changelog_url'] ?? null,
            'download_url'               => $manifest['download_url'] ?? null,
        ];
    }

    /**
     * Insert or replace this release in the catalog, keeping releases newest-first
     * and `latest` pointing at the highest version. Idempotent for a given version.
     *
     * @param  array<string,mixed>  $catalog   existing releases.json (or a fresh skeleton)
     * @param  array<string,mixed>  $manifest  the finalized release manifest
     * @return array<string,mixed>  the updated catalog
     */
    public function upsert(array $catalog, array $manifest): array
    {
        $entry   = $this->toCatalogEntry($manifest);
        $version = (string) ($entry['version'] ?? '');

        $releases = array_values(array_filter(
            (array) ($catalog['releases'] ?? []),
            fn ($r) => is_array($r) && (string) ($r['version'] ?? '') !== $version
        ));

        $releases[] = $entry;

        // Newest first.
        usort($releases, fn ($a, $b) => version_compare((string) $b['version'], (string) $a['version']));

        $catalog['channel']  = $catalog['channel'] ?? ($manifest['channel'] ?? 'stable');
        $catalog['latest']   = (string) $releases[0]['version'];
        $catalog['releases'] = $releases;

        return $catalog;
    }
}
