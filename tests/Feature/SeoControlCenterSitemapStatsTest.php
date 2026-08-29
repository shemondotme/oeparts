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
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('sitemaps'));
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
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
}
