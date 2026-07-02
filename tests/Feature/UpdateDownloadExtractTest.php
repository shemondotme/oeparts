<?php

namespace Tests\Feature;

use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\UpdateDownloader;
use App\Services\Updates\UpdateExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update download + staging extract (Module 21, Chunk 3.2). Resumable, verified
 * download and a zip-slip-guarded extraction into staging (no swap yet).
 */
class UpdateDownloadExtractTest extends TestCase
{
    use RefreshDatabase;

    private string $state;
    private const URL = 'https://releases.test/oeparts.zip';

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-dl-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);
        config(['updates.download.backoff' => [0, 0, 0, 0]]);
        config(['updates.download.verify_sha256' => true]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->state);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir.'/'.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function manifest(string $body, array $overrides = []): array
    {
        return array_merge([
            'version'      => '1.1.0',
            'download_url' => self::URL,
            'sha256'       => hash('sha256', $body),
            'size_bytes'   => strlen($body),
        ], $overrides);
    }

    private function makeZip(array $entries): string
    {
        $path = $this->state.'/fixture-'.uniqid().'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    /* ---- Download ------------------------------------------------------- */

    #[Test]
    public function it_downloads_and_verifies_the_archive(): void
    {
        $body = random_bytes(3000);
        Http::fake([self::URL => Http::response($body, 200)]);

        $path = app(UpdateDownloader::class)->download($this->manifest($body));

        $this->assertSame($body, file_get_contents($path));
    }

    #[Test]
    public function it_rejects_a_checksum_mismatch_and_deletes_the_file(): void
    {
        config(['updates.download.retries' => 0]); // a checksum failure is deterministic
        Http::fake([self::URL => Http::response('tampered payload', 200)]);
        $manifest = $this->manifest('tampered payload', ['sha256' => str_repeat('0', 64)]);

        try {
            app(UpdateDownloader::class)->download($manifest);
            $this->fail('Expected a checksum failure.');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('sha256', $e->getMessage());
        }

        $this->assertFileDoesNotExist(app(UpdateDownloader::class)->downloadPath('1.1.0'));
    }

    #[Test]
    public function it_retries_a_transient_failure_then_succeeds(): void
    {
        $body = random_bytes(1500);
        Http::fake([self::URL => Http::sequence()
            ->push('', 500)
            ->push($body, 200),
        ]);

        $path = app(UpdateDownloader::class)->download($this->manifest($body));

        $this->assertSame($body, file_get_contents($path));
    }

    #[Test]
    public function it_resumes_from_a_partial_download(): void
    {
        $body = random_bytes(4000);
        $downloader = app(UpdateDownloader::class);
        $path = $downloader->downloadPath('1.1.0');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, substr($body, 0, 2500)); // a killed download left 2500 bytes

        // The server honours the Range request with 206 + the remaining bytes.
        Http::fake([self::URL => Http::response(substr($body, 2500), 206)]);

        $result = $downloader->download($this->manifest($body));

        $this->assertSame($body, file_get_contents($result));
    }

    /* ---- Extract -------------------------------------------------------- */

    #[Test]
    public function it_extracts_the_archive_into_staging(): void
    {
        $zip = $this->makeZip(['app/Foo.php' => '<?php // foo', 'version.json' => '{"version":"1.1.0"}']);

        $dir = app(UpdateExtractor::class)->extract($zip, '1.1.0');

        $this->assertSame('<?php // foo', file_get_contents($dir.'/app/Foo.php'));
        $this->assertSame('{"version":"1.1.0"}', file_get_contents($dir.'/version.json'));
    }

    #[Test]
    public function it_blocks_zip_slip_traversal(): void
    {
        $zip = $this->makeZip(['../evil.txt' => 'pwned']);

        $this->expectException(UpdateException::class);
        app(UpdateExtractor::class)->extract($zip, 'malicious');
    }

    #[Test]
    public function re_extracting_replaces_the_previous_staging(): void
    {
        $extractor = app(UpdateExtractor::class);

        $dir = $extractor->extract($this->makeZip(['old.txt' => 'v1']), '1.1.0');
        $this->assertFileExists($dir.'/old.txt');

        $extractor->extract($this->makeZip(['new.txt' => 'v2']), '1.1.0');
        $this->assertFileDoesNotExist($dir.'/old.txt', 'stale staging must be cleared');
        $this->assertFileExists($dir.'/new.txt');
    }
}
