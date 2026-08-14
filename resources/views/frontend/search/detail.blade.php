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
@php
    $galleryImages = $product->images->isNotEmpty() ? $product->images : collect();
    $mainImageAlt = $product->featuredImage?->alt_text ? trans_field($product->featuredImage->alt_text, $lang) : $productName;
    // The lightbox zooms into the ORIGINAL upload (not the medium-resized
    // display copy) for genuinely useful close inspection — falls back to
    // the same medium/logo/placeholder chain as the main display when
    // there's no real uploaded image to zoom into.
    $mainImageFull = $product->featuredImage?->url ?: $product->resolvedImageUrl('medium');
    $confirmedFitment = $product->carModels->sortBy(fn ($m) => [$m->manufacturer?->name, $m->name]);

    // Same VAT-display logic as the hub page (results.blade.php) — the
    // detail page previously always printed the raw net price with no
    // inc/exc-VAT toggle at all.
    $vatMultiplier = bcadd('1', bcdiv((string) $vat_rate, '100', 4), 4);
    $showIncVatPrimary = settings('tax.price_display', 'inc_vat') === 'inc_vat';
    $priceWithVat = bcmul((string) $product->price, $vatMultiplier, 2);

    // Every new PDP section is admin-toggleable (pdp.* settings group) —
    // computed once here so both the in-page sticky nav and each section's
    // own @if reuse the exact same "is this actually showable" answer,
    // which factors in both the toggle AND the section having real content.
    $showSpecs = filter_var(settings('pdp.show_specifications', true), FILTER_VALIDATE_BOOLEAN) && ! empty($product->specifications);
    $showWarranty = filter_var(settings('pdp.show_warranty', true), FILTER_VALIDATE_BOOLEAN) && $product->warranty_months;
    $showVideo = filter_var(settings('pdp.show_video', true), FILTER_VALIDATE_BOOLEAN) && $product->video_url;
    $showReviews = filter_var(settings('pdp.show_reviews', true), FILTER_VALIDATE_BOOLEAN);
    $showRelated = filter_var(settings('pdp.show_related_products', true), FILTER_VALIDATE_BOOLEAN) && $relatedProducts->isNotEmpty();
    $showStickyBar = filter_var(settings('pdp.sticky_add_to_cart', true), FILTER_VALIDATE_BOOLEAN);
    $showBuyNow = filter_var(settings('pdp.buy_now_enabled', false), FILTER_VALIDATE_BOOLEAN) && $product->is_in_stock;

    // detail() already 301s to the canonical oem/idSlug before this view
    // ever renders, so $product's own values ARE the current URL's — no
    // need to re-read route parameters.
    $currentIdSlug = app(\App\Services\ProductSlugService::class)->buildIdSlug($product, $lang);
