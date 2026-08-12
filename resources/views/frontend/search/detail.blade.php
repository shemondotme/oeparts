@extends('layouts.app')

@php
    $lang = app()->getLocale();
    $siteName = settings('general.site_name', 'OeParts');
    $seoService = app(\App\Services\SeoService::class);

    // SeoMeta admin override wins > auto-generated values > site default —
    // getMetaFor()/canonicalUrl() already implement exactly this priority
    // chain (SeoMeta lookup first, falls back to defaultMeta()), so this
    // detail page reuses them rather than hand-rolling its own resolution.
    $seoOverride = $seoService->getMetaFor($product);

    $productName = trans_field($product->name) ?: $product->oem_number;
    $manufacturerName = $product->manufacturer ? trans_field($product->manufacturer->name) : '';
    $conditionLabel = $product->condition ? condition_label($product->condition, $lang) : '';

    $autoTitle = trim(implode(' ', array_filter([$manufacturerName, $productName, $product->oem_number]))) . ' — ' . $siteName;
    $pageTitle = $seoOverride['meta_title'] ?: $autoTitle;
    $pageDescription = $seoOverride['meta_description'] ?: \Illuminate\Support\Str::limit(strip_tags($product->descriptionOrFallback($lang)), 160, '');

    $canonicalUrl = $seoService->canonicalUrl($product);
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title'){{ $pageTitle }}@endsection
@section('meta_description'){{ $pageDescription }}@endsection
@section('og_title'){{ $seoOverride['og_title'] ?: $pageTitle }}@endsection
@section('og_description'){{ $seoOverride['og_description'] ?: $pageDescription }}@endsection

@if($seoOverride['robots'])
@section('meta_robots')
    <meta name="robots" content="{{ $seoOverride['robots'] }}">
@endsection
@endif

@if($ogImageTag = $seoService->ogImageTag($seoOverride['og_image_id']))
@section('og_image'){!! $ogImageTag !!}@endsection
@endif

@section('canonical')
    <link rel="canonical" href="{{ $canonicalUrl }}">
@endsection

@section('hreflang')
    {!! $seoService->hreflang($canonicalUrl, $product) !!}
@endsection

@php
    $breadcrumbListItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('pages.breadcrumb_home'), 'item' => url('/'.$lang.'/')],
    ];
    foreach ($breadcrumbs as $crumb) {
        $breadcrumbListItems[] = [
            '@type' => 'ListItem',
            'position' => count($breadcrumbListItems) + 1,
            'name' => $crumb['label'],
            'item' => $crumb['url'],
        ];
    }
@endphp

@section('json_ld')
{!! $seoService->jsonLd('product', $product) !!}
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => $breadcrumbListItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('og_type', 'product')

{{-- ── Content ──────────────────────────────────────────────────────────── --}}
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Breadcrumbs (visible nav, mirrors the JSON-LD BreadcrumbList above) --}}
    <nav class="text-xs text-ink-muted mb-6" aria-label="Breadcrumb">
        <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink">{{ __('pages.breadcrumb_home') }}</a>
        @foreach($breadcrumbs as $crumb)
            <span class="mx-1">/</span>
            <a href="{{ $crumb['url'] }}" class="hover:text-ink">{{ $crumb['label'] }}</a>
        @endforeach
    </nav>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

        {{-- Gallery: featured image large + thumbnail strip. Falls back
             through Product::resolvedImageUrl() (featured -> manufacturer
             logo -> placeholder) — never a broken image. Clicking a
             thumbnail swaps the main image client-side (Alpine, no page
             reload); the main <img> keeps its own real src (not just an
             Alpine binding) so it's still a real image on first paint for
             crawlers/no-JS clients. --}}
        <div x-data="{ mainImage: '{{ $product->resolvedImageUrl('medium') }}' }">
            <img :src="mainImage"
                 src="{{ $product->resolvedImageUrl('medium') }}"
                 alt="{{ $product->featuredImage?->alt_text ? trans_field($product->featuredImage->alt_text, $lang) : $productName }}"
                 width="800" height="800"
                 class="w-full aspect-square object-contain rounded-lg border border-rule bg-paper"
                 data-testid="product-main-image">
            @if($product->images->count() > 1)
            <div class="flex gap-2 mt-3 flex-wrap">
                @foreach($product->images as $galleryImage)
                <img src="{{ $galleryImage->thumbnail_url }}"
                     alt="{{ $galleryImage->alt_text ? trans_field($galleryImage->alt_text, $lang) : $productName }}"
                     width="150" height="150"
                     @click="mainImage = '{{ $galleryImage->medium_url }}'"
                     class="w-16 h-16 object-cover rounded border border-rule-strong cursor-pointer hover:border-ink transition-colors"
                     data-testid="product-thumbnail">
                @endforeach
            </div>
            @endif
        </div>

        {{-- Spec / CTA block --}}
        <div>
            <h1 class="text-2xl font-bold text-ink mb-1">{{ $productName }}</h1>
            <p class="font-mono text-sm text-ink-muted mb-4 oem-number">
                {{ ui_copy('search_oem_label', 'search.oem_label') }}: <span class="font-semibold text-ink">{{ $product->oem_number }}</span>
            </p>

            <dl class="grid grid-cols-2 gap-3 mb-6 text-sm">
                @if($manufacturerName)
                <div>
                    <dt class="text-ink-muted">{{ __('search.brand') }}</dt>
                    <dd class="font-medium text-ink">{{ $manufacturerName }}</dd>
                </div>
                @endif
                @if($conditionLabel)
                <div>
                    <dt class="text-ink-muted">{{ __('search.condition') }}</dt>
                    <dd class="font-medium text-ink">{{ $conditionLabel }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-ink-muted">{{ ui_copy('search_availability_label', 'search.availability_label') }}</dt>
                    <dd class="font-medium {{ $product->is_in_stock ? 'text-green-700' : 'text-red-700' }}">
                        {{ $product->is_in_stock ? ui_copy('search_in_stock', 'search.in_stock') : ui_copy('search_out_of_stock', 'search.out_of_stock') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ ui_copy('search_price_label', 'search.price_label') }}</dt>
                    <dd class="font-mono font-bold text-ink">{{ format_price($product->price) }}</dd>
                </div>
            </dl>

            {{-- Visible description — this, not just the JSON-LD copy, is
                 the actual thin-content mitigation: it must be real
                 on-page content a visitor (and crawler) can read. --}}
            <p class="text-sm text-body leading-relaxed mb-6">{{ $product->descriptionOrFallback($lang) }}</p>
        </div>
    </div>

    {{-- Cross-reference OEM numbers — same concept as the hub page's
         expandable panel, so the detail page's content isn't thinner. --}}
    @if($product->crossReferences->isNotEmpty())
    <div class="mt-8 pt-6 border-t border-rule">
        <p class="bp-spec text-amber-ink mb-3">{{ ui_copy('search_cross_refs_title', 'search.cross_refs_title') }}</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach($product->crossReferences as $cross)
            <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $cross->normalized_cross_oem]) }}"
               data-testid="product-cross-ref-link"
               class="inline-flex items-center gap-1 px-2 py-1 border border-rule-strong bg-paper font-mono text-[10px] font-semibold tabular-nums text-ink hover:bg-ink hover:text-ivory hover:border-ink transition-colors">
                {{ $cross->cross_oem_number }}
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
