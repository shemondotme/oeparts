<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Product;
use App\Models\SeoMeta;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * SEO Service — generates meta tags, JSON‑LD, hreflang, canonical URLs.
 *
 * Responsibilities:
 * 1. Generate JSON‑LD structured data for products, pages, homepage
 * 2. Build hreflang link tags for all active locales (LocaleRegistry)
 * 3. Provide canonical URL for any route (respecting language prefix)
 * 4. Retrieve SEO meta from SeoMeta morphable table
 * 5. Fallback to sensible defaults when no custom meta exists
 */
class SeoService
{
    private array $supportedLocales;

    public function __construct(
        private SettingsService $settings
    ) {
        $this->supportedLocales = LocaleRegistry::codes();
    }

    /**
     * Generate JSON‑LD structured data for the current page.
     *
     * @param  string  $type  'website', 'product', 'article', 'organization', 'breadcrumb'
     * @param  mixed  $entity  Product, Page, BlogPost, an array of breadcrumb
     *         items (['label' => ..., 'url' => ...]) for 'breadcrumb', or null
     * @return string JSON‑LD script tag (empty string if nothing to output)
     */
    public function jsonLd(string $type = 'website', $entity = null): string
    {
        $data = [];

        switch ($type) {
            case 'website':
                $data = $this->websiteJsonLd();
                break;
            case 'product':
                if ($entity instanceof Product) {
                    $data = $this->productJsonLd($entity);
                }
                break;
            case 'article':
                if ($entity instanceof BlogPost) {
                    $data = $this->articleJsonLd($entity);
                }
                break;
            case 'organization':
                $data = $this->organizationJsonLd();
                break;
            case 'breadcrumb':
                if (is_array($entity)) {
                    $data = $this->breadcrumbJsonLd($entity);
                }
                break;
        }

        if (empty($data)) {
            return '';
        }

        return '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>';
    }

    /**
     * JSON‑LD for the website itself.
     */
    private function websiteJsonLd(): array
    {
        $siteName = $this->settings->get('general.site_name', 'OeParts');
        $siteUrl = URL::to('/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $siteUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                // Google's Sitelinks Search Box spec allows exactly ONE
                // templated variable in the target URL: {search_term_string}
                // (declared by query-input below). An earlier version left
                // an unresolved {lang} placeholder in the same string, which
                // Google would never substitute — producing a literal,
                // broken "/{lang}/parts/..." URL. Resolve it to the
                // request's actual locale instead, same as every other
                // locale-aware URL this service builds.
                'target' => $siteUrl.'/'.App::getLocale().'/parts/{search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Maps this catalog's Condition.slug values to the correct schema.org
     * ItemCondition URL. Only 'new'/'used' are ever seeded (ConditionSeeder),
     * but the model defines no fixed enum — an admin can freely add a
     * custom Condition row (the pre-2026-03-30 enum literally had
     * used_grade_a/b/c, remanufactured, aftermarket, new_old_stock before
     * being collapsed to just new/used, so these richer distinctions could
     * plausibly return). An unmapped slug OMITS the key entirely rather
     * than guessing — safer for a merchant feed than declaring a wrong
     * condition.
     */
    private const CONDITION_SCHEMA_MAP = [
        'new' => 'https://schema.org/NewCondition',
        'used' => 'https://schema.org/UsedCondition',
        'refurbished' => 'https://schema.org/RefurbishedCondition',
        'remanufactured' => 'https://schema.org/RefurbishedCondition',
    ];

    /**
     * Read-only accessor for the SEO Control Center's "Structured Data"
     * tab, which displays this mapping for admin visibility — the map
     * itself is code, not an editable setting.
     */
    public static function conditionSchemaMap(): array
    {
        return self::CONDITION_SCHEMA_MAP;
    }

    /**
     * JSON‑LD for a product.
     */
    private function productJsonLd(Product $product): array
    {
        $price = $product->price;
        $currency = $this->settings->get('general.currency', 'EUR');
        $availability = $product->is_in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => trans_field($product->name) ?: $product->oem_number,
            'description' => $product->descriptionOrFallback(),
            'sku' => $product->oem_number,
            'mpn' => $product->oem_number,
            'brand' => [
                '@type' => 'Brand',
                'name' => trans_field($product->manufacturer->name),
            ],
            // Every OEM number this product answers to — its own primary
            // number plus every cross-reference (other manufacturers'
            // numbers for the same physical part). Without this, structured
            // data only ever reflected the primary number even when a
            // visitor arrived here via a cross-reference search.
            'additionalProperty' => $product->crossReferences
                ->pluck('cross_oem_number')
                ->push($product->oem_number)
                ->unique()
                ->map(fn (string $oem) => [
                    '@type' => 'PropertyValue',
                    'name' => 'OEM Number',
                    'value' => $oem,
                ])
                ->values()
                ->all(),
            'offers' => [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => $currency,
                'availability' => $availability,
                'url' => URL::route('frontend.search.results', [
                    'lang' => App::getLocale(),
                    'oem' => $product->normalized_oem,
                ]),
            ],
        ];

        if ($product->condition && isset(self::CONDITION_SCHEMA_MAP[$product->condition->slug])) {
            $data['itemCondition'] = self::CONDITION_SCHEMA_MAP[$product->condition->slug];
        }

        // Gallery images (featured first) if any exist, else the same
        // fallback chain the visible page uses (manufacturer logo, then a
        // placeholder) — structured data should reflect what a visitor
        // actually sees, not silently omit image entirely.
        $galleryUrls = $product->images->isNotEmpty()
            ? $product->images->sortByDesc('is_featured')->map(fn ($image) => $image->medium_url)->values()->all()
            : [$product->resolvedImageUrl('medium')];
        $data['image'] = $galleryUrls;

        return $data;
    }

    /**
     * JSON‑LD BreadcrumbList from a plain ['label' => ..., 'url' => ...]
     * array — the same shape SearchController::buildResultsViewData() and
     * buildProductBreadcrumbs() already produce. Auto-prepends "Home" so
     * every caller only needs to supply the entity-specific trail.
     */
    private function breadcrumbJsonLd(array $items): array
    {
        $siteName = $this->settings->get('general.site_name', 'OeParts');

        $elements = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => $siteName,
                'item' => URL::to('/'),
            ],
        ];

