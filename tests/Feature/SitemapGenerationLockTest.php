<?php

namespace Tests\Feature;

use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 16 (Scheduled Task/Cron Reliability). Two independent triggers call
 * SitemapService::generateAll() with no coordination between them — the
 * daily sitemap:generate schedule and the SEO Control Center's "Regenerate
 * Sitemap" admin button (via the queued RegenerateSitemap job) — both
 * writing the SAME fixed set of files. An admin clicking that button at (or
 * near) the scheduled time could genuinely race the scheduled run, risking
 * a corrupted/internally-inconsistent sitemap. generateAll() now takes a
 * non-blocking Cache::lock() around its whole body so the second caller
 * fails fast and cleanly instead of silently racing.
 */
class SitemapGenerationLockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map('unlink', glob(public_path('sitemaps/*.xml')) ?: []);
        @unlink(public_path('sitemap.xml'));
        Cache::lock('sitemap:generate:lock', 600)->forceRelease();

        parent::tearDown();
    }

    #[Test]
    public function a_second_call_while_generation_is_in_progress_is_rejected_cleanly(): void
    {
        Http::fake();
        $lock = Cache::lock('sitemap:generate:lock', 600);
        $lock->get(); // simulates another process's generateAll() already running

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already in progress');

        app(SitemapService::class)->generateAll();
    }

    #[Test]
    public function the_lock_is_released_after_a_successful_run_so_the_next_call_can_proceed(): void
    {
        Http::fake();

        app(SitemapService::class)->generateAll();
        // If the lock leaked, this second, legitimate call would incorrectly
        // throw "already in progress" even though the first run finished.
        app(SitemapService::class)->generateAll();

        $this->assertFileExists(public_path('sitemap.xml'));
    }

    #[Test]
    public function the_lock_is_released_even_when_generation_throws(): void
    {
        Http::fake();
        // Cloudflare purge failures are already caught inside generateAll()
        // and don't reach here — force a genuine failure a different way:
        // an unwritable sitemaps directory.
        @mkdir(public_path('sitemaps'), 0555, true);

        try {
            app(SitemapService::class)->generateAll();
        } catch (\Throwable) {
            // Expected — only the lock's fate matters for this test.
        } finally {
            @chmod(public_path('sitemaps'), 0775);
        }

        $this->assertTrue(Cache::lock('sitemap:generate:lock', 600)->get(), 'the lock must not be left held after a failed run');
        Cache::lock('sitemap:generate:lock', 600)->forceRelease();
    }
}
