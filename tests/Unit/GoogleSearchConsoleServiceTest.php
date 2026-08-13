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

    #[Test]
    public function it_reports_totals_and_top_queries_and_pages(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            '*searchAnalytics/query*' => Http::sequence()
                ->push(['rows' => [['clicks' => 42, 'impressions' => 1000, 'ctr' => 0.042, 'position' => 8.3]]], 200)
                ->push(['rows' => [
                    ['keys' => ['bosch brake pad'], 'clicks' => 20, 'impressions' => 400, 'ctr' => 0.05, 'position' => 5.1],
                    ['keys' => ['06l906036l'], 'clicks' => 15, 'impressions' => 300, 'ctr' => 0.05, 'position' => 3.2],
                ]], 200)
                ->push(['rows' => [
                    ['keys' => ['https://oeparts.test/en/parts/06L906036L'], 'clicks' => 15, 'impressions' => 300],
                ]], 200),
        ]);

        $result = app(GoogleSearchConsoleService::class)->getSearchAnalytics();

        $this->assertSame(42, $result['totalClicks']);
        $this->assertSame(1000, $result['totalImpressions']);
        $this->assertEqualsWithDelta(0.042, $result['avgCtr'], 0.0001);
        $this->assertSame(8.3, $result['avgPosition']);
        $this->assertCount(2, $result['topQueries']);
        $this->assertSame('bosch brake pad', $result['topQueries'][0]['query']);
        $this->assertCount(1, $result['topPages']);
        $this->assertSame('https://oeparts.test/en/parts/06L906036L', $result['topPages'][0]['page']);
    }

    #[Test]
    public function it_still_reports_totals_when_the_query_breakdown_call_fails(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            '*searchAnalytics/query*' => Http::sequence()
                ->push(['rows' => [['clicks' => 42, 'impressions' => 1000, 'ctr' => 0.042, 'position' => 8.3]]], 200)
                ->push([], 500)
                ->push([], 500),
        ]);

        $result = app(GoogleSearchConsoleService::class)->getSearchAnalytics();

        $this->assertSame(42, $result['totalClicks']);
        $this->assertSame([], $result['topQueries']);
        $this->assertSame([], $result['topPages']);
    }

    #[Test]
    public function a_failed_totals_call_returns_an_error_instead_of_throwing(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            '*searchAnalytics/query*' => Http::response([], 403),
        ]);

        $result = app(GoogleSearchConsoleService::class)->getSearchAnalytics();

        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function it_inspects_a_url_and_reports_the_index_verdict(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            '*urlInspection/index:inspect*' => Http::response([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'verdict' => 'PASS',
                        'coverageState' => 'Submitted and indexed',
                        'robotsTxtState' => 'ALLOWED',
                        'indexingState' => 'INDEXING_ALLOWED',
                        'lastCrawlTime' => '2026-08-01T00:00:00Z',
                    ],
                ],
            ], 200),
        ]);

        $result = app(GoogleSearchConsoleService::class)->inspectUrl('https://oeparts.test/en');

        $this->assertSame('PASS', $result['verdict']);
        $this->assertSame('Submitted and indexed', $result['coverageState']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'urlInspection/index:inspect')
            && $request['inspectionUrl'] === 'https://oeparts.test/en');
    }

    #[Test]
    public function a_failed_url_inspection_call_returns_an_error_instead_of_throwing(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            '*urlInspection/index:inspect*' => Http::response([], 403),
        ]);

        $result = app(GoogleSearchConsoleService::class)->inspectUrl('https://oeparts.test/en');

        $this->assertArrayHasKey('error', $result);
    }
}
