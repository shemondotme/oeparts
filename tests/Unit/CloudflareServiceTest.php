<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\CloudflareService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CloudflareService — purge-only, opt-in Cloudflare integration. Every
 * public method must no-op or return a clear "not configured" result when
 * performance.cloudflare_enabled is off or credentials are missing — the
 * app must work identically for every install that never touches this.
 */
class CloudflareServiceTest extends TestCase
{
    use RefreshDatabase;

    private function enable(string $zoneId = 'zone123', string $token = 'token456'): void
    {
        foreach ([
            'cloudflare_enabled' => ['true', 'boolean'],
            'cloudflare_zone_id' => [$zoneId, 'string'],
            'cloudflare_api_token' => [$token, 'encrypted'],
        ] as $key => [$value, $type]) {
            Setting::updateOrCreate(
                ['group' => 'performance', 'key' => $key],
                ['value' => $value, 'type' => $type, 'is_encrypted' => $type === 'encrypted']
            );
        }
        app(SettingsService::class)->forget('performance');
    }

    #[Test]
    public function it_is_not_configured_by_default(): void
    {
        $this->assertFalse(app(CloudflareService::class)->isConfigured());
    }

    #[Test]
    public function test_connection_reports_not_configured_without_making_a_request(): void
    {
        Http::fake();

        $result = app(CloudflareService::class)->testConnection();

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    #[Test]
    public function test_connection_succeeds_against_a_valid_zone(): void
    {
        $this->enable();
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'result' => ['name' => 'oeparts.example']]),
        ]);

        $result = app(CloudflareService::class)->testConnection();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('oeparts.example', $result['message']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token456')
            && str_contains($request->url(), '/zones/zone123'));
    }

    #[Test]
    public function test_connection_surfaces_a_cloudflare_error_message(): void
    {
        $this->enable();
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'Invalid API Token']]], 400),
        ]);

        $result = app(CloudflareService::class)->testConnection();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid API Token', $result['message']);
    }

    #[Test]
    public function purge_urls_is_a_silent_no_op_when_not_configured(): void
    {
        Http::fake();

        $result = app(CloudflareService::class)->purgeUrls(['https://example.com/sitemap.xml']);

        $this->assertTrue($result['success'], 'never blocks the caller (e.g. sitemap regeneration) just because Cloudflare is off');
        Http::assertNothingSent();
    }

    #[Test]
    public function purge_urls_sends_the_correct_payload(): void
    {
        $this->enable();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        $result = app(CloudflareService::class)->purgeUrls(['https://example.com/sitemap.xml', 'https://example.com/sitemap-parts.xml']);

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/purge_cache')
            && $request['files'] === ['https://example.com/sitemap.xml', 'https://example.com/sitemap-parts.xml']);
    }

    #[Test]
    public function purge_urls_chunks_more_than_thirty_urls_per_cloudflares_own_limit(): void
    {
        $this->enable();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        $urls = array_map(fn ($i) => "https://example.com/{$i}.xml", range(1, 45));
        app(CloudflareService::class)->purgeUrls($urls);

        Http::assertSentCount(2);
    }

    #[Test]
    public function purge_urls_with_an_empty_list_never_calls_the_api(): void
    {
        $this->enable();
        Http::fake();

        $result = app(CloudflareService::class)->purgeUrls([]);

        $this->assertTrue($result['success']);
        Http::assertNothingSent();
    }

    #[Test]
    public function purge_everything_sends_the_correct_payload(): void
    {
        $this->enable();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        $result = app(CloudflareService::class)->purgeEverything();

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/purge_cache')
            && ($request['purge_everything'] ?? false) === true);
    }

    #[Test]
    public function a_network_failure_is_reported_not_thrown(): void
    {
        $this->enable();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $result = app(CloudflareService::class)->purgeEverything();

        $this->assertFalse($result['success']);
    }
}
