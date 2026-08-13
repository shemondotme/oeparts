<?php

namespace Tests\Unit;

use App\Models\CoreWebVitalsSnapshot;
use App\Services\CoreWebVitalsService;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoreWebVitalsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    private function configure(): void
    {
        app(SettingsService::class)->set('seo.crux_api_key', 'crux-key-123');
    }

    #[Test]
    public function it_reports_unconfigured_without_an_api_key(): void
    {
        $this->assertFalse(app(CoreWebVitalsService::class)->isConfigured());
    }

    #[Test]
    public function it_parses_p75_lcp_cls_and_inp_from_a_real_shaped_response(): void
    {
        $this->configure();

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response([
                'record' => [
                    'metrics' => [
                        'largest_contentful_paint' => ['percentiles' => ['p75' => 1800]],
                        'cumulative_layout_shift' => ['percentiles' => ['p75' => '0.05']],
                        'interaction_to_next_paint' => ['percentiles' => ['p75' => 150]],
                    ],
                ],
            ], 200),
        ]);

        $metrics = app(CoreWebVitalsService::class)->getMetrics();

        $this->assertSame(1800, $metrics['lcp_ms']);
        $this->assertSame(0.05, $metrics['cls']);
        $this->assertSame(150, $metrics['inp_ms']);
    }

    #[Test]
    public function a_404_response_is_reported_as_insufficient_data_not_an_error(): void
    {
        $this->configure();

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response(['error' => ['code' => 404]], 404),
        ]);

        $metrics = app(CoreWebVitalsService::class)->getMetrics();

        $this->assertTrue($metrics['insufficientData']);
    }

    #[Test]
    public function a_non_404_failure_is_reported_as_an_error_instead_of_throwing(): void
    {
        $this->configure();

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response([], 403),
        ]);

        $metrics = app(CoreWebVitalsService::class)->getMetrics();

        $this->assertArrayHasKey('error', $metrics);
    }

    #[Test]
    public function lcp_rating_reflects_the_2026_tightened_2_second_good_threshold(): void
    {
        $service = app(CoreWebVitalsService::class);

        $this->assertSame('good', $service->lcpRating(2000));
        $this->assertSame('needs-improvement', $service->lcpRating(3000));
        $this->assertSame('poor', $service->lcpRating(4001));
        $this->assertNull($service->lcpRating(null));
    }

    #[Test]
    public function cls_and_inp_ratings_use_their_own_thresholds(): void
    {
        $service = app(CoreWebVitalsService::class);

        $this->assertSame('good', $service->clsRating(0.05));
        $this->assertSame('poor', $service->clsRating(0.3));

        $this->assertSame('good', $service->inpRating(150));
        $this->assertSame('poor', $service->inpRating(600));
    }

    #[Test]
    public function snapshot_records_a_row_with_ratings_when_configured_and_data_exists(): void
    {
        $this->configure();

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response([
                'record' => [
                    'metrics' => [
                        'largest_contentful_paint' => ['percentiles' => ['p75' => 1800]],
                        'cumulative_layout_shift' => ['percentiles' => ['p75' => '0.05']],
                        'interaction_to_next_paint' => ['percentiles' => ['p75' => 150]],
                    ],
                ],
            ], 200),
        ]);

        app(CoreWebVitalsService::class)->snapshot();

        $this->assertSame(1, CoreWebVitalsSnapshot::count());
        $snapshot = CoreWebVitalsSnapshot::first();
        $this->assertSame(1800, $snapshot->lcp_ms);
        $this->assertSame('good', $snapshot->lcp_rating);
    }

    #[Test]
    public function snapshot_does_not_record_a_row_when_unconfigured(): void
    {
        app(CoreWebVitalsService::class)->snapshot();

        $this->assertSame(0, CoreWebVitalsSnapshot::count());
    }

    #[Test]
    public function snapshot_does_not_record_a_row_on_insufficient_data(): void
    {
        $this->configure();

        Http::fake(['chromeuxreport.googleapis.com/*' => Http::response([], 404)]);

        app(CoreWebVitalsService::class)->snapshot();

        $this->assertSame(0, CoreWebVitalsSnapshot::count());
    }

    #[Test]
    public function a_second_snapshot_within_the_throttle_window_does_not_duplicate(): void
    {
        $this->configure();

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response([
                'record' => ['metrics' => ['largest_contentful_paint' => ['percentiles' => ['p75' => 1800]]]],
            ], 200),
        ]);

        app(CoreWebVitalsService::class)->snapshot();
        app(CoreWebVitalsService::class)->snapshot();

        $this->assertSame(1, CoreWebVitalsSnapshot::count());
    }
}
