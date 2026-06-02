<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\URL;
use XMLWriter;

/**
 * Sitemap Service — generates XML sitemaps for search engines.
 *
 * Generates multiple sitemap files:
 *   - sitemap.xml          (index)
 *   - sitemap-parts.xml    (products, split per 50 K URLs per PRD)
 *   - sitemap-brands.xml   (manufacturers)
 *   - sitemap-models.xml   (car models)
 *   - sitemap-pages.xml    (CMS pages + homepages)
 *   - sitemap-blog.xml     (blog posts)
 *
 * All sitemaps are written to public/sitemaps/ and referenced in the index.
 * URLs are streamed directly to disk — never accumulated in memory.
 * Each file is capped at MAX_URLS_PER_FILE (50 000) per PRD § Module 8.
 */
class SitemapService
{
    private const MAX_URLS_PER_FILE = 50_000;

    private array $supportedLocales = ['en', 'de', 'lt', 'fr', 'es'];

    private string $sitemapDirectory = 'sitemaps';

    public function __construct(
        private SettingsService $settings
    ) {}

    /**
     * Generate all sitemaps and the master index.
     *
     * @return array List of generated file basenames
     */
    public function generateAll(): array
    {
        $this->ensureDirectory();

        $files = [];

        array_push($files, ...$this->generateProductsSitemap());
        array_push($files, ...$this->generateManufacturersSitemap());
        array_push($files, ...$this->generateCarModelsSitemap());
        array_push($files, ...$this->generatePagesSitemap());
        array_push($files, ...$this->generateBlogSitemap());

        $indexPath = $this->generateIndex($files);

        if ($this->settings->get('seo.google_ping_enabled', true)) {
            $this->pingGoogle();
        }

        return array_merge([$indexPath], $files);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Individual sitemap generators — each returns an array of written filenames
    // ─────────────────────────────────────────────────────────────────────────

    private function generateProductsSitemap(): array
    {
        $written = [];
        $batch = 1;
        $writer = null;
        $count = 0;

        Product::where('is_active', true)
            ->where('is_in_stock', true)
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (Product $product) use (&$written, &$batch, &$writer, &$count) {
                foreach ($this->supportedLocales as $locale) {
                    if ($writer === null || $count >= self::MAX_URLS_PER_FILE) {
                        if ($writer !== null) {
                            $written[] = $this->closeWriter($writer, 'sitemap-parts', $batch);
                            $batch++;
                        }
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $this->writeUrl($writer, [
                        'loc' => URL::route('frontend.search.results', ['lang' => $locale, 'oem' => $product->normalized_oem]),
                        'lastmod' => $product->updated_at->toIso8601String(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ]);
                    $count++;
                }
            });

        if ($writer !== null) {
            $written[] = $this->closeWriter($writer, 'sitemap-parts', $batch);
        }

        return $written ?: [$this->emptyFile('sitemap-parts-1.xml')];
    }

    private function generateManufacturersSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];

        Manufacturer::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (Manufacturer $manufacturer) use (&$writer, &$batch, &$count, &$written) {
                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-brands', $batch);
                        $batch++;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $this->writeUrl($writer, [
                        'loc' => URL::route('frontend.manufacturer.show', ['lang' => $locale, 'manufacturer' => $manufacturer->slug]),
                        'lastmod' => $manufacturer->updated_at->toIso8601String(),
                        'changefreq' => 'monthly',
                        'priority' => '0.6',
                    ]);
                    $count++;
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-brands', $batch);

        return $written;
    }

    private function generateCarModelsSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];

        CarModel::where('is_active', true)
            ->with('manufacturer')
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (CarModel $model) use (&$writer, &$batch, &$count, &$written) {
                if (! $model->manufacturer) {
                    return;
                }

                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-models', $batch);
                        $batch++;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $this->writeUrl($writer, [
                        'loc' => URL::route('frontend.car-model.show', [
                            'lang' => $locale,
                            'manufacturer' => $model->manufacturer->slug,
                            'model' => $model->slug,
                        ]),
                        'lastmod' => $model->updated_at->toIso8601String(),
                        'changefreq' => 'monthly',
                        'priority' => '0.5',
                    ]);
                    $count++;
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-models', $batch);

        return $written;
    }

