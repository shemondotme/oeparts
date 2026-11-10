<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Filament\Pages\Settings\SeoControlCenter;
use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\CarModelResource;
use App\Filament\Resources\ManufacturerResource;
use App\Filament\Resources\NotFoundLogResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\RedirectResource;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\SeoMeta;
use App\Services\Instance\SafeMode;
use App\Support\PublicBaseUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The SEO audit: one list of concrete, fixable problems — each with how
 * serious it is, how many things are affected, a sample of them and a link
 * to where it gets fixed. Database and configuration only (no HTTP calls to
 * the site or to Google), so it is quick and safe to open at any time.
 *
 * Severities: critical = takes the site (or a whole section) out of search or
 * sends visitors/crawlers nowhere; warning = costs rankings or clicks;
 * info = worth knowing / an opportunity; ok = checked and fine.
 */
final class SeoAuditService
{
    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const INFO = 'info';

    public const OK = 'ok';

    private const SAMPLE = 10;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly RedirectLoopDetector $loops,
    ) {}

    /**
     * @return array{
     *     checks: list<array<string, mixed>>,
     *     summary: array{critical:int, warning:int, info:int, ok:int}
     * }
     */
    public function run(bool $skipHeavy = false): array
    {
        $checks = array_values(array_filter([
            ...$this->configurationChecks(),
            ...$this->contentChecks($skipHeavy),
            ...$this->linkChecks(),
            ...$this->indexingChecks(),
        ]));

        $summary = [self::CRITICAL => 0, self::WARNING => 0, self::INFO => 0, self::OK => 0];
        foreach ($checks as $check) {
            $summary[$check['severity']]++;
        }

        return ['checks' => $checks, 'summary' => $summary];
    }

    // ── Configuration ─────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function configurationChecks(): array
    {
        $group = 'Configuration';
        $checks = [];
        $isCopy = app(SafeMode::class)->isCopy();
        $controlCenter = SeoControlCenter::getUrl();

        if (! $isCopy) {
            $checks[] = app()->environment('production')
                ? $this->ok('env', $group, 'Site is in production mode')
                : $this->check('env', $group, self::CRITICAL, 'The site is not in production mode', 'APP_ENV is "'.app()->environment().'", so robots.txt tells every search engine to stay away (Disallow: /).', null, 'Set APP_ENV=production in the live .env file.');

            $checks[] = PublicBaseUrl::isLocalHost((string) config('app.url'))
                ? $this->check('app_url', $group, self::CRITICAL, 'APP_URL points at a local address', 'APP_URL is "'.config('app.url').'". Emails, the sitemap and robots.txt would advertise that address.', null, 'Set APP_URL in the live .env to the real https:// domain.')
                : $this->ok('app_url', $group, 'APP_URL is a public address');
        }

        $defaultRobots = strtolower((string) $this->settings->get('seo.default_robots', 'index,follow'));
        $checks[] = str_contains($defaultRobots, 'noindex')
            ? $this->check('default_robots', $group, self::CRITICAL, 'Every page is set to "noindex" by default', 'The default robots directive is "'.$defaultRobots.'", which is applied to every page that has no setting of its own — i.e. most of the site.', null, 'Set the Default Robots Directive back to "Index, Follow".', $controlCenter.'?tab=4', 'Open Crawlers & AI')
            : $this->ok('default_robots', $group, 'Default robots directive allows indexing');

        $sitemap = public_path('sitemap.xml');
        if (! is_file($sitemap)) {
            $checks[] = $this->check('sitemap', $group, self::WARNING, 'No sitemap has been generated yet', 'Search engines find new pages much faster with a sitemap.', null, 'Generate it from Sitemap & Indexing.', $controlCenter.'?tab=5', 'Open Sitemap & Indexing');
        } elseif (preg_match('#<loc>https?://(localhost|127\.0\.0\.1)#i', (string) @file_get_contents($sitemap, false, null, 0, 4096)) === 1) {
            $checks[] = $this->check('sitemap', $group, self::CRITICAL, 'The sitemap lists localhost addresses', 'sitemap.xml was generated while the site address was wrong, so every URL in it is unusable for search engines.', null, 'Fix APP_URL, then regenerate the sitemap.', $controlCenter.'?tab=5', 'Open Sitemap & Indexing');
        } elseif (filemtime($sitemap) < now()->subDays(3)->getTimestamp()) {
            $checks[] = $this->check('sitemap', $group, self::WARNING, 'The sitemap is more than 3 days old', 'It should regenerate every night. If it has not, the scheduler (cron) is probably not running.', null, 'Check that the scheduler runs, or regenerate now.', $controlCenter.'?tab=5', 'Open Sitemap & Indexing');
        } else {
            $checks[] = $this->ok('sitemap', $group, 'The sitemap is present and recent');
        }

        if (app()->environment('production') && ! str_starts_with((string) config('app.url'), 'https://')) {
            $checks[] = $this->check('https', $group, self::WARNING, 'The site address is not https', 'Search engines prefer https and browsers mark http sites "not secure".', null, 'Install a certificate and set APP_URL to the https:// address.');
        }

        if (app()->environment('production') && trim((string) $this->settings->get('seo.canonical_host', '')) === '') {
            $checks[] = $this->check('canonical_host', $group, self::INFO, 'No canonical host is set', 'Without one, the site can answer on both "www." and the bare domain, splitting its ranking between two addresses.', null, 'Set the Canonical Host once DNS and the certificate cover it.', $controlCenter.'?tab=5', 'Open Sitemap & Indexing');
        }

        $gsc = app(GoogleSearchConsoleService::class);
        $checks[] = $gsc->isConfigured()
            ? $this->ok('gsc', $group, 'Google Search Console is connected')
            : $this->check('gsc', $group, self::INFO, 'Google Search Console is not connected', 'Connecting it shows real clicks, impressions and indexing problems, and lets the sitemap be submitted automatically.', null, 'Add the Search Console credentials.', $controlCenter.'?tab=3', 'Open Structured Data & Integrations');

        return $checks;
    }

    // ── Content ───────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function contentChecks(bool $skipHeavy = false): array
    {
        $group = 'Content';
        $checks = [];

        $pages = Page::query()->where('status', ContentStatus::Published->value)->where('is_homepage', false)->get(['id', 'title', 'slug', 'meta_title', 'meta_description']);
        $posts = BlogPost::query()->where('status', ContentStatus::Published->value)->get(['id', 'title', 'slug', 'meta_title', 'meta_description', 'featured_image_id']);

        // Pages and posts with no hand-written search text. (Blog posts fall back to the excerpt/content for the
        // description, so a missing description is a smaller problem than a missing title — both are listed.)
        $missing = [];
        foreach ($pages as $page) {
            if ($this->en($page->meta_title) === '' || $this->en($page->meta_description) === '') {
                $missing[] = ['label' => 'Page: '.$this->label($page->title, $page->slug), 'url' => PageResource::getUrl('edit', ['record' => $page->id])];
            }
        }
        foreach ($posts as $post) {
            if ($this->en($post->meta_title) === '' || $this->en($post->meta_description) === '') {
                $missing[] = ['label' => 'Post: '.$this->label($post->title, $post->slug), 'url' => BlogPostResource::getUrl('edit', ['record' => $post->id])];
            }
        }
        $checks[] = $missing === []
            ? $this->ok('meta_missing', $group, 'Every page and post has its own search title and description')
            : $this->check('meta_missing', $group, self::WARNING, 'Pages or posts without their own search title / description', 'Google then writes the snippet itself, usually worse than one you wrote. Open each and fill in the SEO tab (the search preview shows how it will look).', count($missing), 'Open the item and use the SEO & Meta section.', null, null, array_slice($missing, 0, self::SAMPLE));

        // Custom titles/descriptions that will be cut off or are too thin to be useful.
        $length = [];
        $addLength = function (string $kind, $resourceClass, $record, array $titleField, array $descField, ?string $name) use (&$length) {
            $title = $this->en($titleField);
            $description = $this->en($descField);
            if (($title !== '' && (mb_strlen($title) > 60 || mb_strlen($title) < 30)) || ($description !== '' && (mb_strlen($description) > 160 || mb_strlen($description) < 70))) {
                $length[] = ['label' => $kind.': '.$name, 'url' => $resourceClass::getUrl('edit', ['record' => $record->id])];
            }
        };
        foreach ($pages as $page) {
            $addLength('Page', PageResource::class, $page, (array) $page->meta_title, (array) $page->meta_description, $this->label($page->title, $page->slug));
        }
        foreach ($posts as $post) {
            $addLength('Post', BlogPostResource::class, $post, (array) $post->meta_title, (array) $post->meta_description, $this->label($post->title, $post->slug));
        }
        $brandRows = Manufacturer::query()->where('is_active', true)->get(['id', 'name', 'slug', 'meta_title', 'meta_description', 'intro']);
        foreach ($brandRows as $brand) {
            $addLength('Brand', ManufacturerResource::class, $brand, (array) $brand->meta_title, (array) $brand->meta_description, $this->label($brand->name, $brand->slug));
        }
        if ($length !== []) {
            $checks[] = $this->check('meta_length', $group, self::INFO, 'Search titles or descriptions of the wrong length', 'A title over 60 or a description over 160 characters is cut off in the results; very short ones waste the space. The SEO section shows a live length meter.', count($length), 'Adjust the text until the meters turn green.', null, null, array_slice($length, 0, self::SAMPLE));
        }

        // The same hand-written title on several pages: they compete with each other.
        $byTitle = [];
        foreach ([['Page', PageResource::class, $pages, 'title', 'slug'], ['Post', BlogPostResource::class, $posts, 'title', 'slug'], ['Brand', ManufacturerResource::class, $brandRows, 'name', 'slug']] as [$kind, $resource, $set, $nameField, $slugField]) {
            foreach ($set as $record) {
                $title = mb_strtolower($this->en((array) $record->meta_title));
                if ($title !== '') {
                    $byTitle[$title][] = ['label' => $kind.': '.$this->label($record->{$nameField}, $record->{$slugField}), 'url' => $resource::getUrl('edit', ['record' => $record->id])];
                }
            }
        }
        $duplicates = [];
        foreach ($byTitle as $title => $rows) {
            if (count($rows) > 1) {
                foreach ($rows as $row) {
                    $duplicates[] = ['label' => $row['label'].'  —  "'.\Illuminate\Support\Str::limit($title, 50).'"', 'url' => $row['url']];
                }
            }
        }
        if ($duplicates !== []) {
            $checks[] = $this->check('meta_duplicate', $group, self::WARNING, 'Several pages share the same search title', 'Two pages with the same title compete for the same search; Google may show only one.', count($duplicates), 'Give each page its own title.', null, null, array_slice($duplicates, 0, self::SAMPLE));
        }

        $noImage = $posts->filter(fn (BlogPost $p) => ! $p->featured_image_id)->map(fn (BlogPost $p) => ['label' => 'Post: '.$this->label($p->title, $p->slug), 'url' => BlogPostResource::getUrl('edit', ['record' => $p->id])])->values()->all();
        if ($noImage !== []) {
            $checks[] = $this->check('blog_no_image', $group, self::INFO, 'Blog posts without a featured image', 'The image is used when the post is shared and for the article rich result.', count($noImage), 'Pick a featured image for each post.', null, null, array_slice($noImage, 0, self::SAMPLE));
        }

        // Brand and car-model landing pages with no text of their own.
        $brandsNoIntro = $brandRows->filter(fn (Manufacturer $b) => $this->en((array) $b->intro) === '');
        $modelsNoIntroCount = CarModel::query()->where('is_active', true)->whereHas('manufacturer', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q->whereNull('intro')->orWhere('intro', '')->orWhere('intro', '[]')->orWhere('intro', '{}'))->count();
        if ($brandsNoIntro->isNotEmpty()) {
            $checks[] = $this->check('brand_intro', $group, self::INFO, 'Brand pages without an introduction', 'A brand page that is only a parts list is "thin" content. A few sentences of real text help it rank for "{brand} parts".', $brandsNoIntro->count(), 'Write an introduction in the brand\'s "Brand page text" section.', null, null,
                $brandsNoIntro->take(self::SAMPLE)->map(fn (Manufacturer $b) => ['label' => 'Brand: '.$this->label($b->name, $b->slug), 'url' => ManufacturerResource::getUrl('edit', ['record' => $b->id])])->values()->all());
        }
        if ($modelsNoIntroCount > 0) {
            $sample = CarModel::query()->where('is_active', true)->whereHas('manufacturer', fn ($q) => $q->where('is_active', true))
                ->where(fn ($q) => $q->whereNull('intro')->orWhere('intro', '')->orWhere('intro', '[]')->orWhere('intro', '{}'))
                ->orderBy('id')->limit(self::SAMPLE)->get(['id', 'name', 'slug']);
            $checks[] = $this->check('model_intro', $group, self::INFO, 'Car-model pages without an introduction', 'Same as for brands: real text turns a bare parts list into a page worth ranking.', $modelsNoIntroCount, 'Write an introduction in the model\'s "Model page text" section.', null, null,
                $sample->map(fn (CarModel $m) => ['label' => 'Model: '.$m->name, 'url' => CarModelResource::getUrl('edit', ['record' => $m->id])])->all());
        }

        // Active parts with no photo (counted, cached — the catalog can be huge). Skipped by the
        // scheduled run: a count over the whole catalog is not something to repeat every night.
        // Measured on a 1,000,000-part catalog: ~7 s cold. So the nightly run (seo:audit) fills the cache off-request
        // (warmCatalogCounts) and the page just reads it; a page opened before the first nightly run pays the cost once.
        $withoutImage = $skipHeavy ? Cache::get(self::IMAGES_CACHE_KEY) : Cache::remember(self::IMAGES_CACHE_KEY, self::IMAGES_CACHE_TTL, fn (): int => $this->countProductsWithoutImage());
        $checks[] = $withoutImage === null
            ? null
            : ($withoutImage === 0
            ? $this->ok('product_images', $group, 'Every active part has a photo')
            : $this->check('product_images', $group, self::INFO, 'Active parts without a photo', 'Pages with a photo get more clicks, and the photo is what is shown when the part is shared.', $withoutImage, 'Add photos (CSV import supports image_urls).'));

        return array_values(array_filter($checks));
    }

    // ── Links inside content ──────────────────────────────────────────────

    /** The catalog-wide "active parts without a photo" count: slow on a big catalog, so cached for a day. */
    public const IMAGES_CACHE_KEY = 'seo-audit:products-without-image';

    private const IMAGES_CACHE_TTL = 26 * 3600;

    private function countProductsWithoutImage(): int
    {
        return Product::query()->where('is_active', true)->whereDoesntHave('images')->count();
    }

    /** Called by the nightly run: does the slow catalog counts now, so the audit page opens instantly. */
    public function warmCatalogCounts(): void
    {
        Cache::put(self::IMAGES_CACHE_KEY, $this->countProductsWithoutImage(), self::IMAGES_CACHE_TTL);
    }

    /** Cache key of the (comparatively slow) link scan — cleared by the page's "run again". */
    public const LINKS_CACHE_KEY = 'seo-audit:links';

    /** @return list<array<string, mixed>> */
    private function linkChecks(): array
    {
        $group = 'Links';
        $checks = [];

        $scan = Cache::remember(self::LINKS_CACHE_KEY, 600, fn (): array => app(LinkAuditService::class)->scan());

        $toItems = fn (array $rows): array => array_map(
            fn (array $r): array => ['label' => $r['source'].'  →  '.$r['href'].'  —  '.$r['reason'], 'url' => $r['edit_url']],
            array_slice($rows, 0, self::SAMPLE)
        );

        $checks[] = $scan['broken'] === []
            ? $this->ok('links_broken', $group, 'No broken internal links in pages and posts ('.$scan['links'].' checked)')
            : $this->check('links_broken', $group, self::WARNING, 'Broken internal links in pages and posts', 'These links lead to a 404 on this site. Visitors hit a dead end and search engines lose the trail.', count($scan['broken']), 'Open the page or post and correct or remove the link.', null, null, $toItems($scan['broken']));

        if ($scan['redirected'] !== []) {
            $checks[] = $this->check('links_redirected', $group, self::INFO, 'Links that only work through a redirect', 'The visitor is sent on, but every hop is a little slower and passes a little less ranking.', count($scan['redirected']), 'Point each link straight at the final address.', null, null, $toItems($scan['redirected']));
        }

        $checks[] = $scan['orphans'] === []
            ? $this->ok('orphan_pages', $group, 'Every page is linked from somewhere')
            : $this->check('orphan_pages', $group, self::INFO, 'Pages and posts that are hard to reach', 'A page with no link to it — or an old blog post that sits deep in the index and that nothing links to — is hard for visitors and crawlers to find; the sitemap alone is a weak signal. Link it from a related page or post, or add a page to the header or footer menu.', count($scan['orphans']), 'Link to it from related content, or tick "Show in navigation" on the page.', null, null,
                array_map(fn (array $o): array => ['label' => $o['label'], 'url' => $o['edit_url']], array_slice($scan['orphans'], 0, self::SAMPLE)));

        if ($scan['external'] > 0) {
            $last = ExternalLinkChecker::last();

            if ($last === null) {
                $checks[] = $this->check('links_external', $group, self::INFO, 'Links to other websites have not been checked yet', 'This page does not fetch other sites (that is slow). They are checked once a week in the background — or press "Check external links now" at the top.', $scan['external'], 'Run the check.');
            } elseif ($last['broken'] === []) {
                $checks[] = $this->ok('links_external', $group, 'No broken links to other websites ('.$last['checked'].' checked '.Carbon::parse($last['checked_at'])->diffForHumans().($last['unverified'] > 0 ? ', '.$last['unverified'].' could not be verified — sites that block bots' : '').')');
            } else {
                $checks[] = $this->check('links_external', $group, self::WARNING, 'Broken links to other websites', 'These addresses no longer work (checked '.Carbon::parse($last['checked_at'])->diffForHumans().'). Visitors who follow them hit a dead end, which reflects on your content.', count($last['broken']), 'Open the page or post and fix or remove the link.', null, null,
                    array_map(fn (array $r): array => ['label' => ($r['sources'][0]['label'] ?? '').'  →  '.$r['href'].'  —  '.$r['reason'], 'url' => $r['sources'][0]['edit_url'] ?? null], array_slice($last['broken'], 0, self::SAMPLE)));
            }
        }

        return $checks;
    }

    // ── Indexing, 404s and redirects ──────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function indexingChecks(): array
    {
        $group = 'Indexing & redirects';
        $checks = [];

        $noindex = SeoMeta::query()->where('robots', 'like', '%noindex%')->orderBy('id')->get(['id', 'metable_type', 'metable_id', 'robots']);
        if ($noindex->isNotEmpty()) {
            $checks[] = $this->check('noindex', $group, self::INFO, 'Items set to "No Index" in SEO Meta', 'These are deliberately kept out of search (and out of the sitemap). Make sure that is still what you want.', $noindex->count(), 'Review them.', null, null,
                $noindex->take(self::SAMPLE)->map(fn (SeoMeta $m) => ['label' => class_basename((string) $m->metable_type).' #'.$m->metable_id.' ('.$m->robots.')', 'url' => $this->editUrlFor($m)])->values()->all());
        }

        $open404 = NotFoundLog::query()->where('resolved', false)->count();
        $checks[] = $open404 === 0
            ? $this->ok('not_found', $group, 'No unresolved 404 errors')
            : $this->check('not_found', $group, $open404 > 50 ? self::WARNING : self::INFO, 'Unresolved 404 errors', 'Visitors or crawlers requested addresses that do not exist. Each one that has inbound links or history is lost traffic.', $open404, 'Create a redirect or mark them resolved.', NotFoundLogResource::getUrl(), 'Open the 404 log');

        $activeRedirects = Redirect::query()->where('is_active', true)->count();
        if ($activeRedirects <= 5000) {
            $loops = $this->loops->findAllLoops();
            $checks[] = $loops === []
                ? $this->ok('redirect_loops', $group, 'No redirect loops')
                : $this->check('redirect_loops', $group, self::CRITICAL, 'Redirect loops', 'A redirect that leads back to itself makes the page unreachable (browsers give up with an error).', count($loops), 'Correct or delete these redirects.', RedirectResource::getUrl(), 'Open Redirects', array_map(fn (string $from) => ['label' => '/'.$from, 'url' => RedirectResource::getUrl()], array_slice($loops, 0, self::SAMPLE)));
        }

        // A → B → C: crawlers lose a little each hop. Self-join on relative targets ("/en/x" → from_url "en/x").
        $chainRows = DB::table('redirects as r1')
            ->join('redirects as r2', 'r2.from_url', '=', DB::raw('LOWER(SUBSTR(r1.to_url, 2))'))
            ->where('r1.is_active', true)->where('r2.is_active', true)
            ->whereRaw("r1.to_url LIKE '/%'")
            ->whereColumn('r1.id', '!=', 'r2.id')
            // A→B→A is a loop (reported above as critical), not a chain.
            ->whereRaw('LOWER(SUBSTR(r2.to_url, 2)) != r1.from_url')
            ->limit(500)
            ->get(['r1.from_url as from_url', 'r1.to_url as to_url', 'r2.to_url as final_url']);
        if ($chainRows->isNotEmpty()) {
            $checks[] = $this->check('redirect_chains', $group, self::WARNING, 'Redirect chains', 'A redirect that points at another redirect. The site follows the chain in one step for visitors, but pointing the first one straight at the final page is cleaner.', $chainRows->count(), 'Edit the first redirect to point at the final address.', RedirectResource::getUrl(), 'Open Redirects',
                $chainRows->take(self::SAMPLE)->map(fn ($r) => ['label' => '/'.$r->from_url.'  →  '.$r->to_url.'  →  '.$r->final_url, 'url' => RedirectResource::getUrl()])->all());
        }

        return $checks;
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** The English value of a translatable field ('' when absent). */
    private function en(array|string|null $field): string
    {
        return is_string($field) ? trim($field) : trim((string) ($field['en'] ?? ''));
    }

    private function label(array|string|null $name, string $fallback): string
    {
        return $this->en($name) ?: $fallback;
    }

    private function editUrlFor(SeoMeta $meta): ?string
    {
        $resource = match ($meta->metable_type) {
            Page::class => PageResource::class,
            BlogPost::class => BlogPostResource::class,
            Product::class => \App\Filament\Resources\ProductResource::class,
            default => null,
        };

        return $resource ? $resource::getUrl('edit', ['record' => $meta->metable_id]) : null;
    }

    /** @return array<string, mixed> */
    private function ok(string $id, string $group, string $title): array
    {
        return $this->check($id, $group, self::OK, $title, '', null, '');
    }

    /**
     * @param  list<array{label:string, url:?string}>  $items
     * @return array<string, mixed>
     */
    private function check(string $id, string $group, string $severity, string $title, string $detail, ?int $count, string $howToFix, ?string $fixUrl = null, ?string $fixLabel = null, array $items = []): array
    {
        return [
            'id' => $id,
            'group' => $group,
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'count' => $count,
            'how_to_fix' => $howToFix,
            'fix_url' => $fixUrl,
            'fix_label' => $fixLabel,
            'items' => $items,
        ];
    }
}