        foreach ($items as $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => count($elements) + 1,
                'name' => $item['label'],
                'item' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * JSON‑LD for a blog article.
     */
    private function articleJsonLd(BlogPost $post): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => trans_field($post->title),
            'description' => trans_field($post->excerpt),
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name ?? 'OeParts',
            ],
            'publisher' => $this->organizationJsonLd(),
        ];
    }

    /**
     * JSON‑LD for the organization (OeParts).
     */
    private function organizationJsonLd(): array
    {
        return [
            '@type' => 'Organization',
            'name' => $this->settings->get('general.site_name', 'OeParts'),
            'url' => URL::to('/'),
            'logo' => $this->resolvedLogoUrl(),
        ];
    }

    /**
     * Resolve the operator-uploaded logo (general.logo_id, an upload path on
     * the 'public' disk) into an absolute URL, same pattern as the
     * seo.default_og_image resolution in layouts/app.blade.php.
     */
    private function resolvedLogoUrl(): ?string
    {
        $logoPath = $this->settings->get('general.logo_id', '');

        return $logoPath ? Storage::disk('public')->url($logoPath) : null;
    }

    /**
     * Generate hreflang link tags for the current route in all supported locales.
     *
     * @param  string|null  $canonicalUrl  If provided, used as the x‑default href
     * @param  \Illuminate\Database\Eloquent\Model|null  $entity  When a Product,
     *         a locale is skipped entirely unless it has a GENUINE translation
     *         (not one trans_field() would silently fall back to English for)
     *         — pointing an hreflang tag at English-fallback content under a
     *         false "this is the French version" claim is worse than omitting
     *         the tag. Backward-compatible: every other existing caller passes
     *         no entity and keeps today's unconditional-all-locales behavior.
     * @return string HTML link tags
     */
    public function hreflang(?string $canonicalUrl = null, ?object $entity = null): string
    {
        $currentRoute = request()->route();
        if (! $currentRoute) {
            return '';
        }

        $tags = [];
        $currentLocale = App::getLocale();

        foreach ($this->supportedLocales as $locale) {
            if ($entity instanceof Product && ! $entity->hasRealTranslation('name', $locale)) {
                continue;
            }

            $url = $this->localizedUrl($locale);
            if ($url) {
                $tags[] = sprintf('<link rel="alternate" hreflang="%s" href="%s">', $locale, $url);
            }
        }

        // x‑default points to the canonical URL (or the current URL if none provided)
        $xDefault = $canonicalUrl ?: URL::current();
        $tags[] = sprintf('<link rel="alternate" hreflang="x-default" href="%s">', $xDefault);

        return implode("\n    ", $tags);
    }

    /**
     * Build a URL for the same route in a different locale.
     */
    private function localizedUrl(string $locale): ?string
    {
        $route = request()->route();
        $parameters = $route->parameters();
        $parameters['lang'] = $locale;

        // Special handling for OEM search route
        if ($route->getName() === 'frontend.search.results' && isset($parameters['oem'])) {
            // Keep the OEM parameter as-is (normalization happens in middleware)
            return URL::route($route->getName(), $parameters);
        }

        try {
            return URL::route($route->getName(), $parameters);
        } catch (\Exception $e) {
            // If route doesn't exist for that locale, fall back to homepage
            return URL::to("/{$locale}/");
        }
    }

    /**
     * Retrieve SEO meta for a morphable entity (Product, Page, etc.).
     *
     * @param  mixed  $entity
     * @return array{meta_title: ?string, meta_description: ?string, canonical_url: ?string, og_title: ?string, og_description: ?string, og_image_id: ?int, robots: ?string}
     */
    public function getMetaFor($entity): array
    {
        if (! $entity) {
            return $this->defaultMeta();
        }

        $meta = SeoMeta::where('metable_type', get_class($entity))
            ->where('metable_id', $entity->id)
            ->first();

        if (! $meta) {
            return $this->defaultMeta();
        }

        return [
            'meta_title' => $meta->meta_title,
            'meta_description' => $meta->meta_description,
            'canonical_url' => $meta->canonical_url,
            'og_title' => $meta->og_title,
            'og_description' => $meta->og_description,
            'og_image_id' => $meta->og_image_id,
            'robots' => $meta->robots,
        ];
    }

    /**
     * Default meta values from settings.
     */
    private function defaultMeta(): array
    {
        return [
            'meta_title' => null,
            'meta_description' => $this->settings->get('seo.default_description'),
            'canonical_url' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image_id' => null,
            'robots' => $this->settings->get('seo.default_robots', 'index,follow'),
        ];
    }

    /**
     * Generate canonical URL for the current page.
     * Respects language prefix and any canonical override stored in SeoMeta.
     *
     * @param  mixed  $entity
     */
    public function canonicalUrl($entity = null): ?string
    {
        // If entity has a canonical_url in SeoMeta, use that
        if ($entity) {
            $meta = $this->getMetaFor($entity);
            if (! empty($meta['canonical_url'])) {
                return $meta['canonical_url'];
            }
        }

        // Otherwise, use the current URL with the current language
        return URL::current();
    }

    /**
     * Generate Open Graph image tag if an image is available.
     *
     * @param  int|null  $ogImageId  MediaFile ID
     * @return string HTML meta tag or empty string
     */
    public function ogImageTag(?int $ogImageId): string
    {
        // Both branches used to return the same site-wide default,
        // ignoring $ogImageId entirely — a per-entity OG image selected in
        // SeoMeta never actually appeared; every page rendered the default
        // image regardless.
        if ($ogImageId) {
            $image = MediaFile::find($ogImageId);
            if ($image?->file_url) {
                return sprintf('<meta property="og:image" content="%s">', $image->file_url);
            }
        }

        $defaultImage = $this->settings->get('seo.default_og_image');
        if ($defaultImage) {
            // default_og_image is a path on the 'public' disk (uploaded via
            // the SEO Control Center's FileUpload field, served through the
            // /storage symlink) — URL::asset() instead built a dead link
            // straight off the public web root, e.g. "/og-images/x.jpg"
            // instead of "/storage/og-images/x.jpg". Every other consumer
            // of this same setting (layouts/app.blade.php) already resolves
            // it correctly this way.
            return sprintf('<meta property="og:image" content="%s">', Storage::disk('public')->url($defaultImage));
        }

        return '';
    }
}