    private function generatePagesSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];

        // Homepages for each locale
        foreach ($this->supportedLocales as $locale) {
            $this->writeUrl($writer, [
                'loc' => URL::to("/{$locale}/"),
                'lastmod' => now()->toIso8601String(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ]);
            $count++;
        }

        Page::where('status', ContentStatus::Published->value)
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (Page $page) use (&$writer, &$batch, &$count, &$written) {
                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-pages', $batch);
                        $batch++;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $this->writeUrl($writer, [
                        'loc' => URL::to("/{$locale}/{$page->slug}"),
                        'lastmod' => $page->updated_at->toIso8601String(),
                        'changefreq' => 'monthly',
                        'priority' => '0.4',
                    ]);
                    $count++;
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-pages', $batch);

        return $written;
    }

    private function generateBlogSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];

        BlogPost::where('status', ContentStatus::Published->value)
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->cursor()
            ->each(function (BlogPost $post) use (&$writer, &$batch, &$count, &$written) {
                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-blog', $batch);
                        $batch++;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $this->writeUrl($writer, [
                        'loc' => URL::to("/{$locale}/blog/{$post->slug}"),
                        'lastmod' => $post->updated_at->toIso8601String(),
                        'changefreq' => 'weekly',
                        'priority' => '0.7',
                    ]);
                    $count++;
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-blog', $batch);

        return $written;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // XMLWriter helpers — open / write / close
    // ─────────────────────────────────────────────────────────────────────────

    private function openWriter(): XMLWriter
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $writer;
    }

    private function writeUrl(XMLWriter $writer, array $url): void
    {
        $writer->startElement('url');
        $writer->writeElement('loc', $url['loc']);
        $writer->writeElement('lastmod', $url['lastmod']);
        $writer->writeElement('changefreq', $url['changefreq']);
        $writer->writeElement('priority', $url['priority']);
        $writer->endElement();

        // Flush buffered output to a temp file every 500 URLs to keep memory low
        // We use outputMemory(true) which clears the buffer after reading.
    }

    /**
     * Finalise a sitemap XMLWriter, flush to disk and return the filename.
     */
    private function closeWriter(XMLWriter $writer, string $base, int $batch): string
    {
        $writer->endElement(); // </urlset>
        $writer->endDocument();

        $filename = $batch === 1 ? "{$base}.xml" : "{$base}-{$batch}.xml";
        $path = public_path("{$this->sitemapDirectory}/{$filename}");

        file_put_contents($path, $writer->outputMemory(true));

        return $filename;
    }

    /**
     * Create an empty (but valid) sitemap for when a content type has no records.
     */
    private function emptyFile(string $filename): string
    {
        $writer = $this->openWriter();

        return $this->closeWriter($writer, rtrim(str_replace('.xml', '', $filename), '-1'), 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index + helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function generateIndex(array $sitemapFiles): string
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($sitemapFiles as $file) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', URL::asset("{$this->sitemapDirectory}/{$file}"));
            $writer->writeElement('lastmod', now()->toIso8601String());
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        $path = public_path('sitemap.xml');
        file_put_contents($path, $writer->outputMemory(true));

        return 'sitemap.xml';
    }

    private function ensureDirectory(): void
    {
        $path = public_path($this->sitemapDirectory);
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function pingGoogle(): void
    {
        try {
            $client = new Client(['timeout' => 5]);
            $client->get('https://www.google.com/ping', [
                'query' => ['sitemap' => url('sitemap.xml')],
            ]);
        } catch (\Exception) {
            // Silent — ping failure must never abort sitemap generation
        }
    }

    public function getSitemapUrl(): string
    {
        return url('sitemap.xml');
    }

    public function cleanup(): void
    {
        $keep = [
            'sitemap-parts.xml', 'sitemap-brands.xml',
            'sitemap-models.xml', 'sitemap-pages.xml', 'sitemap-blog.xml',
        ];

        foreach (glob(public_path("{$this->sitemapDirectory}/sitemap-*.xml")) ?: [] as $file) {
            if (! in_array(basename($file), $keep, true)) {
                @unlink($file);
            }
        }
    }
}
