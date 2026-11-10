<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\RedirectType;
use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\PageResource;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\Redirect;
use App\Support\LocaleRegistry;

/**
 * Finds links inside page and blog content that lead nowhere, and content
 * that nothing links to.
 *
 * Only INTERNAL links are judged — an address is resolved against what the
 * site actually serves (published pages, blog posts, brands, models, parts,
 * the fixed sections). External links are counted but not fetched: checking
 * other people's servers needs network calls that are slow and flaky, which
 * does not belong in a page that must open instantly.
 *
 * Findings:
 *  - broken:     the link resolves to a 404 / 410 on this site
 *  - redirected: the link works only because a redirect rule catches it — point it at the final address
 *  - orphans:    published CMS pages with no link to them from any other content or from the menus
 */
final class LinkAuditService
{
    /** Sections that always exist, whatever the content (second URL segment after the language). */
    private const FIXED_SECTIONS = ['parts', 'brands', 'blog', 'contact', 'sitemap', 'impressum', 'cart', 'checkout', 'reset-password'];

    /** Root-level files and folders that are not language-prefixed. */
    private const ROOT_PATHS = ['robots.txt', 'sitemap.xml', 'llms.txt', 'favicon.ico', 'favicon.svg'];

    public function __construct(private readonly OemNormalizerService $normalizer) {}

    /** @var array<string, string|null> memo of resolved paths within one scan */
    private array $resolved = [];

    /**
     * @return array{
     *     scanned: int,
     *     links: int,
     *     external: int,
     *     broken: list<array{source: string, edit_url: string, href: string, reason: string}>,
     *     redirected: list<array{source: string, edit_url: string, href: string, reason: string}>,
     *     orphans: list<array{label: string, edit_url: string}>
     * }
     */
    public function scan(): array
    {
        $this->resolved = [];

        $sources = $this->sources();
        $broken = [];
        $redirected = [];
        $external = 0;
        $links = 0;

        // slug → true for every CMS page that some OTHER piece of content links to
        $linkedSlugs = [];

        foreach ($sources as $source) {
            foreach ($source['hrefs'] as $href) {
                $kind = $this->classify($href);
                if ($kind === 'skip') {
                    continue;
                }
                if ($kind === 'external') {
                    $external++;

                    continue;
                }

                $links++;
                [$problem, $slug] = $this->check($href);

                if ($slug !== null && $slug !== $source['page_slug']) {
                    $linkedSlugs[$slug] = true;
                }

                if ($problem === null) {
                    continue;
                }

                $row = ['source' => $source['label'], 'edit_url' => $source['edit_url'], 'href' => $href, 'reason' => $problem['reason']];
                if ($problem['type'] === 'broken') {
                    $broken[] = $row;
                } else {
                    $redirected[] = $row;
                }
            }
        }

        return [
            'scanned' => count($sources),
            'links' => $links,
            'external' => $external,
            'broken' => $broken,
            'redirected' => $redirected,
            'orphans' => $this->orphans($linkedSlugs),
        ];
    }

    // ── Sources ───────────────────────────────────────────────────────────

