<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\BlogPost;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Services\Instance\SafeMode;
use App\Services\SeoService;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * llms.txt — an unofficial, still-emerging convention (no formal spec the
 * way robots.txt/sitemap.xml have) for giving AI crawlers/answer engines a
 * short, curated overview of the site. Root-level (outside the {lang}
 * locale group), same reasoning as robots.txt/sitemap.xml.
 */
class LlmsTxtController extends Controller
{
    /**
     * The live part of the file: main pages, latest posts, brands — markdown link lists in the default language,
     * only what the storefront really serves (published, not noindex, not hidden as thin content).
     */
    private function overview(): string
    {
        $locale = LocaleRegistry::defaultCode();
        $seo = app(SeoService::class);
        $noIndex = fn (string $table, string $class) => fn ($q) => $q->select(DB::raw(1))->from('seo_meta')
            ->whereColumn('seo_meta.metable_id', $table.'.id')->where('seo_meta.metable_type', $class)->where('seo_meta.robots', 'like', '%noindex%');
        $sections = [];

        $pages = Page::where('status', ContentStatus::Published->value)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where('is_homepage', false)->whereNotExists($noIndex('pages', Page::class))->orderBy('slug')->limit(30)->get()
            ->reject(fn (Page $p) => $seo->isThinContent($p, $locale))
            ->map(fn (Page $p) => $this->line(trans_field($p->title, $locale), URL::to("/{$locale}/{$p->slug}"), trans_field($p->meta_description, $locale)));
        if ($pages->isNotEmpty()) {
            $sections[] = "## Pages\n\n".$pages->implode("\n");
        }

        $posts = BlogPost::where('status', ContentStatus::Published->value)->where('published_at', '<=', now())
            ->whereNotExists($noIndex('blog_posts', BlogPost::class))->orderByDesc('published_at')->limit(15)->get()
            ->reject(fn (BlogPost $p) => $seo->isThinContent($p, $locale))
            ->map(fn (BlogPost $p) => $this->line(trans_field($p->title, $locale), URL::to("/{$locale}/blog/{$p->slug}"), trans_field($p->meta_description, $locale)));
        if ($posts->isNotEmpty()) {
            $sections[] = "## Blog\n\n".$posts->implode("\n");
        }

        $brands = Manufacturer::where('is_active', true)->whereNotExists($noIndex('manufacturers', Manufacturer::class))->orderBy('slug')->limit(40)->get()
            ->map(fn (Manufacturer $m) => $this->line(trans_field($m->name, $locale), URL::route('frontend.manufacturer.show', ['lang' => $locale, 'manufacturer' => $m->slug])));
        if ($brands->isNotEmpty()) {
            $sections[] = "## Brands\n\n".$brands->implode("\n");
        }

        return implode("\n\n", $sections);
    }

    private function line(string $title, string $url, string $description = ''): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags($title)));
        $description = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($description))), 140, '…');

        return '- ['.str_replace(['[', ']'], '', $title ?: $url).']('.$url.')'.($description !== '' ? ': '.$description : '');
    }

    public function index(Request $request): Response
    {
        // A copy of the shop has nothing to tell answer engines: the file does not exist there.
        abort_if(app(SafeMode::class)->isCopy(), 404);

        // The fallback here (not just an empty string) matters: the real
        // default text lives in SettingsSeeder, which only populates the
        // database on installs that actually run it — this route must
        // still return something sensible on a fresh, unseeded database.
        $default = 'OeParts is an OEM auto-parts catalog searchable by manufacturer part number, '
            ."including cross-reference numbers across manufacturers for the same physical part.\n\n"
            .'Sitemap: {site_url}/sitemap.xml';

        $body = settings_trans('crawlers.llms_txt_body', $default);
        $body = str_replace('{site_url}', rtrim(url('/'), '/'), $body);

        if (filter_var(settings('crawlers.llms_txt_auto', true), FILTER_VALIDATE_BOOLEAN)) {
            $body = rtrim($body)."\n\n".Cache::remember('llms-txt:overview', 600, fn () => $this->overview());
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            // Same reduced max-age as robots.txt — this is admin-editable
            // (SEO Control Center) and should take effect promptly.
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
