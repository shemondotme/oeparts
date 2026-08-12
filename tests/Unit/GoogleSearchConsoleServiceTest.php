<?php

namespace Tests\Unit;

use App\Services\GoogleSearchConsoleService;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleSearchConsoleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    private function configure(): void
    {
        $service = app(SettingsService::class);
        $service->set('seo.gsc_client_id', 'client-123');
        $service->set('seo.gsc_client_secret', 'secret-abc');
        $service->set('seo.gsc_refresh_token', 'refresh-xyz');
        $service->set('seo.gsc_property_url', 'https://oeparts.test/');
    }

    #[Test]
    public function it_reports_unconfigured_when_any_credential_is_missing(): void
    {
        $this->assertFalse(app(GoogleSearchConsoleService::class)->isConfigured());
    }

    #[Test]
    public function it_reports_configured_once_all_four_credentials_are_set(): void
    {
        $this->configure();

        $this->assertTrue(app(GoogleSearchConsoleService::class)->isConfigured());
    }

    #[Test]
    public function it_aggregates_submitted_indexed_errors_and_warnings_across_sitemaps(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599], 200),
            'searchconsole.googleapis.com/*' => Http::response([
                'sitemap' => [
                    [
                        'path' => 'https://oeparts.test/sitemap.xml',
                        'errors' => 1,
                        'warnings' => 2,
                        'contents' => [
                            ['type' => 'web', 'submitted' => 100, 'indexed' => 80],
                        ],
                    ],
                    [
                        'path' => 'https://oeparts.test/sitemap-crossrefs.xml',
                        'errors' => 0,
                        'warnings' => 1,
                        'contents' => [
                            ['type' => 'web', 'submitted' => 50, 'indexed' => 40],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $summary = app(GoogleSearchConsoleService::class)->getSitemapSummary();

        $this->assertSame(150, $summary['submitted']);
        $this->assertSame(120, $summary['indexed']);
        $this->assertSame(1, $summary['errors']);
        $this->assertSame(3, $summary['warnings']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com')
            && $request['refresh_token'] === 'refresh-xyz');
    }

    #[Test]
    public function a_failed_token_refresh_returns_an_error_instead_of_throwing(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $summary = app(GoogleSearchConsoleService::class)->getSitemapSummary();

        $this->assertArrayHasKey('error', $summary);
    }

    #[Test]
    public function a_failed_sitemaps_call_returns_an_error_instead_of_throwing(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            'searchconsole.googleapis.com/*' => Http::response([], 403),
        ]);

        $summary = app(GoogleSearchConsoleService::class)->getSitemapSummary();

        $this->assertArrayHasKey('error', $summary);
    }
}
