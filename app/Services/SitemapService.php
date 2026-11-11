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
use App\Models\SeoMeta;
use App\Support\LocaleRegistry;
use App\Support\PublicBaseUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    /** Rotate well before Google's 50 MB (uncompressed) limit; sizes are estimated while writing. */
    private const MAX_BYTES_PER_FILE = 40_000_000;

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
        private GoogleSearchConsoleService $searchConsole,
    ) {
        $this->supportedLocales = LocaleRegistry::codes();
    }

    /**
     * Generate all sitemaps and the master index.
     *
     * Two independent triggers call this with no coordination between them
     * — the daily `sitemap:generate` schedule and the SEO Control Center's
     * "Regenerate Sitemap" admin button (via the queued RegenerateSitemap
     * job) — both writing the SAME fixed set of files
     * (public/sitemaps/*.xml, public/sitemap.xml). Without a shared lock,
     * an admin clicking that button at (or near) the scheduled time could
     * genuinely race the scheduled run: both processes writing to the same
     * files concurrently, with no guarantee either one's writes land
     * atomically or in order — a real risk of a corrupted or internally
     * inconsistent sitemap (e.g. the index referencing a sub-sitemap file
     * mid-write from the OTHER process). A non-blocking lock means the
     * second caller fails fast and cleanly instead of silently racing.
     *
     * @return array List of generated file basenames
     *
     * @throws \RuntimeException if another generation is already in progress
     */
    public function generateAll(): array
    {
        // Resolved BEFORE taking the lock: a rejected (local) address must
        // fail fast without ever holding or leaking the generation lock.
        $baseUrl = PublicBaseUrl::resolve($this->settings);

        $lock = Cache::lock('sitemap:generate:lock', 3700);

        if (! $lock->get()) {
            throw new \RuntimeException('Sitemap generation is already in progress — skipped this run.');
        }

        // These files are static and every <loc> in them is baked in at
        // generation time. A queue worker / scheduler runs in the CLI, where
        // url()/route()/asset() fall back to config('app.url') — which on a
        // misconfigured server is still http://localhost. Pin one verified
        // public base URL for the whole run (and for the storage URLs used
        // by <image:loc>), and refuse to publish a local address at all.
        $previousRoot = url('/');
        $previousDiskUrl = config('filesystems.disks.public.url');
        $previousAppUrl = config('app.url');

        URL::forceRootUrl($baseUrl);
        if (str_starts_with($baseUrl, 'https://')) {
            URL::forceScheme('https');
        }
        config([
            'app.url' => $baseUrl,
            'filesystems.disks.public.url' => $baseUrl.'/storage',
        ]);
        Storage::forgetDisk('public');

        try {
            $this->ensureDirectory();

            $files = [];

            // Each content group can be switched off (SEO Control Center →
            // Sitemap & Indexing). A group that is off is not generated AND
            // its files from earlier runs are removed, so a stale file can
            // never keep advertising URLs the admin took out.
            $groups = [
                'products' => ['sitemap-parts', fn () => $this->generateProductsSitemap()],
                'crossrefs' => ['sitemap-crossrefs', fn () => $this->generateCrossReferencesSitemap()],
                'brands' => ['sitemap-brands', fn () => $this->generateManufacturersSitemap()],
                'models' => ['sitemap-models', fn () => $this->generateCarModelsSitemap()],
                'pages' => ['sitemap-pages', fn () => $this->generatePagesSitemap()],
                'blog' => ['sitemap-blog', fn () => $this->generateBlogSitemap()],
            ];

            foreach ($groups as $key => [$base, $generate]) {
                if (filter_var($this->settings->get("seo.sitemap_include_{$key}", true), FILTER_VALIDATE_BOOLEAN)) {
                    array_push($files, ...$generate());
                } else {
                    $this->removeSitemapFiles($base);
                }
            }

            $indexPath = $this->generateIndex($files);

            // filter_var: admin-saved booleans persist as the literal
            // string 'false' (PHP-truthy) — a bare truthy check on the raw
            // settings value never actually disabled this ping.
            // Setting key kept as 'google_ping_enabled' (no data migration);
            // it now gates the Search Console submission.
            if (filter_var($this->settings->get('seo.google_ping_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
                $this->notifySearchConsole();
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
                    array_map(fn (string $file) => URL::asset("{$this->sitemapDirectory}/{$file}"), $files)
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
        } finally {
            URL::forceRootUrl($previousRoot === '' ? null : $previousRoot);
            config([
                'app.url' => $previousAppUrl,
                'filesystems.disks.public.url' => $previousDiskUrl,
            ]);
            Storage::forgetDisk('public');
            $lock->release();
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
        $detailPagesEnabled = filter_var($this->settings->get('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN);

        // Products an admin set to noindex in SEO Meta. That override only
        // applies to the per-product DETAIL page (the hub page never reads
        // it), so it only matters when a single-product OEM is listed by its
        // detail URL below. A small set in practice, loaded once.
        $noIndexProductIds = $detailPagesEnabled
            ? array_flip(SeoMeta::where('metable_type', Product::class)
                ->where('robots', 'like', '%noindex%')
                ->pluck('metable_id')
                ->all())
            : [];

        Product::where('is_active', true)
            ->selectRaw('normalized_oem, MAX(updated_at) as updated_at, MAX(id) as id, COUNT(*) as product_count')
            ->groupBy('normalized_oem')
            ->orderByDesc('updated_at')
            ->cursor()
            ->each(function (Product $product) use (&$written, &$batch, &$writer, &$count, &$maxLastmod, $detailPagesEnabled, $noIndexProductIds) {
                // One extra query per DISTINCT oem (not per row) — the same
                // accepted trade-off generateCrossReferencesSitemap() already
                // makes for its own per-group lookup.
                $images = $this->sitemapImages((int) $product->id);

                // Hub+Detail mode: an OEM with exactly ONE active product
                // 301s from the hub URL to that product's detail page
                // (SearchController::results()), so the hub URL must not be
                // listed — the detail URL is the real, canonical, indexable
                // page. Multi-product OEMs stay on the hub (no redirect).
                $detailProduct = null;
                if ($detailPagesEnabled && (int) $product->product_count === 1) {
                    if (isset($noIndexProductIds[(int) $product->id])) {
                        return; // its only public page is a noindex detail page
                    }

                    $detailProduct = Product::with(['manufacturer', 'condition'])->find($product->id);
                }

                $alternates = $this->alternatesFor(fn (string $l) => $detailProduct
                    ? URL::route('frontend.search.detail', [
                        'lang' => $l,
                        'oem' => $detailProduct->normalized_oem,
                        'idSlug' => $this->productSlugService->buildIdSlug($detailProduct, $l),
                    ])
                    : URL::route('frontend.search.results', ['lang' => $l, 'oem' => $product->normalized_oem]));

                foreach ($this->supportedLocales as $locale) {
                    if ($writer === null || $this->fileIsFull($count)) {
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
                        'loc' => $detailProduct
                            ? URL::route('frontend.search.detail', [
                                'lang' => $locale,
                                'oem' => $detailProduct->normalized_oem,
                                'idSlug' => $this->productSlugService->buildIdSlug($detailProduct, $locale),
                            ])
                            : URL::route('frontend.search.results', ['lang' => $locale, 'oem' => $product->normalized_oem]),
                        'lastmod' => $lastmod,
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                        'alternates' => $alternates,
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
     * @return Collection<int, ProductImage>
     */
    private function sitemapImages(int $productId): Collection
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
                $lastmod = $row->updated_at ? Carbon::parse($row->updated_at)->toIso8601String() : now()->toIso8601String();

                // For a single-active-product match in Hub+Detail mode,
                // point straight at the canonical detail URL — skips a
                // wasted redirect hop the hub URL would otherwise cost
                // (single-match auto-redirect in SearchController::results()).
                $activeProducts = Product::query()
                    ->whereIn('id', ProductCrossReference::where('normalized_cross_oem', $crossOem)->pluck('product_id'))
                    ->where('is_active', true)
                    ->get(['id', 'normalized_oem']);

                $singleProduct = ($detailPagesEnabled && $activeProducts->count() === 1) ? $activeProducts->first() : null;

                $alternates = $this->alternatesFor(fn (string $l) => $singleProduct
                    ? URL::route('frontend.search.detail', [
                        'lang' => $l,
                        'oem' => $singleProduct->normalized_oem,
                        'idSlug' => $this->productSlugService->buildIdSlug($singleProduct, $l),
                    ])
                    : URL::route('frontend.search.results', ['lang' => $l, 'oem' => $crossOem]));

                foreach ($this->supportedLocales as $locale) {
                    if ($singleProduct) {
                        $dedupeKey = $singleProduct->id.':'.$locale;
                        if (isset($emittedDetailUrls[$dedupeKey])) {
                            continue;
                        }
                        $emittedDetailUrls[$dedupeKey] = true;
                    }

                    if ($writer === null || $this->fileIsFull($count)) {
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
                        'alternates' => $alternates,
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
            ->whereNotExists($this->noIndexMeta('manufacturers', Manufacturer::class))
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (Manufacturer $manufacturer) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                $alternates = $this->alternatesFor(fn (string $l) => URL::route('frontend.manufacturer.show', ['lang' => $l, 'manufacturer' => $manufacturer->slug]));
                foreach ($this->supportedLocales as $locale) {
                    if ($this->fileIsFull($count)) {
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
                        'alternates' => $alternates,
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
            ->whereNotExists($this->noIndexMeta('car_models', CarModel::class))
            ->whereHas('manufacturer', fn ($q) => $q->where('is_active', true))
            ->with('manufacturer')
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (CarModel $model) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                if (! $model->manufacturer) {
                    return;
                }

                $alternates = $this->alternatesFor(fn (string $l) => URL::route('frontend.car-model.show', [
                    'lang' => $l, 'manufacturer' => $model->manufacturer->slug, 'model' => $model->slug,
                ]));
                foreach ($this->supportedLocales as $locale) {
                    if ($this->fileIsFull($count)) {
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
                        'alternates' => $alternates,
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
            ? Carbon::parse($latestProductChange)->toIso8601String()
            : now()->toIso8601String();

        $homeAlternates = $this->alternatesFor(fn (string $l) => SeoService::homeUrl($l));
        foreach ($this->supportedLocales as $locale) {
            $this->writeUrl($writer, [
                'alternates' => $homeAlternates,
                // No trailing slash — "/en/" 301s to "/en" (see SeoService::homeUrl()).
                'loc' => SeoService::homeUrl($locale),
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
        // Same visibility rule PageController::show() applies: published,
        // with a publish date that has arrived. Listing a scheduled page
        // (or one with no published_at) put URLs in the sitemap that 404.
        // Pages an admin marked noindex in SEO Meta are left out as well —
        // a sitemap entry for a noindex page is a contradictory signal.
        Page::where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('is_homepage', false)
            ->whereNotExists($this->noIndexMeta('pages', Page::class))
            ->orderBy('updated_at', 'desc')
            ->cursor()
            ->each(function (Page $page) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                $locales = $this->indexableLocales($page);
                $alternates = $this->alternatesFor(fn (string $l) => URL::to("/{$l}/{$page->slug}"), $locales);
                foreach ($locales as $locale) {
                    if ($this->fileIsFull($count)) {
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
                        'alternates' => $alternates,
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
            ->whereNotExists($this->noIndexMeta('blog_posts', BlogPost::class))
            ->orderByDesc('published_at')
            ->cursor()
            ->each(function (BlogPost $post) use (&$writer, &$batch, &$count, &$written, &$maxLastmod) {
                $locales = $this->indexableLocales($post);
                $alternates = $this->alternatesFor(fn (string $l) => URL::to("/{$l}/blog/{$post->slug}"), $locales);
                foreach ($locales as $locale) {
                    if ($this->fileIsFull($count)) {
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
                        'alternates' => $alternates,
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

    /** Running estimate of the open file's size — see fileIsFull(). */
    private int $estimatedBytes = 0;

    /**
     * A sitemap file may hold 50,000 URLs AND must stay under 50 MB uncompressed; with language alternates and
     * product photos on every entry the size limit is reached first, so a file is closed at either limit
     * (the byte limit with room to spare: the figure is an estimate).
     */
    private function fileIsFull(int $count): bool
    {
        return $count >= self::MAX_URLS_PER_FILE || $this->estimatedBytes >= self::MAX_BYTES_PER_FILE;
    }

    /**
     * hreflang alternates for one piece of content: [locale => url] plus x-default (the default locale's URL).
     * Empty with a single language — an annotation pointing only at itself adds nothing.
     *
     * @param  \Closure(string): string  $urlFor
     * @return array<string, string>
     */
    private function alternatesFor(\Closure $urlFor, ?array $locales = null): array
    {
        $locales ??= $this->supportedLocales;

        if (count($this->supportedLocales) < 2 || count($locales) < 2) {
            return [];
        }

        $alternates = [];
        foreach ($locales as $locale) {
            $alternates[$locale] = $urlFor($locale);
        }

        $default = LocaleRegistry::defaultCode();
        if (isset($alternates[$default])) {
            $alternates['x-default'] = $alternates[$default];
        }

        return $alternates;
    }

    /**
     * The languages a page / post may be listed in: all of them, minus those where the thin-content rule would
     * serve it with noindex (the sitemap must not contradict the page's own robots tag).
     *
     * @return array<int, string>
     */
    private function indexableLocales($entity): array
    {
        $seo = app(SeoService::class);

        return array_values(array_filter(
            $this->supportedLocales,
            fn (string $locale) => ! $seo->isThinContent($entity, $locale),
        ));
    }

    private function openWriter(): XMLWriter
    {
        $this->estimatedBytes = 0;

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
        $writer->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        return $writer;
    }

    /**
     * @param  array{loc: string, lastmod: string, changefreq: string, priority: string, images?: array<int, array{loc: string, caption: string}>}  $url
     */
    private function writeUrl(XMLWriter $writer, array $url): void
    {
        // Size estimate for fileIsFull(): the text written plus the markup around it.
        $bytes = 150 + strlen($url['loc']) + strlen($url['lastmod']);
        foreach ($url['alternates'] ?? [] as $hreflang => $href) {
            $bytes += 70 + strlen((string) $hreflang) + strlen($href);
        }
        foreach ($url['images'] ?? [] as $image) {
            $bytes += 90 + strlen($image['loc']) + strlen($image['caption']);
        }
        $this->estimatedBytes += $bytes;

        $writer->startElement('url');
        $writer->writeElement('loc', $url['loc']);
        $writer->writeElement('lastmod', $url['lastmod']);
        $writer->writeElement('changefreq', $url['changefreq']);
        $writer->writeElement('priority', $url['priority']);

        // Language alternates (every locale's URL for this same content, plus x-default). Each locale's own
        // <url> lists the full set, including itself — that is what makes the annotation reciprocal.
        foreach ($url['alternates'] ?? [] as $hreflang => $href) {
            $writer->startElement('xhtml:link');
            $writer->writeAttribute('rel', 'alternate');
            $writer->writeAttribute('hreflang', (string) $hreflang);
            $writer->writeAttribute('href', $href);
            $writer->endElement();
        }

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

        // generateAll() calls ensureDirectory() up front, but several unit
        // tests reflection-invoke a single generate*Sitemap() method
        // directly, bypassing that — this is the one chokepoint every
        // sitemap-writing path actually goes through, so it's the right
        // place to guarantee the directory exists rather than relying on
        // every caller to have created it first (or, worse, on it simply
        // having been left over from some earlier unrelated run).
        $this->ensureDirectory();

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

        return Carbon::parse($a)->greaterThan(Carbon::parse($b)) ? $a : $b;
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

    /**
     * Correlated "this row has a SEO Meta override that says noindex"
     * sub-query, for ->whereNotExists(). Entities an admin explicitly took
     * out of the index must not be advertised in the sitemap.
     */
    private function noIndexMeta(string $table, string $modelClass): \Closure
    {
        return fn ($q) => $q->selectRaw('1')
            ->from('seo_meta')
            ->whereColumn('seo_meta.metable_id', "{$table}.id")
            ->where('seo_meta.metable_type', $modelClass)
            ->where('seo_meta.robots', 'like', '%noindex%');
    }

    /** Deletes every file of one sitemap group (sitemap-parts.xml, sitemap-parts-2.xml, …). */
    private function removeSitemapFiles(string $base): void
    {
        foreach (glob(public_path("{$this->sitemapDirectory}/{$base}*.xml")) ?: [] as $file) {
            @unlink($file);
        }
    }

    private function ensureDirectory(): void
    {
        $path = public_path($this->sitemapDirectory);
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Google retired its anonymous sitemap "ping" endpoint (it 404s), so the
     * only supported way to tell Google about a regenerated sitemap is the
     * authenticated Search Console API. Bing retired its ping too — Bing and
     * other engines are notified per-URL through IndexNow instead. Best
     * effort: never fails sitemap generation, which already succeeded.
     */
    private function notifySearchConsole(): void
    {
        try {
            if (! $this->searchConsole->isConfigured()) {
                return;
            }

            $result = $this->searchConsole->submitSitemap(url('sitemap.xml'));

            if (isset($result['error'])) {
                Log::warning('Search Console sitemap submission failed', ['error' => $result['error']]);
            }
        } catch (\Throwable $e) {
            Log::warning('Search Console sitemap submission failed', ['error' => $e->getMessage()]);
        }
    }

    public function getSitemapUrl(): string
    {
        return url('sitemap.xml');
    }
}
