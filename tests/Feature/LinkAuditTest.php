<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\RedirectType;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Services\LinkAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Internal links inside page/post content are resolved against what the site
 * really serves; orphan pages are found; external links are only counted.
 */
class LinkAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Admin::factory()->create(), 'admin');
        Cache::flush();
    }

    private function page(string $slug, string $html = '', array $attrs = []): Page
    {
        return Page::create($attrs + [
            'title' => ['en' => ucfirst($slug)], 'slug' => $slug, 'content' => ['en' => $html],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function scanLinksIn(string $html): array
    {
        $this->page('source', $html);

        return app(LinkAuditService::class)->scan();
    }

    private function brokenHrefs(array $scan): array
    {
        return array_column($scan['broken'], 'href');
    }

    #[Test]
    public function links_to_existing_content_are_fine(): void
    {
        $brand = Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
        CarModel::create(['manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $product = Product::create(['manufacturer_id' => $brand->id, 'oem_number' => 'AB-123', 'normalized_oem' => 'AB123', 'condition_id' => $condition->id, 'price' => 5, 'is_active' => true, 'is_in_stock' => true]);
        $this->page('target');
        BlogPost::create(['title' => ['en' => 'T'], 'slug' => 'a-post', 'content' => ['en' => 'x'], 'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'author_id' => Admin::factory()->create()->id]);

        $scan = $this->scanLinksIn(implode(' ', [
            '<a href="/en">home</a>', '<a href="/en/">home2</a>', '<a href="/en/target">p</a>', '<a href="/en/blog">b</a>',
            '<a href="/en/blog/a-post">post</a>', '<a href="/en/brands">brands</a>', '<a href="/en/brand/acme">brand</a>',
            '<a href="/en/brand/acme/models">models</a>', '<a href="/en/brand/acme/rocket">model</a>', '<a href="/en/parts">parts</a>',
            '<a href="/en/parts/ab-123">oem</a>', "<a href=\"/en/parts/AB123/{$product->id}-acme-part\">detail</a>", '<a href="/en/contact?x=1#frag">c</a>',
        ]));

        $this->assertSame([], $scan['broken'], json_encode($scan['broken']));
        $this->assertGreaterThanOrEqual(13, $scan['links']);
    }

    #[Test]
    public function links_that_lead_to_a_404_are_reported_with_a_reason(): void
    {
        $scan = $this->scanLinksIn(implode(' ', [
            '<a href="/en/no-such-page">a</a>', '<a href="/about">no language</a>', '<a href="/en/blog/missing-post">b</a>',
            '<a href="/en/brand/nobody">c</a>', '<a href="/en/parts/ZZ999">d</a>', '<a href="/en/a/b/c">e</a>',
        ]));

        $this->assertEqualsCanonicalizing(
            ['/en/no-such-page', '/about', '/en/blog/missing-post', '/en/brand/nobody', '/en/parts/ZZ999', '/en/a/b/c'],
            $this->brokenHrefs($scan)
        );
        $reasons = implode(' | ', array_column($scan['broken'], 'reason'));
        $this->assertStringContainsString('no language in the address', $reasons);
        $this->assertStringContainsString('no published page', $reasons);
    }

    #[Test]
    public function a_draft_or_scheduled_page_is_not_a_valid_target(): void
    {
        $this->page('draft-page', '', ['status' => ContentStatus::Draft]);
        $this->page('future-page', '', ['published_at' => now()->addWeek()]);

        $scan = $this->scanLinksIn('<a href="/en/draft-page">d</a> <a href="/en/future-page">f</a>');

        $this->assertEqualsCanonicalizing(['/en/draft-page', '/en/future-page'], $this->brokenHrefs($scan));
    }

    #[Test]
    public function a_link_that_works_only_through_a_redirect_is_flagged_and_a_410_target_is_broken(): void
    {
        Redirect::create(['from_url' => 'en/old-page', 'to_url' => '/en/target', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);
        Redirect::create(['from_url' => 'en/retired', 'to_url' => '', 'type' => RedirectType::Gone, 'is_active' => true, 'hit_count' => 0]);
        $this->page('target');

        $scan = $this->scanLinksIn('<a href="/en/old-page">old</a> <a href="/en/retired">gone</a>');

        $this->assertSame(['/en/old-page'], array_column($scan['redirected'], 'href'));
        $this->assertStringContainsString('/en/target', $scan['redirected'][0]['reason']);
        $this->assertSame(['/en/retired'], $this->brokenHrefs($scan));
    }

    #[Test]
    public function external_mail_phone_and_anchor_links_are_never_judged(): void
    {
        $scan = $this->scanLinksIn('<a href="https://other-site.example/x">ext</a> <a href="mailto:a@b.c">m</a> <a href="tel:+37060000000">t</a> <a href="#top">a</a> <a href="javascript:void(0)">j</a>');

        $this->assertSame([], $scan['broken']);
        $this->assertSame(1, $scan['external']);
        $this->assertSame(0, $scan['links']);
    }

    #[Test]
    public function an_absolute_link_to_our_own_domain_is_treated_as_internal(): void
    {
        config(['app.url' => 'https://shop.example.org']);

        $scan = $this->scanLinksIn('<a href="https://shop.example.org/en/no-such">x</a> <a href="https://www.shop.example.org/en/no-such-2">y</a>');

        $this->assertCount(2, $scan['broken']);
        $this->assertSame(0, $scan['external']);
    }

    #[Test]
    public function pages_nothing_links_to_are_orphans_and_stop_being_one_once_linked_or_in_the_menu(): void
    {
        $this->page('lonely');
        $this->page('menu-page', '', ['is_footer' => true]);
        $this->page('linked-page');
        $this->page('hub', '<a href="/en/linked-page">see</a>');

        $orphans = array_column(app(LinkAuditService::class)->scan()['orphans'], 'label');
        $labels = implode(' ', $orphans);

        $this->assertStringContainsString('/lonely', $labels);
        $this->assertStringContainsString('/hub', $labels, 'the hub itself is linked from nowhere');
        $this->assertStringNotContainsString('/menu-page', $labels);
        $this->assertStringNotContainsString('/linked-page', $labels);
    }

    #[Test]
    public function a_page_linking_only_to_itself_is_still_an_orphan(): void
    {
        $this->page('selfie', '<a href="/en/selfie">me</a>');

        $this->assertStringContainsString('/selfie', implode(' ', array_column(app(LinkAuditService::class)->scan()['orphans'], 'label')));
    }

    #[Test]
    public function the_audit_page_lists_the_link_findings_and_caches_the_scan(): void
    {
        $this->page('source', '<a href="/en/no-such-page">x</a>');

        $audit = collect(app(\App\Services\SeoAuditService::class)->run()['checks'])->keyBy('id');

        $this->assertSame('warning', $audit['links_broken']['severity']);
        $this->assertSame(1, $audit['links_broken']['count']);
        $this->assertTrue(Cache::has(\App\Services\SeoAuditService::LINKS_CACHE_KEY));
    }
}
