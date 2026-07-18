@extends('layouts.app')

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title')
    {{ settings_trans('seo.home_title', settings('general.site_name', 'OeParts') . ' — Genuine OEM Auto Parts') }}
@endsection

@section('meta_description')
    {{ settings_trans('seo.home_description', 'Find genuine OEM auto parts fast. Search by OEM number, compare prices, ship across the EU.') }}
@endsection

@section('og_title')
    {{ settings_trans('seo.home_title', settings('general.site_name', 'OeParts') . ' — Genuine OEM Auto Parts') }}
@endsection

@section('og_description')
    {{ settings_trans('seo.home_description', 'Find genuine OEM auto parts fast. Search by OEM number, compare prices, ship across the EU.') }}
@endsection

@section('canonical')
    <link rel="canonical" href="{{ url('/' . app()->getLocale() . '/') }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ url('/' . $hLang . '/') }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/en/') }}">
@endsection

@php
    $homeLogoPath = settings('general.logo_id', '');
    $homeLogoUrl = $homeLogoPath
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($homeLogoPath)
        : url('/favicon.svg');
@endphp
@section('json_ld')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => settings('general.site_name', 'OeParts'),
    'url' => settings('general.site_url', url('/')),
    'logo' => $homeLogoUrl,
    'sameAs' => array_values(array_filter([
        settings('social_links.facebook_url', ''),
        settings('social_links.instagram_url', ''),
        settings('social_links.twitter_url', ''),
        settings('social_links.linkedin_url', ''),
        settings('social_links.youtube_url', ''),
        settings('social_links.tiktok_url', ''),
    ])) ?: null,
    'contactPoint' => [
        '@type' => 'ContactPoint',
        'telephone' => settings('general.site_phone', '') ?: null,
        'contactType' => 'customer service',
        'areaServed' => 'EU',
        'availableLanguage' => ['English', 'German', 'Lithuanian', 'French', 'Spanish'],
    ],
]), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@type": "WebSite",
    "name": "{{ settings('general.site_name', 'OeParts') }}",
    "url": "{{ settings('general.site_url', url('/')) }}",
    "potentialAction": {
        "@type": "SearchAction",
        "target": {
            "@type": "EntryPoint",
            "urlTemplate": "{{ settings('general.site_url', url('/')) }}/{{ app()->getLocale() }}/parts/{oem}"
        },
        "query-input": "required name=oem"
    }
}
</script>
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [{
        "@type": "ListItem",
        "position": 1,
        "name": "{{ __('Home') }}",
        "item": "{{ url('/' . app()->getLocale() . '/') }}"
    }]
}
</script>
@endsection

@section('og_type', 'website')

{{-- ── Content ──────────────────────────────────────────────────────────── --}}
@section('content')
    @foreach($sections as $index => $section)
        @if($index === 0)
            {{-- Hero section loads immediately (above the fold) --}}
            @includeIf('components.sections.' . $section->type, [
                'section'    => $section,
                'sectionData' => $sectionData,
            ])
        @else
            {{-- Below-fold sections load with lazy animation --}}
            <div
                x-data="{ loaded: false }"
                x-init="
                    const observer = new IntersectionObserver(
                        (entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    loaded = true;
                                    observer.unobserve(entry.target);
                                }
                            });
                        },
                        { threshold: 0.05, rootMargin: '200px' }
                    );
                    observer.observe($el);
                "
            >
                {{-- Skeleton placeholder · Blueprint hairlines --}}
                <div
                    x-show="!loaded"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="relative bg-ivory border-b border-rule py-16 md:py-24 px-4 sm:px-6 lg:px-10"
                    aria-hidden="true"
                >
                    <div class="max-w-[1440px] mx-auto">
                        {{-- Header row skeleton --}}
                        <div class="grid grid-cols-12 gap-x-6 items-end pb-8 mb-12 border-b border-ink">
                            <div class="col-span-12 md:col-span-7 space-y-4 animate-pulse">
                                <div class="flex items-center gap-4">
                                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                                    <span class="h-3 w-32 bg-rule inline-block"></span>
                                </div>
                                <div class="h-10 md:h-14 w-4/5 bg-rule"></div>
                                <div class="h-10 md:h-14 w-2/3 bg-rule"></div>
                            </div>
                            <div class="hidden md:block col-span-5 animate-pulse">
                                <div class="h-3 w-full bg-rule mb-2"></div>
                                <div class="h-3 w-3/4 bg-rule"></div>
                            </div>
                        </div>
                        {{-- Content ledger skeleton --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 border border-ink animate-pulse">
                            @for($i = 0; $i < 3; $i++)
                            <div class="p-6 sm:p-8 border-b md:border-b-0 md:border-r last:border-r-0 border-rule bg-paper">
                                <div class="flex items-center justify-between mb-6">
                                    <span class="h-2 w-8 bg-rule"></span>
                                    <span class="w-8 h-8 border border-rule"></span>
                                </div>
                                <div class="h-10 w-1/2 bg-rule mb-6"></div>
                                <div class="h-3 w-4/5 bg-rule mb-2"></div>
                                <div class="h-3 w-3/5 bg-rule"></div>
                            </div>
                            @endfor
                        </div>
                    </div>
                </div>

                {{-- Actual content --}}
                <div
                    x-show="loaded"
                    x-transition:enter="transition ease-out duration-500"
                    x-transition:enter-start="opacity-0 translate-y-8"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-cloak
                >
                    @includeIf('components.sections.' . $section->type, [
                        'section'    => $section,
                        'sectionData' => $sectionData,
                    ])
                </div>
            </div>
        @endif
    @endforeach
@endsection
