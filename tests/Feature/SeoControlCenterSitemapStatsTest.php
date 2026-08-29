<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Control Center's sitemap subheading previously showed only a
 * last-generated timestamp — a silently truncated or empty regeneration was
 * unnoticeable without opening the raw XML. Now surfaces a per-sub-sitemap
 * URL count plus a direct link to the live file.
 */
class SeoControlCenterSitemapStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        // This is a real Docker dev container, not an ephemeral CI
        // filesystem — a genuine sitemap:generate run elsewhere in this
        // same environment persists across test runs. Clear it BEFORE
        // each test too, not just after, so "no sitemap" tests aren't at
        // the mercy of whatever real files happen to already exist on disk.
        $this->clearSitemapFiles();
    }

    protected function tearDown(): void
    {
        $this->clearSitemapFiles();

        parent::tearDown();
    }

    private function clearSitemapFiles(): void
    {
        File::deleteDirectory(public_path('sitemaps'));
        @unlink(public_path('sitemap.xml'));
    }

    private function writeFixtureSitemap(int $partsUrls, int $brandUrls): void
    {
        File::ensureDirectoryExists(public_path('sitemaps'));

        $urls = str_repeat('<url><loc>http://localhost/en/parts/X</loc></url>', $partsUrls);
        File::put(public_path('sitemaps/sitemap-parts.xml'), "<urlset>{$urls}</urlset>");

        $brands = str_repeat('<url><loc>http://localhost/en/brand/x</loc></url>', $brandUrls);
        File::put(public_path('sitemaps/sitemap-brands.xml'), "<urlset>{$brands}</urlset>");

        File::put(public_path('sitemap.xml'), '<sitemapindex></sitemapindex>');
    }

    #[Test]
    public function it_shows_no_sitemap_message_when_none_exists(): void
    {
        Livewire::test(SeoControlCenter::class)
            ->assertSee('Sitemap has not been generated yet.');
    }

    #[Test]
    public function it_shows_the_url_count_per_sub_sitemap_and_a_link_to_the_live_file(): void
    {
        $this->writeFixtureSitemap(partsUrls: 90, brandUrls: 9);

        Livewire::test(SeoControlCenter::class)
            ->assertSee('99 URLs across 2 files')
            ->assertSee('Parts')
            ->assertSee('90')
            ->assertSee('Brands')
            ->assertSee('9')
            ->assertSee('View sitemap.xml');
    }

    #[Test]
    public function it_links_to_googles_rich_results_test_and_schema_validator(): void
    {
        // No in-admin JSON-LD preview exists yet — these are the fastest
        // path to checking whether the structured data this same page
        // generates (AggregateRating/FAQPage/etc.) actually validates.
        Livewire::test(SeoControlCenter::class)
            ->assertSee('https://search.google.com/test/rich-results?url='.urlencode(url('/')), false)
            ->assertSee('https://validator.schema.org/#url='.urlencode(url('/')), false);
    }
}
