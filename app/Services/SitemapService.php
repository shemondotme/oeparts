<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Product;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\BlogPost;
use App\Models\CarModel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

/**
 * Sitemap Service — generates XML sitemaps for search engines.
 *
 * Generates multiple sitemap files:
 *   - sitemap.xml (index)
 *   - sitemap-parts.xml (products)
 *   - sitemap-brands.xml (manufacturers)
 *   - sitemap-models.xml (car models)
 *   - sitemap-pages.xml (CMS pages)
 *   - sitemap-blog.xml (blog posts)
 *
 * All sitemaps are written to public/sitemaps/ and referenced in the index.
 * The service also supports pinging Google when sitemaps are updated.
 */
class SitemapService
{
    private array $supportedLocales = ['en', 'de', 'lt', 'fr', 'es'];
    private string $sitemapDirectory = 'sitemaps';

    public function __construct(
        private SettingsService $settings
    ) {}

    /**
     * Generate all sitemaps and the master index.
     *
     * @return array List of generated file paths
     */
    public function generateAll(): array
    {
        $this->ensureDirectory();

        $files = [];

        // Generate individual sitemaps
        $files[] = $this->generateProductsSitemap();
        $files[] = $this->generateManufacturersSitemap();
        $files[] = $this->generateCarModelsSitemap();
        $files[] = $this->generatePagesSitemap();
        $files[] = $this->generateBlogSitemap();

        // Generate the index
        $indexPath = $this->generateIndex($files);

        // Ping Google if enabled
        if ($this->settings->get('seo.google_ping_enabled', true)) {
            $this->pingGoogle();
        }

        return array_merge([$indexPath], $files);
    }