@endphp
<div class="max-w-6xl mx-auto px-4 py-8"
     x-data="{
        mainImage: '{{ $product->resolvedImageUrl('medium') }}',
        mainImageFull: '{{ $mainImageFull }}',
        activeIndex: 0,
        zoomOpen: false,
        quantity: {{ max(1, (int) $product->moq) }},
        cartState: 'idle',
        cartError: '',
        setImage(index, mediumUrl, originalUrl) {
            this.activeIndex = index;
            this.mainImage = mediumUrl;
            this.mainImageFull = originalUrl;
        },
        async addToCart() {
            if (this.cartState !== 'idle') return;
            this.cartState = 'loading';
            this.cartError = '';
            try {
                const res = await fetch(@js(route('frontend.cart.add', ['lang' => $lang])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ product_id: {{ $product->id }}, quantity: this.quantity })
                });
                let data = {};
                try { data = await res.json(); } catch (e) {}
                if (res.ok && data.success) {
                    this.cartState = 'added';
                    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { itemCount: data.cart?.item_count ?? 0 } }));
                    window.dispatchEvent(new CustomEvent('cart-toast', {
                        detail: { productName: @js(trim($manufacturerName.' '.$product->oem_number)), quantity: this.quantity, itemCount: data.cart?.item_count ?? 0 }
                    }));
                    setTimeout(() => this.cartState = 'idle', 3000);
                } else {
                    this.cartState = 'idle';
                    this.cartError = (data && data.message) ? data.message : @js(ui_copy('search_cart_add_failed', 'search.cart_add_failed'));
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.cartError, type: 'error' } }));
                }
            } catch (e) {
                this.cartState = 'idle';
                this.cartError = @js(ui_copy('search_cart_add_failed', 'search.cart_add_failed'));
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.cartError, type: 'error' } }));
            }
        }
     }">

    {{-- Breadcrumbs (visible nav, mirrors the JSON-LD BreadcrumbList above) --}}
    <nav class="text-xs text-ink-muted mb-6" aria-label="Breadcrumb">
        <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink">{{ __('pages.breadcrumb_home') }}</a>
        @foreach($breadcrumbs as $crumb)
            <span class="mx-1">/</span>
            <a href="{{ $crumb['url'] }}" class="hover:text-ink">{{ $crumb['label'] }}</a>
        @endforeach
    </nav>

    {{-- In-page section nav — only lists sections that actually render, so
         it never links to a toggled-off or empty section. --}}
    <nav class="hidden sm:flex flex-wrap items-center gap-x-4 gap-y-1 sticky top-0 z-10 bg-paper/95 backdrop-blur border-b border-rule mb-6 py-2 font-mono text-[10px] font-bold uppercase tracking-[0.08em] text-ink-muted"
         aria-label="{{ __('search.nav_section_label') }}">
        @if($confirmedFitment->isNotEmpty())<a href="#fitment" class="hover:text-amber-ink">{{ __('search.nav_fitment') }}</a>@endif
        @if($showSpecs)<a href="#specs" class="hover:text-amber-ink">{{ __('search.nav_specs') }}</a>@endif
        @if($showWarranty)<a href="#warranty" class="hover:text-amber-ink">{{ __('search.nav_warranty') }}</a>@endif
        @if($showVideo)<a href="#video" class="hover:text-amber-ink">{{ __('search.nav_video') }}</a>@endif
        @if($showRelated)<a href="#related" class="hover:text-amber-ink">{{ __('search.nav_related') }}</a>@endif
        @if($showReviews)<a href="#reviews" class="hover:text-amber-ink">{{ __('search.nav_reviews') }}</a>@endif
        <a href="#faq" class="hover:text-amber-ink">{{ __('search.nav_faq') }}</a>
    </nav>

    <div class="grid grid-cols-1 md:grid-cols-[64px_1fr_1fr] gap-5 items-start">

        {{-- Thumbnail rail — vertical on desktop (stays visible beside the
             main image without competing for vertical space), horizontal
             on mobile where width is scarce. --}}
        @if($galleryImages->count() > 1)
        <div class="flex md:flex-col gap-2 overflow-x-auto md:overflow-visible order-2 md:order-1">
            @foreach($galleryImages as $index => $galleryImage)
            <button type="button"
                    @click="setImage({{ $index }}, '{{ $galleryImage->medium_url }}', '{{ $galleryImage->url }}')"
                    :class="activeIndex === {{ $index }} ? 'border-amber-ink border-2' : 'border-rule-strong'"
                    class="w-16 h-16 shrink-0 border bg-paper overflow-hidden transition-colors hover:border-ink"
                    data-testid="product-thumbnail"
                    aria-label="{{ __('search.gallery_position', ['current' => $index + 1, 'total' => $galleryImages->count()]) }}">
                <img src="{{ $galleryImage->thumbnail_url }}"
                     alt="{{ $galleryImage->alt_text ? trans_field($galleryImage->alt_text, $lang) : $productName }}"
                     width="64" height="64" class="w-full h-full object-cover">
            </button>
            @endforeach
        </div>
        @endif

        {{-- Main image — click to open a full-screen zoom view. The <img>
             keeps its own real src (not just an Alpine binding) so it's
             still a real image on first paint for crawlers/no-JS clients. --}}
        <div class="relative order-1 md:order-2">
            <button type="button" @click="zoomOpen = true"
                    class="relative block w-full border border-rule bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-ink">
                <img :src="mainImage"
                     src="{{ $product->resolvedImageUrl('medium') }}"
                     alt="{{ $mainImageAlt }}"
                     width="800" height="800"
                     class="w-full aspect-square object-contain"
                     data-testid="product-main-image">
                @if($galleryImages->count() > 1)
                <span class="absolute top-2 left-2 bg-paper border border-rule-strong font-mono text-[10px] px-2 py-1 text-ink-muted"
                      x-text="(activeIndex + 1) + ' / {{ $galleryImages->count() }}'"></span>
                @endif
                <span class="absolute bottom-2 right-2 bg-ink text-ivory font-mono text-[9.5px] uppercase tracking-wide px-2 py-1 inline-flex items-center gap-1">
                    <x-heroicon-o-magnifying-glass-plus class="w-3 h-3" />
                    {{ __('search.gallery_zoom_hint') }}
                </span>
            </button>
        </div>

        {{-- Zoom lightbox --}}
        <div x-show="zoomOpen" x-cloak @keydown.escape.window="zoomOpen = false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[80] bg-ink/92 flex items-center justify-center p-6 sm:p-10"
             role="dialog" aria-modal="true" aria-label="{{ $productName }}">
            <button type="button" @click="zoomOpen = false"
                    class="absolute top-4 right-4 w-10 h-10 border border-ivory/30 text-ivory hover:bg-amber hover:text-ink hover:border-amber flex items-center justify-center transition-colors"
                    aria-label="{{ __('part_inquiry.close') }}">
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
            <img :src="mainImageFull" :alt="'{{ $mainImageAlt }}'" @click.stop class="max-w-full max-h-full object-contain" loading="lazy">
        </div>

        {{-- Spec / CTA block --}}
        <div class="order-3">
            <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.08em] text-amber-ink bg-amber/15 px-2.5 py-1 mb-3">
                {{ __('search.genuine_badge') }}@if($conditionLabel) · {{ $conditionLabel }}@endif
            </span>
            <h1 class="text-2xl font-bold text-ink mb-1">{{ $productName }}</h1>
            <p class="font-mono text-sm text-ink-muted mb-5 oem-number">
                {{ ui_copy('search_oem_label', 'search.oem_label') }}: <span class="font-semibold text-ink">{{ $product->oem_number }}</span>
                @if($manufacturerName) — {{ $manufacturerName }} @endif
            </p>

            <div class="border border-rule mb-5">
                <div class="grid grid-cols-2 divide-x divide-rule border-b border-rule">
                    <div class="p-3">
                        <p class="bp-spec-mono mb-1">{{ ui_copy('search_availability_label', 'search.availability_label') }}</p>
                        <p class="text-sm font-bold {{ $product->is_in_stock ? 'text-green-700' : 'text-red-700' }}">
                            {{ $product->is_in_stock ? ui_copy('search_in_stock', 'search.in_stock') : ui_copy('search_out_of_stock', 'search.out_of_stock') }}
                        </p>
                    </div>
                    <div class="p-3">
                        <p class="bp-spec-mono mb-1">{{ ui_copy('search_price_label', 'search.price_label') }}</p>
                        <p class="font-mono font-bold text-lg text-ink">{{ format_price($showIncVatPrimary ? $priceWithVat : $product->price) }}</p>
                        <p class="font-mono text-[9px] tracking-[0.1em] uppercase text-ink-muted mt-0.5">
                            {{ $showIncVatPrimary ? ui_copy('search_incl_vat_short', 'search.incl_vat_short') : ui_copy('search_excl_vat_short', 'search.excl_vat_short') }}
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 divide-x divide-rule">
                    <div class="p-3">
                        <p class="bp-spec-mono mb-1">{{ __('search.delivery_label') }}</p>
                        <p class="text-sm font-bold text-ink">{{ $product->delivery_time ?: '—' }}</p>
                    </div>
                    <div class="p-3">
                        <p class="bp-spec-mono mb-1">{{ __('search.moq_label') }}</p>
                        <p class="text-sm font-bold text-ink">{{ trans_choice('search.moq_value', max(1, (int) $product->moq), ['count' => max(1, (int) $product->moq)]) }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-stretch gap-3 mb-5">
                <div class="flex items-center border border-ink">
                    <button type="button" @click="quantity = Math.max({{ max(1, (int) $product->moq) }}, quantity - 1)"
                            class="w-9 h-9 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory transition-colors"
                            aria-label="{{ ui_copy('search_aria_decrease_qty', 'search.aria_decrease_qty') }}">
                        <x-heroicon-s-minus class="w-3.5 h-3.5" />
                    </button>
                    <input type="text" inputmode="numeric" x-model.number="quantity"
                           class="w-11 h-9 text-center font-mono text-sm font-bold text-ink bg-paper border-0 border-x border-ink focus:ring-0 focus:outline-none p-0"
                           aria-label="{{ ui_copy('search_aria_quantity', 'search.aria_quantity') }}">
                    <button type="button" @click="quantity = Math.min(99, quantity + 1)"
                            class="w-9 h-9 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory transition-colors"
                            aria-label="{{ ui_copy('search_aria_increase_qty', 'search.aria_increase_qty') }}">
                        <x-heroicon-s-plus class="w-3.5 h-3.5" />
                    </button>
                </div>
                <button type="button" @click="addToCart" :disabled="cartState !== 'idle'"
                        data-testid="product-add-to-cart"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 border font-mono text-xs font-bold uppercase tracking-[0.18em] transition-colors"
                        :class="{
                            'bg-amber border-amber text-ink': cartState === 'idle',
                            'bg-amber/50 border-amber/50 text-ink/50 cursor-wait': cartState === 'loading',
                            'bg-ink border-ink text-ivory': cartState === 'added'
                        }">
                    <span x-show="cartState === 'idle'" class="inline-flex items-center gap-2">
                        <x-heroicon-s-plus class="w-3.5 h-3.5" />{{ ui_copy('search_btn_add_to_cart', 'search.btn_add_to_cart') }}
                    </span>
                    <span x-show="cartState === 'loading'" x-cloak>
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </span>
                    <span x-show="cartState === 'added'" x-cloak class="inline-flex items-center gap-2">
                        <x-heroicon-s-check class="w-3.5 h-3.5" />{{ ui_copy('search_btn_added', 'search.btn_added') }}
                    </span>
                </button>
            </div>

            @if($showBuyNow)
            <form method="POST" action="{{ route('frontend.cart.buy-now', ['lang' => $lang]) }}" class="mb-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" x-bind:value="quantity">
                <button type="submit" data-testid="product-buy-now"
                        title="{{ __('search.buy_now_helper') }}"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 font-mono text-[11px] font-bold tracking-[0.22em] uppercase border border-ink bg-ink text-ivory hover:bg-ink/90 transition-all duration-200 w-full">
                    <x-heroicon-s-bolt class="w-3.5 h-3.5" />
                    {{ ui_copy('search_btn_buy_now', 'search.btn_buy_now') }}
                </button>
            </form>
            @endif

            <button type="button" @click="$dispatch('open-inquiry-modal', { oem: '{{ $product->oem_number }}' })"
                    data-testid="product-ask-fitment"
                    class="bp-btn-outline w-full mb-6">
                <x-heroicon-o-chat-bubble-left-right class="w-3.5 h-3.5" />
                {{ __('search.ask_fitment_button') }}
            </button>

            {{-- Trust badges — same copy/icons as the site footer's trust
                 bar, so the promises made here are consistent sitewide. --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 border border-rule divide-x divide-y sm:divide-y-0 divide-rule">
                <div class="flex flex-col items-center text-center gap-1.5 p-3">
                    <x-heroicon-s-shield-check class="w-4 h-4 text-amber-ink" />
                    <span class="font-mono text-[9px] font-bold uppercase tracking-[0.06em] text-ink-muted leading-tight">{{ ui_copy('footer_oem_badge_text', 'footer.oem_badge_text') }}</span>
                </div>
                <div class="flex flex-col items-center text-center gap-1.5 p-3">
                    <x-heroicon-o-truck class="w-4 h-4 text-amber-ink" />
                    <span class="font-mono text-[9px] font-bold uppercase tracking-[0.06em] text-ink-muted leading-tight">{{ ui_copy('footer_shipping_badge_text', 'footer.shipping_badge_text') }}</span>
                </div>
                <div class="flex flex-col items-center text-center gap-1.5 p-3">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-amber-ink" />
                    <span class="font-mono text-[9px] font-bold uppercase tracking-[0.06em] text-ink-muted leading-tight">{{ ui_copy('footer_returns_badge_text', 'footer.returns_badge_text') }}</span>
                </div>
                <div class="flex flex-col items-center text-center gap-1.5 p-3">
                    <x-heroicon-s-lock-closed class="w-4 h-4 text-amber-ink" />
                    <span class="font-mono text-[9px] font-bold uppercase tracking-[0.06em] text-ink-muted leading-tight">{{ ui_copy('footer_security_badge_text', 'footer.security_badge_text') }}</span>
                </div>
            </div>
            <p x-show="cartError" x-cloak x-text="cartError" class="mt-2 text-xs text-red-700"></p>
        </div>

        {{-- Description — this, not just the JSON-LD copy, is the actual
             thin-content mitigation: it must be real on-page content a
             visitor (and crawler) can read. --}}
        <div class="order-4 md:col-span-3 border-t border-rule pt-6 mt-2">
            <p class="text-sm text-body leading-relaxed max-w-[68ch]">{{ $product->descriptionOrFallback($lang) }}</p>
        </div>

        {{-- Manufacturer trust block — logo/verified-OEM badge/country,
             pure content (no admin toggle: this is identity, not an
             optional section) using fields already on the Manufacturer
             model. --}}
        @if($product->manufacturer)
        <div class="order-5 md:col-span-3 border-t border-rule pt-6 flex items-center gap-4 flex-wrap">
            @if($product->manufacturer->logo)
            <img src="{{ $product->manufacturer->logo->file_url }}"
                 alt="{{ $manufacturerName }}"
                 class="h-10 w-auto max-w-[120px] object-contain">
            @endif
            <div class="flex items-center gap-3 flex-wrap font-mono text-[10px] font-bold uppercase tracking-[0.06em] text-ink-muted">
                @if($product->manufacturer->is_verified_oem)
                <span class="inline-flex items-center gap-1.5 text-amber-ink bg-amber/15 px-2.5 py-1">
                    <x-heroicon-s-shield-check class="w-3.5 h-3.5" />
                    {{ __('search.trust_verified_oem') }}
                </span>
                @endif
                @if($product->manufacturer->country_code)
                <span>{{ __('search.trust_country_of_origin') }}: {{ $product->manufacturer->country_code }}</span>
                @endif
            </div>
        </div>
        @endif

        {{-- Compatible Vehicle Fitment — real compatibility data (car
             models linked to this product) that the page previously loaded
             but never displayed. Repeating fitment confirmation on the PDP
             itself (not just in search filters) is the single factor auto-
             parts UX research most consistently ties to fewer returns and
             lower cart abandonment. --}}
        @if($confirmedFitment->isNotEmpty())
        <div id="fitment" class="order-6 md:col-span-3 border-t border-rule pt-6">
            <p class="bp-spec text-amber-ink mb-1">{{ __('search.fitment_title') }}</p>
            <p class="text-xs text-ink-muted mb-3">{{ __('search.fitment_subtitle') }}</p>
            <div class="border border-rule overflow-x-auto">
                <table class="w-full text-sm min-w-[420px]">
                    <thead>
                        <tr class="bg-ivory-alt border-b border-rule">
                            <th class="text-left font-mono text-[10px] uppercase tracking-wide text-ink-muted px-3 py-2">{{ __('search.fitment_make_model') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-wide text-ink-muted px-3 py-2">{{ __('search.fitment_years') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-wide text-ink-muted px-3 py-2">{{ __('search.fitment_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($confirmedFitment as $carModel)
                        <tr class="border-b border-rule last:border-b-0" data-testid="product-fitment-row">
                            <td class="px-3 py-2 font-medium text-ink">{{ trim(($carModel->manufacturer ? trans_field($carModel->manufacturer->name) : '').' '.$carModel->name) }}</td>
                            <td class="px-3 py-2 text-ink-muted">{{ $carModel->year_from }}–{{ $carModel->year_to ?: now()->year }}</td>
                            <td class="px-3 py-2"><span class="font-mono text-[11px] font-bold text-green-700">✓ {{ __('search.fitment_match') }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Cross-reference OEM numbers — same concept as the hub page's
             expandable panel, so the detail page's content isn't thinner. --}}
        @if($product->crossReferences->isNotEmpty())
        <div class="order-7 md:col-span-3 border-t border-rule pt-6">
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

        {{-- Specifications — admin-entered key/value pairs. Escaped with
             {{ }}, never {!! !!}: admin-authored but still not a place to
             relax output escaping. --}}
        @if($showSpecs)
        <div id="specs" class="order-9 md:col-span-3 border-t border-rule pt-6">
            <p class="bp-spec text-amber-ink mb-3">{{ __('search.specs_title') }}</p>
            <div class="border border-rule divide-y divide-rule max-w-2xl">
                @foreach($product->specifications as $specKey => $specValue)
                <div class="flex justify-between gap-4 px-4 py-2.5 text-sm">
                    <span class="text-ink-muted">{{ $specKey }}</span>
                    <span class="font-medium text-ink text-right">{{ $specValue }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Warranty --}}
        @if($showWarranty)
        <div id="warranty" class="order-10 md:col-span-3 border-t border-rule pt-6">
            <p class="bp-spec text-amber-ink mb-3">{{ __('search.warranty_title') }}</p>
            <p class="inline-flex items-center gap-2 font-mono text-sm font-bold text-ink border border-rule px-3 py-2">
                <x-heroicon-o-shield-check class="w-4 h-4 text-amber-ink" />
                {{ trans_choice('search.warranty_months', $product->warranty_months, ['count' => $product->warranty_months]) }}
            </p>
        </div>
        @endif

        {{-- Product video --}}
        @if($showVideo)
        <div id="video" class="order-11 md:col-span-3 border-t border-rule pt-6">
            <p class="bp-spec text-amber-ink mb-3">{{ __('search.video_title') }}</p>
            <video controls preload="none" class="w-full max-w-2xl border border-rule" poster="{{ $product->resolvedImageUrl('medium') }}">
                <source src="{{ $product->video_url }}">
            </video>
        </div>
        @endif

        {{-- Related products — automatic (same manufacturer or shared
             vehicle fitment), no manual curation. No shared product-card
             partial exists anywhere in the codebase yet (the hub renders
             rows/cards inline), so this is hand-rolled rather than
             prematurely extracting a one-call-site component. --}}
        @if($showRelated)
        <div id="related" class="order-12 md:col-span-3 border-t border-rule pt-6">
            <p class="bp-spec text-amber-ink mb-1">{{ __('search.related_title') }}</p>
            <p class="text-xs text-ink-muted mb-3">{{ __('search.related_subtitle') }}</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                @foreach($relatedProducts as $related)
                @php
                    $relatedIdSlug = app(\App\Services\ProductSlugService::class)->buildIdSlug($related, $lang);
                    $relatedName = trans_field($related->name) ?: $related->oem_number;
                @endphp
                <a href="{{ route('frontend.search.detail', ['lang' => $lang, 'oem' => $related->normalized_oem, 'idSlug' => $relatedIdSlug]) }}"
                   data-testid="product-related-link"
                   class="group border border-rule bg-paper hover:border-ink transition-colors block">
                    <div class="aspect-square bg-ivory-alt overflow-hidden">
                        <img src="{{ $related->resolvedImageUrl('thumbnail') }}" alt="{{ $relatedName }}"
                             loading="lazy" class="w-full h-full object-contain group-hover:scale-105 transition-transform">
                    </div>
                    <div class="p-2">
                        <p class="font-mono text-[10px] font-semibold text-ink truncate">{{ $related->oem_number }}</p>
                        <p class="text-[11px] text-ink-muted truncate">{{ $relatedName }}</p>
                        <p class="font-mono text-xs font-bold text-ink mt-1">{{ format_price($related->price) }}</p>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Customer reviews — open submission, admin-moderated; only
             status=approved reviews (already scoped by the controller)
             ever render here. --}}
        @if($showReviews)
        <div id="reviews" class="order-13 md:col-span-3 border-t border-rule pt-6">
            <p class="bp-spec text-amber-ink mb-3">{{ __('search.reviews_title') }}</p>

            <div class="flex items-center gap-3 mb-5">
                <span class="font-mono text-lg font-bold text-ink">{{ number_format((float) ($product->approved_reviews_avg_rating ?? 0), 1) }}</span>
                <span class="text-amber-ink text-sm" aria-hidden="true">{{ str_repeat('★', (int) round($product->approved_reviews_avg_rating ?? 0)) }}{{ str_repeat('☆', 5 - (int) round($product->approved_reviews_avg_rating ?? 0)) }}</span>
                <span class="text-xs text-ink-muted">{{ trans_choice('search.reviews_average_of', $product->approved_reviews_count ?? 0, ['count' => $product->approved_reviews_count ?? 0]) }}</span>
            </div>

            @if($approvedReviews->isEmpty())
            <p class="text-sm text-ink-muted mb-5">{{ __('search.reviews_no_reviews_yet') }}</p>
            @else
            <div class="border border-rule divide-y divide-rule mb-5">
                @foreach($approvedReviews as $review)
                <div class="p-4" data-testid="product-review-row">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <p class="text-sm font-bold text-ink">{{ $review->reviewer_name }}</p>
                        <span class="text-amber-ink text-xs" aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                    </div>
                    @if($review->title)
                    <p class="text-sm font-semibold text-ink mb-1">{{ $review->title }}</p>
                    @endif
                    <p class="text-xs text-ink-muted leading-relaxed">{{ $review->comment }}</p>
                </div>
                @endforeach
            </div>
            @endif

            <details class="border border-rule">
                <summary class="cursor-pointer select-none px-4 py-3 font-mono text-xs font-bold uppercase tracking-[0.1em] text-ink">
                    {{ __('search.reviews_write_a_review') }}
                </summary>
                <form method="POST" action="{{ route('frontend.search.review.store', ['lang' => $lang, 'oem' => $product->normalized_oem, 'idSlug' => $currentIdSlug]) }}" class="p-4 border-t border-rule space-y-3">
                    @csrf
                    {{-- Honeypot — same double-layer pattern as Contact/Part
                         Inquiry: a manual decoy field plus Spatie's own
                         honeypot + time-trap fields (validated by the
                         'honeypot' route middleware). --}}
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                    @honeypot
                    <div>
                        <label for="review_reviewer_name" class="block text-xs font-bold text-ink mb-1">{{ __('search.reviews_form_name') }}</label>
                        <input type="text" id="review_reviewer_name" name="reviewer_name" required maxlength="100"
                               class="w-full border border-rule px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-ink">
                    </div>
                    <div>
                        <label for="review_title" class="block text-xs font-bold text-ink mb-1">{{ __('search.reviews_form_title') }}</label>
                        <input type="text" id="review_title" name="title" maxlength="150"
                               class="w-full border border-rule px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-ink">
                    </div>
                    <div>
                        <label for="review_rating" class="block text-xs font-bold text-ink mb-1">{{ __('search.reviews_form_rating') }}</label>
                        <select id="review_rating" name="rating" required class="border border-rule px-3 py-2 text-sm">
                            @foreach([5, 4, 3, 2, 1] as $stars)
                            <option value="{{ $stars }}">{{ str_repeat('★', $stars) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="review_comment" class="block text-xs font-bold text-ink mb-1">{{ __('search.reviews_form_comment') }}</label>
                        <textarea id="review_comment" name="comment" required maxlength="2000" rows="3"
                                  class="w-full border border-rule px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-ink"></textarea>
                    </div>
                    <button type="submit" class="bp-btn-outline">{{ __('search.reviews_form_submit') }}</button>
                </form>
            </details>
        </div>
        @endif

        {{-- Fitment & shipping FAQ — real, visible content that mirrors
             the promises made in the trust badges/spec panel above. --}}
        <div id="faq" class="order-14 md:col-span-3 border-t border-rule pt-6">
            <p class="bp-spec text-amber-ink mb-3">{{ __('search.faq_title') }}</p>
            <div class="border border-rule divide-y divide-rule">
                <div class="p-4">
                    <p class="text-sm font-bold text-ink mb-1">{{ __('search.faq_q_fit') }}</p>
                    <p class="text-xs text-ink-muted leading-relaxed">{{ __('search.faq_a_fit') }}</p>
                </div>
                <div class="p-4">
                    <p class="text-sm font-bold text-ink mb-1">{{ __('search.faq_q_condition') }}</p>
                    <p class="text-xs text-ink-muted leading-relaxed">{{ __('search.faq_a_condition') }}</p>
                </div>
                <div class="p-4">
                    <p class="text-sm font-bold text-ink mb-1">{{ __('search.faq_q_delivery') }}</p>
                    <p class="text-xs text-ink-muted leading-relaxed">{{ __('search.faq_a_delivery') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Sticky mobile add-to-cart bar — reuses the SAME Alpine x-data scope
         (quantity/cartState/addToCart) already defined above, no duplicate
         state. Bottom placement: natural thumb reach on mobile. --}}
    @if($showStickyBar)
    <div class="md:hidden fixed bottom-0 inset-x-0 z-30 bg-paper border-t border-rule-strong p-3 flex items-stretch gap-2 shadow-[0_-2px_8px_rgba(0,0,0,0.08)]">
        <div class="flex items-center border border-ink shrink-0">
            <button type="button" @click="quantity = Math.max({{ max(1, (int) $product->moq) }}, quantity - 1)"
                    class="w-9 h-9 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory transition-colors"
                    aria-label="{{ ui_copy('search_aria_decrease_qty', 'search.aria_decrease_qty') }}">
                <x-heroicon-s-minus class="w-3.5 h-3.5" />
            </button>
            <input type="text" inputmode="numeric" x-model.number="quantity"
                   class="w-9 h-9 text-center font-mono text-sm font-bold text-ink bg-paper border-0 border-x border-ink focus:ring-0 focus:outline-none p-0"
                   aria-label="{{ ui_copy('search_aria_quantity', 'search.aria_quantity') }}">
            <button type="button" @click="quantity = Math.min(99, quantity + 1)"
                    class="w-9 h-9 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory transition-colors"
                    aria-label="{{ ui_copy('search_aria_increase_qty', 'search.aria_increase_qty') }}">
                <x-heroicon-s-plus class="w-3.5 h-3.5" />
            </button>
        </div>
        <button type="button" @click="addToCart" :disabled="cartState !== 'idle'"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 border font-mono text-[10px] font-bold uppercase tracking-[0.1em] transition-colors"
                :class="{
                    'bg-amber border-amber text-ink': cartState === 'idle',
                    'bg-amber/50 border-amber/50 text-ink/50 cursor-wait': cartState === 'loading',
                    'bg-ink border-ink text-ivory': cartState === 'added'
                }">
            <span x-show="cartState !== 'added'">{{ ui_copy('search_btn_add_to_cart', 'search.btn_add_to_cart') }}</span>
            <span x-show="cartState === 'added'" x-cloak>{{ ui_copy('search_btn_added', 'search.btn_added') }}</span>
        </button>
        @if($showBuyNow)
        <form method="POST" action="{{ route('frontend.cart.buy-now', ['lang' => $lang]) }}" class="flex-1">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="hidden" name="quantity" x-bind:value="quantity">
            <button type="submit" class="w-full h-full inline-flex items-center justify-center gap-1.5 px-2 border border-ink bg-ink text-ivory font-mono text-[10px] font-bold uppercase tracking-[0.1em]">
                {{ ui_copy('search_btn_buy_now', 'search.btn_buy_now') }}
            </button>
        </form>
        @endif
    </div>
    {{-- Spacer so the sticky bar never overlaps the FAQ/footer content --}}
    <div class="h-16"></div>
    @endif
</div>

<x-modals.part-inquiry :normalized-query="$product->oem_number" />
@endsection
