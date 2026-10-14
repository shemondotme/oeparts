<?php

namespace Tests\Unit;

use App\Services\Migration\Transport\HostResolver;
use App\Services\RemoteImageDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

/**
 * DNS is answered by a fake resolver bound into the container, so these tests never depend on real network access. The URL
 * rules themselves (UrlPolicy) are tested in full in tests/Unit/Migration/Transport; here: that the downloader really uses
 * them, with the messages an admin sees.
 */
class RemoteImageDownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal but genuine 1x1 PNG. */
    private const TINY_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    private FakeHostResolver $dns;

    /** @param  array<string, list<list<string>>>|null  $answers */
    private function service(?array $answers = null): RemoteImageDownloadService
    {
        $this->dns = new FakeHostResolver($answers ?? ['*' => [['93.184.216.34']]]);
        $this->app->instance(HostResolver::class, $this->dns);

        return app(RemoteImageDownloadService::class);
    }

    #[Test]
    public function it_downloads_and_stores_a_valid_image(): void
    {
        Storage::fake('public');
        Http::fake(['scraped-site.test/*' => Http::response(self::TINY_PNG, 200, ['Content-Type' => 'image/png'])]);

        $path = $this->service()->downloadToProductImages('https://scraped-site.test/photo.png');

        $this->assertStringStartsWith('product-images/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function it_still_accepts_a_plain_http_image_link(): void
    {
        Storage::fake('public');
        Http::fake(['scraped-site.test/*' => Http::response(self::TINY_PNG, 200, ['Content-Type' => 'image/png'])]);

        $path = $this->service()->downloadToProductImages('http://scraped-site.test/photo.png');

        Storage::disk('public')->assertExists($path);
        Http::assertSent(fn ($request) => (string) $request->url() === 'http://scraped-site.test/photo.png');
    }

    #[Test]
    public function it_rejects_a_non_http_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid http:// or https://');

        $this->service()->downloadToProductImages('file:///etc/passwd');
    }

    /** @return array<string, array{string}> */
    public static function internalTargets(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/internal-api'],
            'private range' => ['http://192.168.1.50/photo.jpg'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/photo.jpg'],
            'ipv4 mapped in ipv6' => ['http://[::ffff:127.0.0.1]/photo.jpg'],
            'decimal ip' => ['http://2130706433/photo.jpg'],
            'hex ip' => ['http://0x7f000001/photo.jpg'],
            'short ip' => ['http://127.1/photo.jpg'],
            'userinfo trick' => ['http://scraped-site.test@127.0.0.1/photo.jpg'],
            'localhost' => ['http://localhost/photo.jpg'],
            'internal name' => ['http://db.internal/photo.jpg'],
            'a name that resolves to a private address' => ['http://sneaky.test/photo.jpg'],
            'a name with one private answer among public ones' => ['http://mixed.test/photo.jpg'],
            'a name whose only IPv6 answer is private' => ['http://v6only.test/photo.jpg'],
        ];
    }

    #[Test]
    #[DataProvider('internalTargets')]
    public function it_rejects_an_internal_target_without_making_a_request(string $url): void
    {
        Http::fake();
        $service = $this->service([
            'sneaky.test' => [['10.1.2.3']],
            'mixed.test' => [['93.184.216.34', '10.0.0.5']],
            'v6only.test' => [['fd00::5']],
            '*' => [['93.184.216.34']],
        ]);

        try {
            $service->downloadToProductImages($url);
            $this->fail('expected the URL to be refused');
        } catch (InvalidArgumentException) {
            Http::assertNothingSent();
            $this->addToAssertionCount(1);
        }
    }

    #[Test]
    public function a_private_address_gets_the_private_address_message(): void
    {
        $this->expectExceptionMessage('private or reserved address');

        $this->service()->downloadToProductImages('http://10.0.0.1/photo.jpg');
    }

    #[Test]
    public function a_dual_stack_host_with_a_private_ipv6_answer_is_rejected(): void
    {
        // the live gap: the old check looked at the IPv4 answer only
        Http::fake();
        $service = $this->service(['dual.test' => [['93.184.216.34', '::1']]]);

        try {
            $service->downloadToProductImages('https://dual.test/photo.jpg');
            $this->fail('expected refusal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('private or reserved', $e->getMessage());
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function a_name_that_does_not_resolve_says_so(): void
    {
        $this->expectExceptionMessage('Could not resolve');

        $this->service(['nowhere.test' => [[]]])->downloadToProductImages('https://nowhere.test/photo.jpg');
    }

    #[Test]
    public function the_name_is_resolved_once_so_a_rebinding_server_cannot_change_the_target(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(self::TINY_PNG, 200, ['Content-Type' => 'image/png'])]);
        // public on the first lookup, the metadata address on every later one
        $service = $this->service(['rebind.test' => [['93.184.216.34'], ['169.254.169.254']]]);

        $service->downloadToProductImages('https://rebind.test/photo.png');

        $this->assertSame(1, $this->dns->lookups['rebind.test']);
    }

    #[Test]
    public function it_refuses_to_follow_a_redirect(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/'])]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('redirects');

        $this->service()->downloadToProductImages('https://scraped-site.test/photo.png');
    }

    #[Test]
    public function it_rejects_a_non_2xx_response(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response('Not Found', 404)]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->downloadToProductImages('https://scraped-site.test/gone.png');
    }

    #[Test]
    public function it_rejects_a_response_that_is_not_actually_an_image(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->downloadToProductImages('https://scraped-site.test/page.html');
    }

    #[Test]
    public function it_rejects_a_response_larger_than_4mb(): void
    {
        Http::fake(['scraped-site.test/*' => Http::response(str_repeat('a', 4 * 1024 * 1024 + 1), 200, ['Content-Type' => 'image/jpeg'])]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('larger than 4MB');

        $this->service()->downloadToProductImages('https://scraped-site.test/huge.jpg');
    }
}