    /**
     * Every published page and post, with the links found in its text (all languages).
     *
     * @return list<array{label: string, edit_url: string, hrefs: list<string>, page_slug: ?string}>
     */
    private function sources(): array
    {
        $sources = [];

        foreach (Page::query()->where('status', ContentStatus::Published->value)->get(['id', 'title', 'slug', 'content']) as $page) {
            $sources[] = [
                'label' => 'Page: '.($this->en((array) $page->title) ?: $page->slug),
                'edit_url' => PageResource::getUrl('edit', ['record' => $page->id]),
                'hrefs' => $this->hrefsIn((array) $page->content),
                'page_slug' => $page->slug,
            ];
        }

        foreach (BlogPost::query()->where('status', ContentStatus::Published->value)->get(['id', 'title', 'slug', 'content', 'excerpt']) as $post) {
            $sources[] = [
                'label' => 'Post: '.($this->en((array) $post->title) ?: $post->slug),
                'edit_url' => BlogPostResource::getUrl('edit', ['record' => $post->id]),
                'hrefs' => $this->hrefsIn(array_merge((array) $post->content, (array) $post->excerpt)),
                'page_slug' => null,
            ];
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $perLocale  locale => html
     * @return list<string>
     */
    private function hrefsIn(array $perLocale): array
    {
        $found = [];

        foreach ($perLocale as $html) {
            if (! is_string($html) || $html === '') {
                continue;
            }

            if (preg_match_all('/<a\s[^>]*?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $html, $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $m) {
                    $href = trim(html_entity_decode($m[1] !== '' ? $m[1] : ($m[2] ?? ''), ENT_QUOTES | ENT_HTML5));
                    if ($href !== '') {
                        $found[$href] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    // ── Resolving one link ────────────────────────────────────────────────

    /** 'skip' (mail/tel/anchor…), 'external' (another site) or 'internal'. */
    private function classify(string $href): string
    {
        if ($href === '' || $href[0] === '#' || preg_match('/^(mailto|tel|sms|javascript|data):/i', $href) === 1) {
            return 'skip';
        }

        if (preg_match('#^(https?:)?//#i', $href) === 1) {
            $host = strtolower((string) parse_url(str_starts_with($href, '//') ? 'https:'.$href : $href, PHP_URL_HOST));

            return in_array($host, $this->ownHosts(), true) ? 'internal' : 'external';
        }

        return 'internal';
    }

    /** @return list<string> */
    private function ownHosts(): array
    {
        $hosts = [];
        foreach ([config('app.url'), settings('general.site_url', ''), settings('seo.canonical_host', '')] as $candidate) {
            $host = strtolower((string) parse_url(preg_match('#^https?://#i', (string) $candidate) === 1 ? (string) $candidate : 'https://'.$candidate, PHP_URL_HOST));
            if ($host !== '') {
                $hosts[$host] = true;
                $hosts[(str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host)] = true;
            }
        }

        return array_keys($hosts);
    }

    /**
     * @return array{0: ?array{type: 'broken'|'redirected', reason: string}, 1: ?string} [problem, CMS slug it points at]
     */
    private function check(string $href): array
    {
        $path = (string) (parse_url(preg_match('#^//#', $href) === 1 ? 'https:'.$href : $href, PHP_URL_PATH) ?? '');
        $path = '/'.trim(rawurldecode($path), '/');
        $key = strtolower($path);

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        return $this->resolved[$key] = $this->resolve($path);
    }

    /** @return array{0: ?array{type: string, reason: string}, 1: ?string} */
    private function resolve(string $path): array
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '' || in_array($trimmed, self::ROOT_PATHS, true) || str_starts_with($trimmed, 'storage/') || str_starts_with($trimmed, 'build/')) {
            return [null, null];
        }

        // A redirect rule that catches this exact address?
        $rule = Redirect::query()->where('is_active', true)->where('from_url', strtolower($trimmed))->first(['to_url', 'type']);
        if ($rule) {
            return $rule->type === RedirectType::Gone
                ? [['type' => 'broken', 'reason' => 'this address was retired on purpose (410 Gone)'], null]
                : [['type' => 'redirected', 'reason' => 'works only through a redirect to '.$rule->to_url.' — link to that address directly'], null];
        }

        $segments = explode('/', $trimmed);
        $lang = $segments[0];

        if (! in_array($lang, LocaleRegistry::codes(), true)) {
            return [['type' => 'broken', 'reason' => 'no language in the address (it must start with /en, /de, …), so it leads to a 404'], null];
        }

        $rest = array_slice($segments, 1);
        $first = $rest[0] ?? null;

        if ($first === null) {
            return [null, null]; // the home page
        }

        if (in_array($first, self::FIXED_SECTIONS, true) && count($rest) === 1) {
            return [null, null];
        }

        return match ($first) {
            'parts' => [$this->checkPart($rest), null],
            'blog' => [count($rest) === 2 ? $this->checkBlog($rest[1]) : (count($rest) === 1 ? null : $this->notFound()), null],
            'brand' => [$this->checkBrand($rest), null],
            'account', 'search', 'auth', 'orders', 'newsletter' => [null, null], // behind-login / action areas: not content
            default => count($rest) === 1 ? $this->checkPage($first) : [$this->notFound(), null],
        };
    }

    /** @return array{type: string, reason: string} */
    private function notFound(): array
    {
        return ['type' => 'broken', 'reason' => 'no such page on this site (404)'];
    }

    /** @param list<string> $rest @return ?array{type: string, reason: string} */
    private function checkPart(array $rest): ?array
    {
        if (count($rest) === 1) {
            return null;
        }

        $oem = $this->normalizer->normalize($rest[1]);

        if (count($rest) === 2) {
            $exists = Product::query()->where('is_active', true)->where('normalized_oem', $oem)->exists()
                || ProductCrossReference::query()->where('normalized_cross_oem', $oem)->exists();

            return $exists ? null : ['type' => 'broken', 'reason' => 'no active part with the number '.$oem.' (the page shows "not found")'];
        }

        if (count($rest) === 3 && preg_match('/^(\d+)-[a-z0-9\-]+$/', $rest[2], $m) === 1) {
            return Product::query()->where('is_active', true)->whereKey((int) $m[1])->exists()
                ? null
                : ['type' => 'broken', 'reason' => 'that part no longer exists or is inactive'];
        }

        return $this->notFound();
    }

    private function checkBlog(string $slug): ?array
    {
        return BlogPost::query()->where('slug', $slug)->published()->exists()
            ? null
            : ['type' => 'broken', 'reason' => 'no published blog post "'.$slug.'"'];
    }

    /** @param list<string> $rest @return ?array{type: string, reason: string} */
    private function checkBrand(array $rest): ?array
    {
        if (count($rest) < 2 || count($rest) > 3) {
            return $this->notFound();
        }

        $brand = Manufacturer::query()->where('slug', $rest[1])->where('is_active', true)->first(['id']);
        if (! $brand) {
            return ['type' => 'broken', 'reason' => 'no active brand "'.$rest[1].'"'];
        }

        if (count($rest) === 2 || $rest[2] === 'models') {
            return null;
        }

        return CarModel::query()->where('manufacturer_id', $brand->id)->where('slug', $rest[2])->where('is_active', true)->exists()
            ? null
            : ['type' => 'broken', 'reason' => 'no active car model "'.$rest[2].'" for this brand'];
    }

    /** @return array{0: ?array{type: string, reason: string}, 1: ?string} */
    private function checkPage(string $slug): array
    {
        $page = Page::query()->where('slug', $slug)->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')->where('published_at', '<=', now())->first(['id']);

        return $page
            ? [null, $slug]
            : [['type' => 'broken', 'reason' => 'no published page "'.$slug.'" (it may be a draft, scheduled or deleted)'], null];
    }

    // ── Orphans ───────────────────────────────────────────────────────────

    /**
     * Published, visible CMS pages that no other content links to and that are not in the header/footer menu or
     * linked from the shop's own header/footer template.
     *
     * @param  array<string, true>  $linkedSlugs
     * @return list<array{label: string, edit_url: string}>
     */
    private function orphans(array $linkedSlugs): array
    {
        $templated = $this->slugsLinkedFromTemplates();
        $orphans = [];

        $pages = Page::query()
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where('is_homepage', false)
            ->where('is_header', false)->where('is_footer', false)
            ->get(['id', 'title', 'slug']);

        foreach ($pages as $page) {
            if (isset($linkedSlugs[$page->slug]) || isset($templated[$page->slug])) {
                continue;
            }

            $orphans[] = [
                'label' => ($this->en((array) $page->title) ?: $page->slug).'  (/'.$page->slug.')',
                'edit_url' => PageResource::getUrl('edit', ['record' => $page->id]),
            ];
        }

        return $orphans;
    }

    /**
     * Page slugs the storefront's header/footer templates link to directly (legal pages and the like).
     *
     * @return array<string, true>
     */
    private function slugsLinkedFromTemplates(): array
    {
        $slugs = [];

        foreach (['components/footer.blade.php', 'components/navbar.blade.php'] as $file) {
            $path = resource_path('views/'.$file);
            $source = is_file($path) ? (string) file_get_contents($path) : '';

            if (preg_match_all("/'slug'\s*=>\s*'([a-z0-9\-]+)'/", $source, $m) > 0) {
                foreach ($m[1] as $slug) {
                    $slugs[$slug] = true;
                }
            }
        }

        return $slugs;
    }

    private function en(array $field): string
    {
        return trim((string) ($field['en'] ?? ''));
    }
}
