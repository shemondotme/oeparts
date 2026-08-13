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
                        <p class="font-mono font-bold text-lg text-ink">{{ format_price($product->price) }}</p>
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
        <div class="md:col-span-3 border-t border-rule pt-6 mt-2">
            <p class="text-sm text-body leading-relaxed max-w-[68ch]">{{ $product->descriptionOrFallback($lang) }}</p>
        </div>

        {{-- Compatible Vehicle Fitment — real compatibility data (car
             models linked to this product) that the page previously loaded
             but never displayed. Repeating fitment confirmation on the PDP
             itself (not just in search filters) is the single factor auto-
             parts UX research most consistently ties to fewer returns and
             lower cart abandonment. --}}
        @if($confirmedFitment->isNotEmpty())
        <div class="md:col-span-3 border-t border-rule pt-6">
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
        <div class="md:col-span-3 border-t border-rule pt-6">
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

        {{-- Fitment & shipping FAQ — real, visible content that mirrors
             the promises made in the trust badges/spec panel above. --}}
        <div class="md:col-span-3 border-t border-rule pt-6">
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
</div>

<x-modals.part-inquiry :normalized-query="$product->oem_number" />
@endsection
