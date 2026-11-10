<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Page;
use App\Services\ExternalLinkChecker;
use App\Services\LinkAuditService;
use App\Services\OemNormalizerService;
use App\Services\SeoAuditService;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Links to other websites: checked politely in the background, never on the audit page itself. */
class ExternalLinkCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
        Cache::forget(ExternalLinkChecker::CACHE_KEY);
    }

    private function pageLinking(string ...$hrefs): Page
    {
        $html = implode('', array_map(fn ($h) => '<p><a href="'.$h.'">link</a></p>', $hrefs));

        return Page::create([
            'title' => ['en' => 'Resources'], 'slug' => 'resources'.uniqid(), 'content' => ['en' => $html],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);
    }

    private function checker(?\Closure $publicHost = null): ExternalLinkChecker
    {
        return new ExternalLinkChecker(app(LinkAuditService::class), $publicHost ?? fn () => true, pauseMs: 0);
    }

    public function test_dead_pages_are_reported_with_where_they_are_used_and_good_ones_are_not(): void
    {
        $this->pageLinking('https://good.example/ok', 'https://dead.example/gone', 'https://gone.example/410');
        Http::fake([
            'good.example/*' => Http::response('', 200),
            'dead.example/*' => Http::response('', 404),
            'gone.example/*' => Http::response('', 410),
        ]);

        $result = $this->checker()->run();

        $this->assertSame(3, $result['checked']);
        $this->assertEqualsCanonicalizing(['https://dead.example/gone', 'https://gone.example/410'], array_column($result['broken'], 'href'));
        $this->assertStringContainsString('Page: Resources', $result['broken'][0]['sources'][0]['label']);
        $this->assertNotNull(ExternalLinkChecker::last());
    }

    public function test_a_site_that_refuses_head_is_asked_again_with_get(): void
    {
        $this->pageLinking('https://nohead.example/page');
        Http::fake(function ($request) {
            return $request->method() === 'HEAD' ? Http::response('', 405) : Http::response('ok', 206);
        });

        $this->assertSame([], $this->checker()->run()['broken']);
    }

    public function test_bot_blocking_sites_are_unverified_not_broken(): void
    {
        $this->pageLinking('https://shield.example/a', 'https://limit.example/b');
        Http::fake(['shield.example/*' => Http::response('', 403), 'limit.example/*' => Http::response('', 429)]);

        $result = $this->checker()->run();

        $this->assertSame([], $result['broken']);
        $this->assertSame(2, $result['unverified']);
    }

    public function test_a_server_error_is_retried_and_a_one_off_hiccup_is_forgiven(): void
    {
        $this->pageLinking('https://flaky.example/a');
        Http::fakeSequence()->push('', 503)->push('', 200);

        $this->assertSame([], $this->checker()->run()['broken']);
    }

    public function test_an_unreachable_site_is_broken(): void
    {
        $this->pageLinking('https://down.example/a');
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $result = $this->checker()->run();

        $this->assertSame('the website could not be reached', $result['broken'][0]['reason']);
    }

    public function test_private_addresses_are_never_fetched(): void
    {
        $this->pageLinking('http://127.0.0.1/admin', 'http://192.168.1.5/x');
        Http::fake();

        $checker = new ExternalLinkChecker(app(LinkAuditService::class), null, pauseMs: 0);
        $result = $checker->run();

        Http::assertNothingSent();
        $this->assertSame([], $result['broken']);
        $this->assertSame(2, $result['unverified']);
    }

    public function test_own_site_links_are_not_treated_as_external(): void
    {
        $this->pageLinking('/en/parts', 'mailto:a@b.c', '#top');
        Http::fake();

        $this->assertSame(0, $this->checker()->run()['checked']);
        Http::assertNothingSent();
    }

    public function test_the_audit_reports_the_outcome(): void
    {
        $this->pageLinking('https://dead.example/gone');
        $audit = fn () => collect(app(SeoAuditService::class)->run()['checks'] ?? [])->firstWhere('id', 'links_external');

        Cache::forget(SeoAuditService::LINKS_CACHE_KEY);
        $this->assertStringContainsString('not been checked yet', $audit()['title']);

        Http::fake(['dead.example/*' => Http::response('', 404)]);
        $this->checker()->run();
        Cache::forget(SeoAuditService::LINKS_CACHE_KEY);

        $this->assertStringContainsString('Broken links to other websites', $audit()['title']);
    }
}
