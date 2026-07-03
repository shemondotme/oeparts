<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * oeparts:release:manifest command (Module 21, Chunk 5.2) — end-to-end over temp files:
 * folds dist/build-result.json into version.json and upserts releases.json.
 */
class GenerateReleaseManifestCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-relman-'.getmypid();
        @mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function writeJson(string $name, array $data): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, json_encode($data));

        return $path;
    }

    #[Test]
    public function it_folds_the_build_result_and_upserts_the_catalog(): void
    {
        $version = $this->writeJson('version.json', [
            'version' => '1.2.0', 'channel' => 'stable', 'release_date' => '2026-07-03',
            'min_version_to_update_from' => '1.1.0', 'migration_count' => 2,
            'sha256' => null, 'size_bytes' => null,
        ]);
        $catalog = $this->writeJson('releases.json', [
            'channel' => 'stable', 'latest' => '1.1.0',
            'releases' => [['version' => '1.1.0', 'sha256' => 'prev']],
        ]);
        $build = $this->writeJson('build-result.json', [
            'version' => '1.2.0', 'sha256' => str_repeat('c', 64), 'size_bytes' => 8192,
        ]);

        $this->artisan('oeparts:release:manifest', [
            '--version-file' => $version,
            '--catalog-file' => $catalog,
            '--build-result' => $build,
        ])->assertExitCode(0);

        $v = json_decode(file_get_contents($version), true);
        $this->assertSame(str_repeat('c', 64), $v['sha256']);
        $this->assertSame(8192, $v['size_bytes']);
        $this->assertStringContainsString('1.2.0', $v['download_url']);

        $c = json_decode(file_get_contents($catalog), true);
        $this->assertSame('1.2.0', $c['latest']);
        $this->assertSame('1.2.0', $c['releases'][0]['version']);
        $this->assertCount(2, $c['releases']);
    }

    #[Test]
    public function it_fails_on_a_version_mismatch(): void
    {
        $version = $this->writeJson('version.json', ['version' => '1.2.0']);
        $build = $this->writeJson('build-result.json', ['version' => '1.3.0', 'sha256' => 'x', 'size_bytes' => 1]);

        $this->artisan('oeparts:release:manifest', [
            '--version-file' => $version,
            '--catalog-file' => $this->dir.'/releases.json',
            '--build-result' => $build,
        ])->assertExitCode(1);
    }
}
