<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Page;
use App\Services\SitemapService;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Every sitemap entry carries the full set of language alternates (and x-default), so Google pairs the translations. */
class SitemapHreflangTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), glob(public_path('sitemaps/*.xml')) ?: []);
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    public function test_page_and_home_entries_list_every_locale_and_an_x_default(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
        Page::create([
            'title' => ['en' => 'Guide'], 'slug' => 'guide', 'content' => ['en' => '<p>x</p>'],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);

        Http::fake();
        app(SitemapService::class)->generateAll();
        $xml = file_get_contents(public_path('sitemaps/sitemap-pages.xml'));

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        foreach (['en', 'de', 'lt', 'fr', 'es'] as $code) {
            $this->assertMatchesRegularExpression('#<xhtml:link rel="alternate" hreflang="'.$code.'" href="[^"]+/'.$code.'/guide"#', $xml);
        }
        $this->assertStringContainsString('hreflang="x-default"', $xml);
        // 5 locales → each of the 5 <url> blocks lists 6 links (5 + x-default), for the page and again for the homepage.
        $this->assertSame(60, substr_count($xml, '<xhtml:link '));

        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'the sitemap stays well-formed XML');
    }

    public function test_a_file_is_closed_before_the_size_limit_and_the_estimate_tracks_the_real_size(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
        $service = app(SitemapService::class);
        $ref = new \ReflectionClass($service);
        $call = fn (string $m, ...$a) => tap($ref->getMethod($m))->setAccessible(true)->invoke($service, ...$a);

        $writer = $call('openWriter');
        for ($i = 0; $i < 200; $i++) {
            $call('writeUrl', $writer, [
                'loc' => 'https://parts.example.com/en/parts/PART'.$i, 'lastmod' => now()->toIso8601String(),
                'changefreq' => 'weekly', 'priority' => '0.8',
                'alternates' => ['en' => 'https://parts.example.com/en/parts/PART'.$i, 'de' => 'https://parts.example.com/de/parts/PART'.$i, 'x-default' => 'https://parts.example.com/en/parts/PART'.$i],
                'images' => [['loc' => 'https://cdn.example.com/img/'.$i.'.jpg', 'caption' => 'Brake pad']],
            ]);
        }
        $actual = strlen($writer->outputMemory(false));
        $estimate = $ref->getProperty('estimatedBytes');
        $estimate->setAccessible(true);

        $this->assertEqualsWithDelta($actual, $estimate->getValue($service), $actual * 0.35, 'the running estimate is close to the real size');
        $this->assertFalse($call('fileIsFull', 10));

        $estimate->setValue($service, 41_000_000);
        $this->assertTrue($call('fileIsFull', 10), 'a file near 50 MB is closed even with few URLs');
        $this->assertTrue($call('fileIsFull', 50_000));
    }
}
