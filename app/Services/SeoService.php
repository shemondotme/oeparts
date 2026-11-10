<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Product;
use App\Models\Review;
use App\Models\SeoMeta;
use App\Support\LocaleRegistry;
use Illuminate\Database\Eloquent\Model;
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
        private SettingsService $settings,
        private ProductSlugService $productSlugService,
    ) {
        $this->supportedLocales = LocaleRegistry::codes();
    }

    /**
     * Generate JSON‑LD structured data for the current page.
     *
     * @param  string  $type  'website', 'product', 'article', 'organization', 'breadcrumb'
     * @param  mixed  $entity  Product, Page, BlogPost, an array of breadcrumb
     *                         items (['label' => ..., 'url' => ...]) for 'breadcrumb', or null
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
            case 'home':
                $data = $this->homeJsonLd();
                break;
            case 'organization':
                // Standalone block → needs its own @context; the nested copy
                // inside Article.publisher inherits the parent's.
                $data = $this->organizationJsonLd(true);
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

        return '<script type="application/ld+json">'.json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>';
    }

    /**
     * Homepage entity graph: Organization + WebSite as ONE @graph with stable
     * @id's, the WebSite naming the Organization as its publisher. One
     * source for what the home view used to hand-write (and what
     * websiteJsonLd()/organizationJsonLd() were never wired to), so the
     * entity is declared once and other pages can point at the same @id.
     * Address/sameAs/phone are only emitted when actually configured.
     */
    private function homeJsonLd(): array
    {
        $siteName = (string) $this->settings->get('general.site_name', 'OeParts');
        $siteUrl = rtrim((string) ($this->settings->get('general.site_url', '') ?: URL::to('/')), '/');
        $logoPath = (string) $this->settings->get('general.logo_id', '');
        $logo = $logoPath ? Storage::disk('public')->url($logoPath) : URL::to('/favicon.svg');

        $sameAs = array_values(array_filter(array_map(
            fn (string $key) => (string) $this->settings->get("social_links.{$key}", ''),
            ['facebook_url', 'instagram_url', 'twitter_url', 'linkedin_url', 'youtube_url', 'tiktok_url']
        )));

        $languageNames = [];
        foreach (LocaleRegistry::languages() as $language) {
            $languageNames[] = $language['name'];
        }

        $organization = array_filter([
            '@type' => 'Organization',
            '@id' => $siteUrl.'/#organization',
            'name' => $siteName,
            'url' => $siteUrl,
            'logo' => $logo,
            'address' => ($this->settings->get('company.address', '') ?: null),
            'sameAs' => $sameAs ?: null,
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => ($this->settings->get('general.site_phone', '') ?: null),
                'contactType' => 'customer service',
                'areaServed' => 'EU',
                'availableLanguage' => $languageNames,
            ],
        ], fn ($v) => $v !== null);

        // A ContactPoint with nothing but static fields adds no information.
        if (empty($organization['contactPoint']['telephone'])) {
            unset($organization['contactPoint']['telephone']);
        }

        $website = [
            '@type' => 'WebSite',
            '@id' => $siteUrl.'/#website',
            'name' => $siteName,
            'url' => $siteUrl,
            'publisher' => ['@id' => $siteUrl.'/#organization'],
            'inLanguage' => array_column(LocaleRegistry::languages(), 'code'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $siteUrl.'/'.App::getLocale().'/parts/{search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return ['@context' => 'https://schema.org', '@graph' => [$organization, $website]];
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
                'availability' => $availability,
                'url' => $this->offerUrl($product),
            ] + $this->priceFields($price),
        ];

        if ($product->condition && isset(self::CONDITION_SCHEMA_MAP[$product->condition->slug])) {
            $data['itemCondition'] = self::CONDITION_SCHEMA_MAP[$product->condition->slug];
        }

        // Warranty — the column already exists and the page shows it; only
        // emitted when a real, positive duration is stored.
        $warrantyMonths = (int) ($product->warranty_months ?? 0);
        if ($warrantyMonths > 0) {
            $data['warranty'] = [
                '@type' => 'WarrantyPromise',
                'durationOfWarranty' => [
                    '@type' => 'QuantitativeValue',
                    'value' => $warrantyMonths,
                    'unitCode' => 'MON',
                ],
            ];
        }

        // Fitment — the confirmed vehicles this part fits, as the schema.org
        // spare-part relationship. Mirrors the visible "confirmed fitment"
        // list (active models only); capped so a universal part fitting
        // hundreds of models doesn't bloat every page.
        $fitment = $product->loadMissing('carModels.manufacturer')->carModels
            ->filter(fn ($model) => $model->is_active)
            ->take(25)
            ->map(fn ($model) => [
                '@type' => 'Car',
                'name' => trim(($model->manufacturer ? trans_field($model->manufacturer->name) : '').' '.$model->name),
            ])
            ->filter(fn (array $car) => $car['name'] !== '')
            ->values()
            ->all();

        if ($fitment !== []) {
            $data['isAccessoryOrSparePartFor'] = $fitment;
        }

        // Admin-entered spec key/value pairs — same additionalProperty
        // pattern already used for cross-reference OEM numbers above, just
        // never extended to specifications. Only when the specs SECTION is
        // actually visible (pdp.show_specifications): structured data must
        // match what the page shows, same principle as the reviews gate
        // below.
        if (filter_var($this->settings->get('pdp.show_specifications', true), FILTER_VALIDATE_BOOLEAN) && ! empty($product->specifications)) {
            foreach ($product->specifications as $specKey => $specValue) {
                $data['additionalProperty'][] = [
                    '@type' => 'PropertyValue',
                    'name' => $specKey,
                    'value' => $specValue,
                ];
            }
        }

        // Gallery images (featured first) if any exist, else the same
        // fallback chain the visible page uses (manufacturer logo, then a
        // placeholder) — structured data should reflect what a visitor
        // actually sees, not silently omit image entirely.
        $galleryUrls = $product->images->isNotEmpty()
            ? $product->images->sortByDesc('is_featured')->map(fn ($image) => $image->medium_url)->values()->all()
            : [$product->resolvedImageUrl('medium')];
        $data['image'] = $galleryUrls;

        // AggregateRating/Review — only when the reviews SECTION is
        // actually visible (pdp.show_reviews) and there's at least one
        // approved review. Google explicitly disallows a fabricated
        // AggregateRating with reviewCount 0, and emitting rating data the
        // page itself doesn't display would be exactly the kind of
        // structured-data/visible-content mismatch already fixed elsewhere
        // in this service (see the breadcrumb fix). Falls back to a fresh
        // count/avg query when the caller didn't preload
        // approved_reviews_count/avg_rating via withCount()/withAvg()
        // (SearchController::detail() always does).
        if (filter_var($this->settings->get('pdp.show_reviews', true), FILTER_VALIDATE_BOOLEAN)) {
            $reviewCount = (int) ($product->approved_reviews_count ?? $product->approvedReviews()->count());

            if ($reviewCount > 0) {
                $avgRating = $product->approved_reviews_avg_rating ?? $product->approvedReviews()->avg('rating');

                $data['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round((float) $avgRating, 1),
                    'reviewCount' => $reviewCount,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ];

                $data['review'] = $product->approvedReviews()
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (Review $review) => array_filter([
                        '@type' => 'Review',
                        'reviewRating' => [
                            '@type' => 'Rating',
                            'ratingValue' => $review->rating,
                            'bestRating' => 5,
                            'worstRating' => 1,
                        ],
                        'author' => ['@type' => 'Person', 'name' => $review->reviewer_name],
                        'reviewBody' => $review->comment,
                        'name' => $review->title,
                        'datePublished' => $review->created_at?->toIso8601String(),
                    ]))
                    ->values()
                    ->all();
            }
        }

        // Product.video — only when the video SECTION is actually visible
        // (pdp.show_video, which already requires video_url to be set).
        // No dedicated video metadata (thumbnail/duration/upload date) is
        // captured anywhere at entry time, so this uses the same
        // fallbacks the visible page itself relies on elsewhere: the
        // product's own name/description and resolved image as a
        // thumbnail, and the product row's own created_at as the closest
        // available proxy for when this video became part of the listing.
        if (filter_var($this->settings->get('pdp.show_video', true), FILTER_VALIDATE_BOOLEAN) && $product->video_url) {
            $data['video'] = [
                '@type' => 'VideoObject',
                'name' => $data['name'],
                'description' => $data['description'],
                'thumbnailUrl' => $product->resolvedImageUrl('medium'),
                'uploadDate' => $product->created_at?->toIso8601String(),
                'contentUrl' => $product->video_url,
            ];
        }

        return $data;
    }

    /**
     * price / priceCurrency / priceSpecification for an Offer, matching what
     * the storefront actually DISPLAYS. Prices are stored net; with
     * tax.price_display = inc_vat (the default) the page shows the VAT-
     * inclusive amount as the primary price, so structured data must carry
     * that same amount — Google (and Merchant Center) compare the two and
     * flag/disqualify a mismatch, and EU listings are expected to be VAT-
     * inclusive. Uses the same flat tax.default_vat_rate the hub and detail
     * pages use, and declares the VAT treatment explicitly.
     *
     * @param  mixed  $netPrice  the stored (net) product price
     * @return array{price: mixed, priceCurrency: string, priceSpecification: array<string, mixed>}
     */
    public function priceFields(mixed $netPrice): array
    {
        $currency = (string) $this->settings->get('general.currency', 'EUR');

        if (! is_numeric($netPrice)) {
            return ['price' => $netPrice, 'priceCurrency' => $currency, 'priceSpecification' => []];
        }

        $vatIncluded = $this->settings->get('tax.price_display', 'inc_vat') === 'inc_vat';
        $net = number_format((float) $netPrice, 2, '.', '');
        $price = $net;

        if ($vatIncluded) {
            $rate = (int) $this->settings->get('tax.default_vat_rate', 21);
            $price = bcmul($net, bcadd('1', bcdiv((string) $rate, '100', 4), 4), 2);
        }

        return [
            // schema.org wants a plain decimal ("12.50"), never a locale-
            // formatted or float-noise string.
            'price' => $price,
            'priceCurrency' => $currency,
            'priceSpecification' => [
                '@type' => 'UnitPriceSpecification',
                'price' => $price,
                'priceCurrency' => $currency,
                'valueAddedTaxIncluded' => $vatIncluded,
            ],
        ];
    }

    /**
     * The URL the offer lives at. With Hub+Detail pages on, the product's
     * own detail page is the canonical, indexable page (the hub URL 301s to
     * it for a single-product OEM), so the offer must point there — pointing
     * it at a redirecting hub URL mismatches the page's own canonical.
     */
    private function offerUrl(Product $product): string
    {
        $locale = App::getLocale();

        if (filter_var($this->settings->get('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return URL::route('frontend.search.detail', [
                'lang' => $locale,
                'oem' => $product->normalized_oem,
                'idSlug' => $this->productSlugService->buildIdSlug($product, $locale),
            ]);
        }

        return URL::route('frontend.search.results', ['lang' => $locale, 'oem' => $product->normalized_oem]);
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
        // Article rich results want an image; only emitted when the post
        // really has a featured image (never a fabricated placeholder).
        $imageUrl = $post->featuredImage?->file_url;

        return array_filter([
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
            'image' => $imageUrl ?: null,
        ], fn ($v) => $v !== null);
    }

    /**
     * JSON‑LD for the organization (OeParts).
     */
    private function organizationJsonLd(bool $standalone = false): array
    {
        return ($standalone ? ['@context' => 'https://schema.org'] : []) + [
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
     * @param  Model|null  $entity  When a Product,
     *                              a locale is skipped entirely unless it has a GENUINE translation
     *                              (not one trans_field() would silently fall back to English for)
     *                              — pointing an hreflang tag at English-fallback content under a
     *                              false "this is the French version" claim is worse than omitting
     *                              the tag. Backward-compatible: every other existing caller passes
     *                              no entity and keeps today's unconditional-all-locales behavior.
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

            $url = $this->localizedUrl($locale, $entity);
            if ($url) {
                $tags[] = sprintf('<link rel="alternate" hreflang="%s" href="%s">', $locale, $url);
            }
        }

        // x-default must be the SAME URL on every locale variant of a page
        // (Google's cluster rule) — the default-locale version, not whichever
        // locale the visitor happens to be on. Falls back to the canonical /
        // current URL only when the default locale has no variant to point at.
        $defaultLocale = LocaleRegistry::defaultCode();
        $xDefault = null;
        if (! ($entity instanceof Product) || $entity->hasRealTranslation('name', $defaultLocale)) {
            $xDefault = $this->localizedUrl($defaultLocale, $entity);
        }
        $xDefault ??= $canonicalUrl ?: URL::current();
        $tags[] = sprintf('<link rel="alternate" hreflang="x-default" href="%s">', e($xDefault));

        return implode("\n    ", $tags);
    }

    /**
     * hreflang tags for pages whose per-locale URL is a plain closure — the
     * one place that decides which locales are advertised (LocaleRegistry,
     * the admin-managed list) and what x-default is (the default locale's
     * URL, identical on every variant). Replaces the hand-written
     * `@foreach(['en','de','lt','fr','es'])` loops that drifted out of sync
     * with the real locale list and each picked its own x-default.
     *
     * @param  \Closure(string): ?string  $urlFor  locale code => absolute URL (null skips that locale)
     */
    public function hreflangTags(\Closure $urlFor): string
    {
        $tags = [];

        foreach ($this->supportedLocales as $locale) {
            $url = $urlFor($locale);
            if ($url) {
                $tags[] = sprintf('<link rel="alternate" hreflang="%s" href="%s">', e($locale), e($url));
            }
        }

        $default = $urlFor(LocaleRegistry::defaultCode());
        if ($default) {
            $tags[] = sprintf('<link rel="alternate" hreflang="x-default" href="%s">', e($default));
        }

        return implode("\n    ", $tags);
    }

    /**
     * Canonical home URL for a locale — deliberately WITHOUT a trailing
     * slash. EnforceCanonicalHost (and public/.htaccess) 301 every
     * trailing-slash URL except the bare root, so "/en/" is never a final
     * URL; advertising it in canonical/hreflang/sitemap/JSON-LD pointed all
     * of them at a redirect.
     */
    public static function homeUrl(?string $locale = null): string
    {
        return URL::to('/'.($locale ?: App::getLocale()));
    }

    /**
     * Build a URL for the same route in a different locale.
     */
    private function localizedUrl(string $locale, ?object $entity = null): ?string
    {
        $route = request()->route();
        $parameters = $route->parameters();
        $parameters['lang'] = $locale;

        // The per-product detail route's idSlug is locale-dependent — its
        // cosmetic slug text is derived from the product's name IN THE
        // CURRENT locale (ProductSlugService::generate()). Reusing the
        // current route's idSlug verbatim pointed the hreflang tag for
        // every OTHER locale at a slug that still read in the current
        // locale's language. Following it would 301 again
        // (SearchController::detail()'s canonical-drift check) to the real
        // per-locale slug — an hreflang link that redirects instead of
        // resolving directly, a documented Google anti-pattern.
        if ($route->getName() === 'frontend.search.detail' && $entity instanceof Product) {
            $parameters['idSlug'] = $this->productSlugService->buildIdSlug($entity, $locale);

            return URL::route($route->getName(), $parameters);
        }

        // Special handling for OEM search route
        if ($route->getName() === 'frontend.search.results' && isset($parameters['oem'])) {
            // Keep the OEM parameter as-is (normalization happens in middleware)
            return URL::route($route->getName(), $parameters);
        }

        try {
            return URL::route($route->getName(), $parameters);
        } catch (\Exception $e) {
            // If route doesn't exist for that locale, fall back to homepage
            return self::homeUrl($locale);
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
