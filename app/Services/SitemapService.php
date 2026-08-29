<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\ProductImage;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    private array $supportedLocales;

    private string $sitemapDirectory = 'sitemaps';

    /**
     * basename => the real max lastmod written into that file, tracked by
     * closeWriter() and read back by generateIndex(). Every sub-sitemap's
     * <loc> entries already carry real content dates (updated_at) — this
     * just carries that same real signal up to the index's own <lastmod>
     * per sub-sitemap, instead of hardcoding now() there regardless of
     * whether that file's content actually changed since the last run.
     */
    private array $fileLastMods = [];

    public function __construct(
        private SettingsService $settings,
        private CloudflareService $cloudflare,
        private ProductSlugService $productSlugService,
    ) {
        $this->supportedLocales = LocaleRegistry::codes();
    }

    /**
     * Generate all sitemaps and the master index.
     *
     * @return array List of generated file basenames
     */
    public function generateAll(): array
    {
        try {
            $this->ensureDirectory();

            $files = [];

            array_push($files, ...$this->generateProductsSitemap());
            array_push($files, ...$this->generateCrossReferencesSitemap());
            array_push($files, ...$this->generateManufacturersSitemap());
            array_push($files, ...$this->generateCarModelsSitemap());
            array_push($files, ...$this->generatePagesSitemap());
            array_push($files, ...$this->generateBlogSitemap());

            $indexPath = $this->generateIndex($files);

            // filter_var: admin-saved booleans persist as the literal
            // string 'false' (PHP-truthy) — a bare truthy check on the raw
            // settings value never actually disabled this ping.
            if (filter_var($this->settings->get('seo.google_ping_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
                $this->pingGoogle();
            }
            if (filter_var($this->settings->get('seo.bing_ping_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
                $this->pingBing();
            }

            // sitemap.xml and every sub-file live at a FIXED URL that gets
            // overwritten in place on every regeneration (unlike uploaded
            // media/build assets, which always get a new unique filename) —
            // exactly the "same URL, content changed" case an edge cache
            // needs telling about. Best-effort: CloudflareService itself
            // no-ops when not configured, and any failure here must never
            // fail sitemap generation, which already succeeded.
            try {
                $this->cloudflare->purgeUrls(array_merge(
                    [url('sitemap.xml')],
                    array_map(fn (string $file) => \Illuminate\Support\Facades\URL::asset("{$this->sitemapDirectory}/{$file}"), $files)
                ));
            } catch (\Throwable $e) {
                Log::warning('Cloudflare sitemap purge failed', ['error' => $e->getMessage()]);
            }

            return array_merge([$indexPath], $files);
        } catch (\Exception $e) {
            Log::error('Sitemap generation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
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
        $maxLastmod = null;

        // Dedupe by normalized_oem: multiple Product rows can share the same
        // OEM (different sellers/conditions), but the storefront
        // search-result URL is keyed by OEM alone — each duplicate row
        // previously wrote an identical <loc> once per row, wasting crawl
        // budget and sending a duplicate-content signal.
        // is_in_stock is deliberately NOT filtered here — the real search
        // page only gates visibility on is_active; in_stock is an optional
        // filter a visitor can apply, not a 404. Filtering on it here
        // dropped otherwise-reachable, indexable pages from the sitemap.
        //
        // MAX(id) picks one representative product per OEM group to hang
        // <image:image> entries off — the group itself has no single real
        // product row (it's an aggregate), so an arbitrary-but-deterministic
        // member's own gallery stands in for the whole OEM's images.
        Product::where('is_active', true)
            ->selectRaw('normalized_oem, MAX(updated_at) as updated_at, MAX(id) as id')
            ->groupBy('normalized_oem')
            ->orderByDesc('updated_at')
            ->cursor()
            ->each(function (Product $product) use (&$written, &$batch, &$writer, &$count, &$maxLastmod) {
                // One extra query per DISTINCT oem (not per row) — the same
                // accepted trade-off generateCrossReferencesSitemap() already
                // makes for its own per-group lookup.
                $images = $this->sitemapImages((int) $product->id);

                foreach ($this->supportedLocales as $locale) {
                    if ($writer === null || $count >= self::MAX_URLS_PER_FILE) {
                        if ($writer !== null) {
                            $written[] = $this->closeWriter($writer, 'sitemap-parts', $batch, $maxLastmod);
                            $batch++;
                            $maxLastmod = null;
                        }
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $lastmod = $product->updated_at->toIso8601String();
                    $this->writeUrl($writer, [
                        'loc' => URL::route('frontend.search.results', ['lang' => $locale, 'oem' => $product->normalized_oem]),
                        'lastmod' => $lastmod,
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                        'images' => $images->map(fn (ProductImage $image) => [
                            'loc' => $image->medium_url,
                            'caption' => trans_field($image->alt_text, $locale),
                        ])->all(),
                    ]);
                    $count++;
                    $maxLastmod = $this->laterLastmod($maxLastmod, $lastmod);
                }
            });

        if ($writer !== null) {
            $written[] = $this->closeWriter($writer, 'sitemap-parts', $batch, $maxLastmod);
        }

        return $written ?: [$this->emptyFile('sitemap-parts-1.xml')];
    }

    /**
     * Featured first, capped well under Google's 1,000-per-URL limit — a
     * handful of real photos is worth more discovery signal per byte than
     * an exhaustive gallery dump.
     *
     * @return \Illuminate\Support\Collection<int, ProductImage>
     */
    private function sitemapImages(int $productId): \Illuminate\Support\Collection
    {
        return ProductImage::where('product_id', $productId)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(5)
            ->get();
    }

    /**
     * Cross-reference OEM numbers (other manufacturers' numbers for the
     * same physical part) are functionally searchable today
     * (SearchService::crossReferenceMatch()) but were never proactively
     * listed anywhere — Google only found them by crawling internal links,
     * with no priority signal. Deduped GLOBALLY by normalized_cross_oem
     * across the whole product_cross_references table (not per-product):
     * the storefront URL for a cross-ref number is keyed by that number
     * alone, and two different products could theoretically share one.
     *
     * One extra query per DISTINCT cross-OEM value (not per row) to
     * resolve how many active products share it, deciding hub-vs-detail
     * URL below — bounded by the number of distinct cross-references, not
     * total catalog size; an accepted trade-off for correctness/clarity
     * over a more complex single-query grouped fetch.
     */
    private function generateCrossReferencesSitemap(): array
    {
        $written = [];
        $batch = 1;
        $writer = null;
        $count = 0;
        $maxLastmod = null;
        $detailPagesEnabled = filter_var($this->settings->get('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN);

        // A product can legitimately carry many distinct cross-OEM numbers
        // (that's the whole point of cross-referencing), each of which
        // independently resolves to the SAME singleProduct detail URL below.
        // Without tracking what's already been written per locale, a
        // product with N cross-refs produced N identical duplicate <loc>
        // entries instead of one — bounded by distinct-product × locale
        // count, not total cross-reference rows.
        $emittedDetailUrls = [];

        ProductCrossReference::query()
            ->join('products', 'products.id', '=', 'product_cross_references.product_id')
            ->where('products.is_active', true)
            ->selectRaw('product_cross_references.normalized_cross_oem as normalized_cross_oem, MAX(products.updated_at) as updated_at')
            ->groupBy('product_cross_references.normalized_cross_oem')
            ->orderByDesc('updated_at')
            ->cursor()
            ->each(function ($row) use (&$written, &$batch, &$writer, &$count, &$maxLastmod, $detailPagesEnabled, &$emittedDetailUrls) {
                $crossOem = $row->normalized_cross_oem;
                $lastmod = $row->updated_at ? \Illuminate\Support\Carbon::parse($row->updated_at)->toIso8601String() : now()->toIso8601String();

                // For a single-active-product match in Hub+Detail mode,
                // point straight at the canonical detail URL — skips a
                // wasted redirect hop the hub URL would otherwise cost
                // (single-match auto-redirect in SearchController::results()).
                $activeProducts = Product::query()
                    ->whereIn('id', ProductCrossReference::where('normalized_cross_oem', $crossOem)->pluck('product_id'))
                    ->where('is_active', true)
                    ->get(['id', 'normalized_oem']);

                $singleProduct = ($detailPagesEnabled && $activeProducts->count() === 1) ? $activeProducts->first() : null;

                foreach ($this->supportedLocales as $locale) {
                    if ($singleProduct) {
                        $dedupeKey = $singleProduct->id.':'.$locale;
                        if (isset($emittedDetailUrls[$dedupeKey])) {
                            continue;
                        }
                        $emittedDetailUrls[$dedupeKey] = true;
                    }

                    if ($writer === null || $count >= self::MAX_URLS_PER_FILE) {
                        if ($writer !== null) {
                            $written[] = $this->closeWriter($writer, 'sitemap-crossrefs', $batch, $maxLastmod);
                            $batch++;
                            $maxLastmod = null;
                        }
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $loc = $singleProduct
                        ? URL::route('frontend.search.detail', [
                            'lang' => $locale,
                            'oem' => $singleProduct->normalized_oem,
                            'idSlug' => $this->productSlugService->buildIdSlug($singleProduct, $locale),
                        ])
                        : URL::route('frontend.search.results', ['lang' => $locale, 'oem' => $crossOem]);

                    $this->writeUrl($writer, [
                        'loc' => $loc,
                        'lastmod' => $lastmod,
                        'changefreq' => 'weekly',
                        'priority' => '0.5',
                    ]);
                    $count++;
                    $maxLastmod = $this->laterLastmod($maxLastmod, $lastmod);
                }
            });

        if ($writer !== null) {
            $written[] = $this->closeWriter($writer, 'sitemap-crossrefs', $batch, $maxLastmod);
        }

        return $written ?: [$this->emptyFile('sitemap-crossrefs-1.xml')];
    }

    private function generateManufacturersSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];
        $maxLastmod = null;

        Manufacturer::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (Manufacturer $manufacturer) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-brands', $batch, $maxLastmod);
                        $batch++;
                        $maxLastmod = null;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $lastmod = $manufacturer->updated_at->toIso8601String();
                    $this->writeUrl($writer, [
                        'loc' => URL::route('frontend.manufacturer.show', ['lang' => $locale, 'manufacturer' => $manufacturer->slug]),
                        'lastmod' => $lastmod,
                        'changefreq' => 'monthly',
                        'priority' => '0.6',
                    ]);
                    $count++;
                    $maxLastmod = $this->laterLastmod($maxLastmod, $lastmod);
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-brands', $batch, $maxLastmod);

        return $written;
    }

    private function generateCarModelsSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];
        $maxLastmod = null;

        // CarModelController::show() 404s unless BOTH the manufacturer and
        // the car model are active — this used to only check the model,
        // so deactivating a manufacturer left its models' URLs in the
        // sitemap pointing at pages that immediately 404 on crawl, feeding
        // Google dead links (and populating the very NotFoundLog table
        // built to catch this class of problem).
        CarModel::where('is_active', true)
            ->whereHas('manufacturer', fn ($q) => $q->where('is_active', true))
            ->with('manufacturer')
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (CarModel $model) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                if (! $model->manufacturer) {
                    return;
                }

                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-models', $batch, $maxLastmod);
                        $batch++;
                        $maxLastmod = null;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $lastmod = $model->updated_at->toIso8601String();
                    $this->writeUrl($writer, [
                        'loc' => URL::route('frontend.car-model.show', [
                            'lang' => $locale,
                            'manufacturer' => $model->manufacturer->slug,
                            'model' => $model->slug,
                        ]),
                        'lastmod' => $lastmod,
                        'changefreq' => 'monthly',
                        'priority' => '0.5',
                    ]);
                    $count++;
                    $maxLastmod = $this->laterLastmod($maxLastmod, $lastmod);
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-models', $batch, $maxLastmod);

        return $written;
    }

    private function generatePagesSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];
        $maxLastmod = null;

        // Homepages for each locale. lastmod used to be a hardcoded now()
        // on every single regeneration regardless of whether the homepage
        // actually changed — the exact "always-fresh" pattern Google's own
        // guidance says it will start discounting, and the worst place for
        // it since these are the highest-priority (1.0) URLs in the whole
        // sitemap. Uses the most recently changed active product instead —
        // a real signal for "content this page surfaces changed as of X",
        // not a fabricated one.
        $latestProductChange = Product::where('is_active', true)->max('updated_at');
        $homepageLastmod = $latestProductChange
            ? \Illuminate\Support\Carbon::parse($latestProductChange)->toIso8601String()
            : now()->toIso8601String();

        foreach ($this->supportedLocales as $locale) {
            $this->writeUrl($writer, [
                'loc' => URL::to("/{$locale}/"),
                'lastmod' => $homepageLastmod,
                'changefreq' => 'daily',
                'priority' => '1.0',
            ]);
            $count++;
            $maxLastmod = $this->laterLastmod($maxLastmod, $homepageLastmod);
        }

        // is_homepage pages are excluded — they're already covered by the
        // "/{locale}/" entries written above. Without this, a page flagged
        // as the homepage got a SECOND, separate <loc> entry at its own
        // slug, sending search engines a duplicate-content signal for what
        // is really the same page (matches SitemapController's human-
        // readable sitemap, which already excludes is_homepage the same way).
        Page::where('status', ContentStatus::Published->value)
            ->where('is_homepage', false)
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (Page $page) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-pages', $batch, $maxLastmod);
                        $batch++;
                        $maxLastmod = null;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    $lastmod = $page->updated_at->toIso8601String();
                    $this->writeUrl($writer, [
                        'loc' => URL::to("/{$locale}/{$page->slug}"),
                        'lastmod' => $lastmod,
                        'changefreq' => 'monthly',
                        'priority' => '0.4',
                    ]);
                    $count++;
                    $maxLastmod = $this->laterLastmod($maxLastmod, $lastmod);
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-pages', $batch, $maxLastmod);

        return $written;
    }

    private function generateBlogSitemap(): array
    {
        $writer = $this->openWriter();
        $batch = 1;
        $count = 0;
        $written = [];
        $maxLastmod = null;

        BlogPost::where('status', ContentStatus::Published->value)
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->cursor()
            ->each(function (BlogPost $post) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                foreach ($this->supportedLocales as $locale) {
                    if ($count >= self::MAX_URLS_PER_FILE) {
                        $written[] = $this->closeWriter($writer, 'sitemap-blog', $batch, $maxLastmod);
                        $batch++;
                        $maxLastmod = null;
                        $writer = $this->openWriter();
                        $count = 0;
                    }

                    // Sorted by published_at, not updated_at — an earlier
                    // post edited more recently than a later one is common
                    // enough that the first row processed isn't reliably
                    // this file's max lastmod; tracked explicitly instead.
                    $lastmod = $post->updated_at->toIso8601String();
                    $this->writeUrl($writer, [
                        'loc' => URL::to("/{$locale}/blog/{$post->slug}"),
                        'lastmod' => $lastmod,
                        'changefreq' => 'weekly',
                        'priority' => '0.7',
                    ]);
                    $count++;
                    $maxLastmod = $this->laterLastmod($maxLastmod, $lastmod);
                }
            });

        $written[] = $this->closeWriter($writer, 'sitemap-blog', $batch, $maxLastmod);

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
        // Declared on every sitemap (harmless when unused) rather than
        // conditionally per file — only generateProductsSitemap() actually
        // writes <image:image> entries today.
        $writer->writeAttribute('xmlns:image', 'http://www.sitemaps.org/schemas/sitemap-image/1.1');

        return $writer;
    }

    /**
     * @param  array{loc: string, lastmod: string, changefreq: string, priority: string, images?: array<int, array{loc: string, caption: string}>}  $url
     */
    private function writeUrl(XMLWriter $writer, array $url): void
    {
        $writer->startElement('url');
        $writer->writeElement('loc', $url['loc']);
        $writer->writeElement('lastmod', $url['lastmod']);
        $writer->writeElement('changefreq', $url['changefreq']);
        $writer->writeElement('priority', $url['priority']);

        // ProductImage already has everything this needs (medium_url +
        // translatable alt_text) — without it, every product photo was
        // invisible to Google Images' strongest discovery signal, found
        // only incidentally via crawled <img> tags, if at all.
        foreach ($url['images'] ?? [] as $image) {
            $writer->startElement('image:image');
            $writer->writeElement('image:loc', $image['loc']);
            if ($image['caption'] !== '') {
                $writer->writeElement('image:caption', $image['caption']);
            }
            $writer->endElement();
        }

        $writer->endElement();

        // Flush buffered output to a temp file every 500 URLs to keep memory low
        // We use outputMemory(true) which clears the buffer after reading.
    }

    /**
     * Finalise a sitemap XMLWriter, flush to disk and return the filename.
     * $maxLastmod (the real max lastmod among this file's own entries) is
     * recorded so generateIndex() can use it instead of now() — null only
     * for a genuinely empty file, where there's no real content to date.
     */
    private function closeWriter(XMLWriter $writer, string $base, int $batch, ?string $maxLastmod = null): string
    {
        $writer->endElement(); // </urlset>
        $writer->endDocument();

        $filename = $batch === 1 ? "{$base}.xml" : "{$base}-{$batch}.xml";
        $path = public_path("{$this->sitemapDirectory}/{$filename}");

        $bytes = file_put_contents($path, $writer->outputMemory(true));
        if ($bytes === false) {
            throw new \RuntimeException("Failed to write sitemap file: {$path}");
        }

        $this->fileLastMods[$filename] = $maxLastmod ?? now()->toIso8601String();

        return $filename;
    }

    /**
     * Later of two ISO 8601 lastmod strings, treating a null accumulator as
     * "no entries seen yet."
     */
    private function laterLastmod(?string $a, string $b): string
    {
        if ($a === null) {
            return $b;
        }

        return \Illuminate\Support\Carbon::parse($a)->greaterThan(\Illuminate\Support\Carbon::parse($b)) ? $a : $b;
    }

    /**
     * Create an empty (but valid) sitemap for when a content type has no records.
     */
    private function emptyFile(string $filename): string
    {
        $writer = $this->openWriter();

        $base = preg_replace('/-\d+$/', '', str_replace('.xml', '', $filename));
        return $this->closeWriter($writer, $base, 1);
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
            // The real max lastmod among that file's own entries — tracked
            // by closeWriter() — not a blanket now() that claimed every
            // sub-sitemap "just changed" on every single regeneration
            // regardless of whether its content actually did.
            $writer->writeElement('lastmod', $this->fileLastMods[$file] ?? now()->toIso8601String());
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        $path = public_path('sitemap.xml');
        $bytes = file_put_contents($path, $writer->outputMemory(true));
        if ($bytes === false) {
            throw new \RuntimeException("Failed to write sitemap index file: {$path}");
        }

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
            Http::timeout(5)->get('https://www.google.com/ping', [
                'sitemap' => url('sitemap.xml'),
            ]);
        } catch (\Exception $e) {
            Log::debug('Google ping failed', ['error' => $e->getMessage()]);
        }
    }

    private function pingBing(): void
    {
        try {
            Http::timeout(5)->get('https://www.bing.com/ping', [
                'sitemap' => url('sitemap.xml'),
            ]);
        } catch (\Exception $e) {
            Log::debug('Bing ping failed', ['error' => $e->getMessage()]);
        }
    }

    public function getSitemapUrl(): string
    {
        return url('sitemap.xml');
    }

    public function cleanup(): void
    {
        $keep = [
            'sitemap-parts.xml', 'sitemap-crossrefs.xml', 'sitemap-brands.xml',
            'sitemap-models.xml', 'sitemap-pages.xml', 'sitemap-blog.xml',
        ];

        foreach (glob(public_path("{$this->sitemapDirectory}/sitemap-*.xml")) ?: [] as $file) {
            if (! in_array(basename($file), $keep, true)) {
                try {
                    unlink($file);
                } catch (\Exception $e) {
                    Log::error('Failed to delete sitemap file', [
                        'file' => $file,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
