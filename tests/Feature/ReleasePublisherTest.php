<?php

namespace Tests\Feature;

use App\Services\Updates\ReleasePublisher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Release manifest + catalog generation (Module 21, Chunk 5.2). Pure transformation:
 * folds the build result into version.json and upserts the releases.json catalog the
 * in-app updater reads (fields must match UpdateChecker's expectations).
 */
class ReleasePublisherTest extends TestCase
{
    private function publisher(): ReleasePublisher
    {
        return new ReleasePublisher([
            'release_url_template' => 'https://example.test/releases/download/v{version}/{asset}',
            'asset_name'           => 'oeparts-{version}.zip',
        ]);
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'version'                    => '1.1.0',
            'codename'                   => 'Borealis',
            'release_date'               => '2026-07-03',
            'channel'                    => 'stable',
            'security'                   => false,
            'min_php'                    => '8.2',
            'min_mysql'                  => '8.0.16',
            'min_version_to_update_from' => '1.0.0',
            'migration_count'            => 3,
            'required_extensions'        => ['json', 'zip'],
            'sha256'                     => null,
            'size_bytes'                 => null,
            'download_url'               => 'https://github.com/.../latest/oeparts.zip',
            'changelog_url'              => 'https://example.test/CHANGELOG.md',
        ], $overrides);
    }

    #[Test]
    public function finalize_folds_the_build_result_into_the_manifest(): void
    {
        $final = $this->publisher()->finalize($this->manifest(), [
            'version'    => '1.1.0',
            'sha256'     => str_repeat('a', 64),
            'size_bytes' => 4096,
        ]);

        $this->assertSame(str_repeat('a', 64), $final['sha256']);
        $this->assertSame(4096, $final['size_bytes']);
        // download_url derived from the template (versioned, not /latest/).
        $this->assertSame('https://example.test/releases/download/v1.1.0/oeparts-1.1.0.zip', $final['download_url']);
    }

    #[Test]
    public function finalize_prefers_an_explicit_download_url_from_the_build(): void
    {
        $final = $this->publisher()->finalize($this->manifest(), [
            'version'      => '1.1.0',
            'sha256'       => str_repeat('b', 64),
            'size_bytes'   => 10,
            'download_url' => 'https://mirror.test/oeparts-1.1.0.zip',
        ]);

        $this->assertSame('https://mirror.test/oeparts-1.1.0.zip', $final['download_url']);
    }

    #[Test]
    public function catalog_entry_projects_only_the_consumed_fields(): void
    {
        $entry = $this->publisher()->toCatalogEntry($this->manifest(['sha256' => 'x', 'size_bytes' => 5]));

        $this->assertSame('1.1.0', $entry['version']);
        $this->assertSame(3, $entry['migration_count']);
        $this->assertSame(5, $entry['size_bytes']);
        // Heavy manifest-only fields are not carried into the compact catalog entry.
        $this->assertArrayNotHasKey('required_extensions', $entry);
        $this->assertArrayNotHasKey('post_update_notes', $entry);
    }

    #[Test]
    public function upsert_adds_replaces_sorts_and_tracks_latest(): void
    {
        $publisher = $this->publisher();
        $catalog = ['channel' => 'stable', 'latest' => '1.0.0', 'releases' => [
            ['version' => '1.0.0', 'sha256' => 'old'],
        ]];

        // Add a newer release.
        $catalog = $publisher->upsert($catalog, $this->manifest(['version' => '1.1.0']));
        $this->assertCount(2, $catalog['releases']);
        $this->assertSame('1.1.0', $catalog['latest']);
        $this->assertSame('1.1.0', $catalog['releases'][0]['version'], 'newest first');
        $this->assertSame('1.0.0', $catalog['releases'][1]['version']);

        // Re-publishing the same version replaces (no duplicate).
        $catalog = $publisher->upsert($catalog, $this->manifest(['version' => '1.1.0', 'sha256' => 'new', 'size_bytes' => 9]));
        $this->assertCount(2, $catalog['releases']);
        $this->assertSame('new', $catalog['releases'][0]['sha256']);
    }
}