    /**
     * Ensure the sitemap directory exists.
     */
    private function ensureDirectory(): void
    {
        $path = public_path($this->sitemapDirectory);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Generate sitemap for products (parts).
     */
    private function generateProductsSitemap(): string
    {
        $products = Product::where('is_active', true)
            ->where('is_in_stock', true)
            ->orderBy('updated_at', 'desc')
            ->cursor();

        $urls = [];
        foreach ($products as $product) {
            foreach ($this->supportedLocales as $locale) {
                $urls[] = [
                    'loc' => URL::route('frontend.search.results', [
                        'lang' => $locale,
                        'oem' => $product->normalized_oem,
                    ]),
                    'lastmod' => $product->updated_at->toIso8601String(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }
        }

        return $this->writeSitemap('sitemap-parts.xml', $urls);
    }

    /**
     * Generate sitemap for manufacturers.
     */
    private function generateManufacturersSitemap(): string
    {
        $manufacturers = Manufacturer::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->cursor();

        $urls = [];
        foreach ($manufacturers as $manufacturer) {
            foreach ($this->supportedLocales as $locale) {
                $urls[] = [
                    'loc' => URL::route('frontend.manufacturer.show', [
                        'lang' => $locale,
                        'manufacturer' => $manufacturer->slug,
                    ]),
                    'lastmod' => $manufacturer->updated_at->toIso8601String(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            }
        }

        return $this->writeSitemap('sitemap-brands.xml', $urls);
    }

    /**
     * Generate sitemap for car models.
     */
    private function generateCarModelsSitemap(): string
    {
        $models = CarModel::where('is_active', true)
            ->with('manufacturer')
            ->orderBy('updated_at', 'desc')
            ->cursor();

        $urls = [];
        foreach ($models as $model) {
            foreach ($this->supportedLocales as $locale) {
                $urls[] = [
                    'loc' => URL::route('frontend.car-model.show', [
                        'lang' => $locale,
                        'manufacturer' => $model->manufacturer->slug,
                        'model' => $model->slug,
                    ]),
                    'lastmod' => $model->updated_at->toIso8601String(),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ];
            }
        }

        return $this->writeSitemap('sitemap-models.xml', $urls);
    }

    /**
     * Generate sitemap for CMS pages.
     */
    private function generatePagesSitemap(): string
    {
        $pages = Page::where('status', ContentStatus::Published->value)
            ->orderBy('updated_at', 'desc')
            ->cursor();

        $urls = [];
        foreach ($pages as $page) {
            foreach ($this->supportedLocales as $locale) {
                $urls[] = [
                    'loc' => URL::to("/{$locale}/{$page->slug}"),
                    'lastmod' => $page->updated_at->toIso8601String(),
                    'changefreq' => 'monthly',
                    'priority' => '0.4',
                ];
            }
        }

        // Add homepage for each locale
        foreach ($this->supportedLocales as $locale) {
            $urls[] = [
                'loc' => URL::to("/{$locale}/"),
                'lastmod' => now()->toIso8601String(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];
        }

        return $this->writeSitemap('sitemap-pages.xml', $urls);
    }

    /**
     * Generate sitemap for blog posts.
     */
    private function generateBlogSitemap(): string
    {
        $posts = BlogPost::where('status', ContentStatus::Published->value)
            ->where('published_at', '<=', now())
            ->orderBy('published_at', 'desc')
            ->cursor();

        $urls = [];
        foreach ($posts as $post) {
            foreach ($this->supportedLocales as $locale) {
                $urls[] = [
                    'loc' => URL::to("/{$locale}/blog/{$post->slug}"),
                    'lastmod' => $post->updated_at->toIso8601String(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            }
        }

        return $this->writeSitemap('sitemap-blog.xml', $urls);
    }

    /**
     * Write a sitemap XML file.
     *
     * @param string $filename
     * @param array $urls
     * @return string Full public path to the file
     */
    private function writeSitemap(string $filename, array $urls): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

        foreach ($urls as $url) {
            $entry = $xml->addChild('url');
            $entry->addChild('loc', htmlspecialchars($url['loc']));
            $entry->addChild('lastmod', $url['lastmod']);
            $entry->addChild('changefreq', $url['changefreq']);
            $entry->addChild('priority', $url['priority']);
        }

        $path = public_path("{$this->sitemapDirectory}/{$filename}");
        $xml->asXML($path);

        return $filename;
    }

    /**
     * Generate the sitemap index file referencing all sitemaps.
     *
     * @param array $sitemapFiles
     * @return string Index filename
     */
    private function generateIndex(array $sitemapFiles): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

        foreach ($sitemapFiles as $file) {
            $entry = $xml->addChild('sitemap');
            $entry->addChild('loc', htmlspecialchars(URL::asset("{$this->sitemapDirectory}/{$file}")));
            $entry->addChild('lastmod', now()->toIso8601String());
        }

        $path = public_path('sitemap.xml');
        $xml->asXML($path);

        return 'sitemap.xml';
    }

    /**
     * Ping Google about the updated sitemap.
     */
    private function pingGoogle(): void
    {
        $sitemapUrl = url('sitemap.xml');

        try {
            $client = new \GuzzleHttp\Client();
            $client->get('https://www.google.com/ping', [
                'query' => ['sitemap' => $sitemapUrl],
                'timeout' => 5,
            ]);
        } catch (\Exception $e) {
            // Silent fail — logging would be better in production
        }
    }

    /**
     * Get the URL of the main sitemap index.
     */
    public function getSitemapUrl(): string
    {
        return url('sitemap.xml');
    }

    /**
     * Clean up old sitemap files (keep only the latest).
     */
    public function cleanup(): void
    {
        $files = glob(public_path("{$this->sitemapDirectory}/sitemap-*.xml"));
        $keep = [
            'sitemap-parts.xml',
            'sitemap-brands.xml',
            'sitemap-models.xml',
            'sitemap-pages.xml',
            'sitemap-blog.xml',
        ];

        foreach ($files as $file) {
            $basename = basename($file);
            if (!in_array($basename, $keep, true)) {
                unlink($file);
            }
        }
    }
}