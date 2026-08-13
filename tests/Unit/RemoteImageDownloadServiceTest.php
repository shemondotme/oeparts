<?php

namespace Tests\Unit;

use App\Services\RemoteImageDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DNS resolution (resolveHost()) is stubbed via a partial mock in the
 * happy-path/reachable-host cases so these tests never depend on real
 * network access — the SSRF-rejection cases instead use literal IP
 * addresses in the URL, which the service never needs to resolve at all.
 */
class RemoteImageDownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal but genuine 1x1 PNG. */
    private const TINY_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    private function serviceWithResolvedHost(string $ip): RemoteImageDownloadService
    {
        $service = \Mockery::mock(RemoteImageDownloadService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveHost')->andReturn($ip);

        return $service;
    }

    #[Test]
    public function it_downloads_and_stores_a_valid_image(): void
    {
        Storage::fake('public');
        Http::fake(['scraped-site.test/*' => Http::response(self::TINY_PNG, 200, ['Content-Type' => 'image/png'])]);

        $service = $this->serviceWithResolvedHost('93.184.216.34');

        $path = $service->downloadToProductImages('https://scraped-site.test/photo.png');

        $this->assertStringStartsWith('product-images/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function it_rejects_a_non_http_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(RemoteImageDownloadService::class)->downloadToProductImages('file:///etc/passwd');
    }

    #[Test]
    public function it_rejects_a_loopback_address_without_making_a_request(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(RemoteImageDownloadService::class)->downloadToProductImages('http://127.0.0.1/internal-api');
        } finally {
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function it_rejects_a_private_range_address(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(RemoteImageDownloadService::class)->downloadToProductImages('http://192.168.1.50/photo.jpg');
        } finally {
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function it_rejects_the_cloud_metadata_address(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(RemoteImageDownloadService::class)->downloadToProductImages('http://169.254.169.254/latest/meta-data/');
        } finally {
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function it_refuses_to_follow_a_redirect(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/'])]);

        $service = $this->serviceWithResolvedHost('93.184.216.34');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('redirects');

        $service->downloadToProductImages('https://scraped-site.test/photo.png');
    }

    #[Test]
    public function it_rejects_a_non_2xx_response(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response('Not Found', 404)]);

        $service = $this->serviceWithResolvedHost('93.184.216.34');

        $this->expectException(InvalidArgumentException::class);

        $service->downloadToProductImages('https://scraped-site.test/gone.png');
    }

    #[Test]
    public function it_rejects_a_response_that_is_not_actually_an_image(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html'])]);

        $service = $this->serviceWithResolvedHost('93.184.216.34');

        $this->expectException(InvalidArgumentException::class);

        $service->downloadToProductImages('https://scraped-site.test/page.html');
    }

    #[Test]
    public function it_rejects_a_response_larger_than_4mb(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response(str_repeat('a', 4 * 1024 * 1024 + 1), 200, ['Content-Type' => 'image/jpeg'])]);

        $service = $this->serviceWithResolvedHost('93.184.216.34');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('larger than 4MB');

        $service->downloadToProductImages('https://scraped-site.test/huge.jpg');
    }
}
