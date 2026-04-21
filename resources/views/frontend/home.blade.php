@extends('layouts.app')

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title')
    {{ trans_field(settings('seo.homepage_title', null)) ?: settings('general.site_name', 'OEMHub') . ' — Genuine OEM Auto Parts' }}
@endsection

@section('meta_description')
    {{ trans_field(settings('seo.homepage_description', null)) ?: 'Find genuine OEM auto parts fast. Search by OEM number, compare prices, ship across the EU.' }}
@endsection

@section('og_title')
    {{ trans_field(settings('seo.homepage_title', null)) ?: settings('general.site_name', 'OEMHub') . ' — Genuine OEM Auto Parts' }}
@endsection

@section('og_description')
    {{ trans_field(settings('seo.homepage_description', null)) ?: 'Find genuine OEM auto parts fast. Search by OEM number, compare prices, ship across the EU.' }}
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

@section('json_ld')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => settings('general.site_name', 'OEMHub'),
    'url' => settings('general.site_url', url('/')),
    'logo' => settings('general.site_url', url('/')) . '/logo.png',
    'sameAs' => array_values(array_filter([
        settings('contact.facebook_url', ''),
        settings('contact.linkedin_url', ''),
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
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "{{ settings('general.site_name', 'OEMHub') }}",
    "url": "{{ settings('general.site_url', url('/')) }}",
    "potentialAction": {
        "@type": "SearchAction",
        "target": {
            "@type": "EntryPoint",
            "urlTemplate": "{{ settings('general.site_url', url('/')) }}/{lang}/parts/{oem}",
            "actionPlatform": ["http://schema.org/DesktopWebPlatform", "http://schema.org/MobileWebPlatform"]
        },
        "query-input": "required name=oem"
    }
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [{
        "@type": "ListItem",
        "position": 1,
        "name": "Home",
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
                {{-- Skeleton placeholder while loading --}}
                <div
                    x-show="!loaded"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="py-14 md:py-20 px-4 bg-gray-50 animate-pulse"
                    aria-hidden="true"
                >
                    <div class="max-w-4xl mx-auto space-y-6">
                        <div class="h-8 bg-gray-200 rounded-lg w-1/3 mx-auto"></div>
                        <div class="h-4 bg-gray-200 rounded w-2/3 mx-auto"></div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="h-48 bg-gray-200 rounded-2xl"></div>
                            <div class="h-48 bg-gray-200 rounded-2xl"></div>
                            <div class="h-48 bg-gray-200 rounded-2xl"></div>
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
