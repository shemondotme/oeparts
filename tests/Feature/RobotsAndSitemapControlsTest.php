<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Models\Admin;
use App\Models\Manufacturer;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Services\SitemapService;
use App\Support\RobotsCustomRules;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin-editable extra robots.txt rules (validated so one line can never
 * block the site) and per-group sitemap switches (a switched-off group is
 * neither generated nor left behind as a stale file).
 */
class RobotsAndSitemapControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), glob(public_path('sitemaps/*.xml')) ?: []); // parallel runs share this real folder
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function setting(string $group, string $key, string $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value, 'type' => $type, 'is_encrypted' => false]);
        app(SettingsService::class)->forget($group);
    }

    // ── robots.txt rules ──────────────────────────────────────────────────

    #[Test]
    public function standard_directives_and_comments_are_accepted(): void
    {
        $this->assertNull(RobotsCustomRules::problem("# keep out\nDisallow: /en/internal/\nAllow: /en/internal/public/\nCrawl-delay: 5\nSitemap: https://x.org/extra.xml\n\n"));
        $this->assertNull(RobotsCustomRules::problem(''));
        $this->assertNull(RobotsCustomRules::problem(null));
    }

    #[Test]
    public function a_rule_that_would_block_the_whole_site_is_refused(): void
    {
        foreach (['Disallow: /', 'disallow:/', "Disallow: /en/x\nDisallow: /  "] as $bad) {
            $this->assertStringContainsString('would block the whole site', (string) RobotsCustomRules::problem($bad), $bad);
        }
    }

    #[Test]
    public function arbitrary_text_and_over_long_input_are_refused(): void
    {
        $this->assertStringContainsString('is not a robots.txt rule', (string) RobotsCustomRules::problem("Disallow: /ok\n<script>alert(1)</script>"));
        $this->assertStringContainsString('longer than', (string) RobotsCustomRules::problem('Disallow: /'.str_repeat('a', 400)));
        $this->assertStringContainsString('At most', (string) RobotsCustomRules::problem(implode("\n", array_fill(0, 101, 'Disallow: /a'))));
    }

    #[Test]
    public function invalid_text_yields_no_lines_at_all_rather_than_a_half_applied_block(): void
    {
        $this->assertSame([], RobotsCustomRules::lines("Disallow: /ok\nnonsense"));
        $this->assertSame(['Disallow: /a', 'Allow: /a/b'], RobotsCustomRules::lines("# c\n  Disallow: /a  \n\nAllow: /a/b"));
    }

    #[Test]
    public function extra_rules_land_in_the_wildcard_group_before_the_per_bot_groups(): void
    {
        $this->setting('crawlers', 'robots_custom_rules', "Disallow: /en/internal/");
        $this->setting('crawlers', 'ai_bot_rules', json_encode([['user_agent' => 'GPTBot', 'action' => 'block']]), 'json');
        $this->withoutMiddleware(RedirectIfNotInstalled::class);
        $this->app['env'] = 'production';

        $body = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString('Disallow: /en/internal/', $body);
        $this->assertLessThan(strpos($body, 'User-agent: GPTBot'), strpos($body, 'Disallow: /en/internal/'), 'must belong to the * group, not GPTBot');
        $this->assertGreaterThan(strpos($body, 'User-agent: *'), strpos($body, 'Disallow: /en/internal/'));
    }

    #[Test]
    public function the_extra_rules_never_apply_outside_production(): void
    {
        $this->setting('crawlers', 'robots_custom_rules', "Disallow: /en/internal/");

        $this->assertStringNotContainsString('/en/internal/', $this->get('/robots.txt')->getContent());
    }

    #[Test]
    public function the_control_center_refuses_a_site_blocking_rule_and_saves_a_good_one(): void
    {
        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(SeoControlCenter::class)
            ->set('data.robots_custom_rules', 'Disallow: /')
            ->call('save')
            ->assertHasErrors(['data.robots_custom_rules']);

        Livewire::test(SeoControlCenter::class)
            ->set('data.robots_custom_rules', 'Disallow: /en/internal/')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Disallow: /en/internal/', Setting::where(['group' => 'crawlers', 'key' => 'robots_custom_rules'])->value('value'));
    }

    // ── Sitemap groups ────────────────────────────────────────────────────

    #[Test]
    public function a_switched_off_group_is_not_generated_and_its_old_files_are_removed(): void
    {
        Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);

        app(SitemapService::class)->generateAll();
        $this->assertFileExists(public_path('sitemaps/sitemap-brands.xml'));

        $this->setting('seo', 'sitemap_include_brands', 'false', 'boolean');
        app(SitemapService::class)->generateAll();

        $this->assertFileDoesNotExist(public_path('sitemaps/sitemap-brands.xml'));
        $this->assertFileExists(public_path('sitemaps/sitemap-pages.xml'), 'other groups are unaffected');
        $this->assertStringNotContainsString('sitemap-brands.xml', file_get_contents(public_path('sitemap.xml')));
        $this->assertStringContainsString('sitemap-pages.xml', file_get_contents(public_path('sitemap.xml')));
    }

    #[Test]
    public function every_group_is_included_by_default(): void
    {
        app(SitemapService::class)->generateAll();
        $index = file_get_contents(public_path('sitemap.xml'));

        foreach (['sitemap-parts', 'sitemap-crossrefs', 'sitemap-brands', 'sitemap-models', 'sitemap-pages', 'sitemap-blog'] as $base) {
            $this->assertStringContainsString("{$base}.xml", $index);
        }
    }
}
