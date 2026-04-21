<?php

namespace App\Services;

use App\Models\SeoMeta;
use App\Models\Product;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\BlogPost;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

/**
 * SEO Service — generates meta tags, JSON‑LD, hreflang, canonical URLs.
 *
 * Responsibilities:
 * 1. Generate JSON‑LD structured data for products, pages, homepage
 * 2. Build hreflang link tags for all 5 supported locales
 * 3. Provide canonical URL for any route (respecting language prefix)
 * 4. Retrieve SEO meta from SeoMeta morphable table
 * 5. Fallback to sensible defaults when no custom meta exists
 */
class SeoService
{
    private array $supportedLocales = ['en', 'de', 'lt', 'fr', 'es'];

    public function __construct(
        private SettingsService $settings
    ) {}

    /**
     * Generate JSON‑LD structured data for the current page.
     *
     * @param string $type 'website', 'product', 'article', 'organization'
     * @param mixed $entity Product, Page, BlogPost, or null
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
        }

        if (empty($data)) {
            return '';
        }

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    /**
     * JSON‑LD for the website itself.
     */
    private function websiteJsonLd(): array
    {
        $siteName = $this->settings->get('general.site_name', 'OEMHub');
        $siteUrl = URL::to('/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $siteUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $siteUrl . '/{lang}/parts/{search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
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
            'description' => trans_field($product->description) ?: '',
            'sku' => $product->oem_number,
            'mpn' => $product->oem_number,
            'brand' => [
                '@type' => 'Brand',
                'name' => trans_field($product->manufacturer->name),
            ],
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

        return $data;
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
                'name' => $post->author?->name ?? 'OEMHub',
            ],
            'publisher' => $this->organizationJsonLd(),
        ];
    }

    /**
     * JSON‑LD for the organization (OEMHub).
     */
    private function organizationJsonLd(): array
    {
        return [
            '@type' => 'Organization',
            'name' => $this->settings->get('general.site_name', 'OEMHub'),
            'url' => URL::to('/'),
            'logo' => $this->settings->get('general.logo_url') ? URL::asset($this->settings->get('general.logo_url')) : null,
        ];
    }

    /**
     * Generate hreflang link tags for the current route in all supported locales.
     *
     * @param string|null $canonicalUrl If provided, used as the x‑default href
     * @return string HTML link tags
     */
    public function hreflang(?string $canonicalUrl = null): string
    {
        $currentRoute = request()->route();
        if (!$currentRoute) {
            return '';
        }

        $tags = [];
        $currentLocale = App::getLocale();

        foreach ($this->supportedLocales as $locale) {
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
     * @param mixed $entity
     * @return array{meta_title: ?string, meta_description: ?string, canonical_url: ?string, og_title: ?string, og_description: ?string, og_image_id: ?int, robots: ?string}
     */
    public function getMetaFor($entity): array
    {
        if (!$entity) {
            return $this->defaultMeta();
        }

        $meta = SeoMeta::where('metable_type', get_class($entity))
            ->where('metable_id', $entity->id)
            ->first();

        if (!$meta) {
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
     * @param mixed $entity
     * @return string|null
     */
    public function canonicalUrl($entity = null): ?string
    {
        // If entity has a canonical_url in SeoMeta, use that
        if ($entity) {
            $meta = $this->getMetaFor($entity);
            if (!empty($meta['canonical_url'])) {
                return $meta['canonical_url'];
            }
        }

        // Otherwise, use the current URL with the current language
        return URL::current();
    }

    /**
     * Generate Open Graph image tag if an image is available.
     *
     * @param int|null $ogImageId MediaFile ID
     * @return string HTML meta tag or empty string
     */
    public function ogImageTag(?int $ogImageId): string
    {
        if (!$ogImageId) {
            $defaultImage = $this->settings->get('seo.default_og_image');
            if ($defaultImage) {
                return sprintf('<meta property="og:image" content="%s">', URL::asset($defaultImage));
            }
            return '';
        }

        // MediaFile URL is resolved via public storage; if not available fall back to default
        $defaultImage = $this->settings->get('seo.default_og_image');
        if ($defaultImage) {
            return sprintf('<meta property="og:image" content="%s">', URL::asset($defaultImage));
        }
        return '';
    }
}