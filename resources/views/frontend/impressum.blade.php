@extends('layouts.app')

@php
    $siteName = settings('general.site_name', 'OeParts');
    $pageTitle = __('impressum.page_title');
    $metaDescr = __('impressum.meta_description');
    $updatedAt = now();
@endphp

@section('title'){{ $pageTitle }} · {{ $siteName }}@endsection
@section('meta_description'){{ $metaDescr }}@endsection

@section('canonical')
    <link rel="canonical" href="{{ url('/' . $lang . '/impressum') }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ url('/' . $hLang . '/impressum') }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/en/impressum') }}">
@endsection

@section('meta_robots')
    <meta name="robots" content="noindex, follow">
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — LEGAL NOTICE / IMPRESSUM
     Company particulars are read LIVE from settings('company.*') rather
     than stored as static translated content — see ImpressumController's
     docblock for why. Only labels/prose are translated per locale.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Doc header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ __('impressum.breadcrumb_home') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink truncate max-w-[14rem]">{{ __('impressum.breadcrumb_self') }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · IMPRESSUM · REV. {{ $updatedAt->format('Y.m.d') }}
            </div>
        </div>

        {{-- ═══ Hero ═══ --}}
        <header class="mb-14">
            <div class="flex items-center gap-4 mb-8">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                <span class="bp-spec text-amber-ink">{{ __('impressum.eyebrow_section') }} · {{ __('impressum.eyebrow_subject') }}</span>
            </div>

            <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                       text-4xl sm:text-5xl lg:text-6xl max-w-[22ch] break-words">
                {{ __('impressum.heading') }}<span class="text-amber">.</span>
            </h1>

            <p class="mt-6 text-base text-ink/80 leading-relaxed max-w-2xl">
                {{ __('impressum.intro') }}
            </p>
        </header>

        {{-- ═══ Main content grid ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-12 gap-y-10">

            {{-- ── Body ── --}}
            <article class="col-span-12 lg:col-span-8">
                <div class="prose prose-lg prose-slate max-w-none
                            prose-headings:font-display prose-headings:font-extrabold prose-headings:tracking-[-0.02em] prose-headings:text-ink
                            prose-h2:text-2xl prose-h2:mt-10 prose-h2:mb-4 prose-h2:border-b prose-h2:border-ink prose-h2:pb-3
                            prose-p:text-body prose-p:leading-relaxed
                            prose-a:text-ink prose-a:underline prose-a:underline-offset-4 prose-a:decoration-amber prose-a:decoration-2 hover:prose-a:text-amber-ink
                            prose-strong:text-ink prose-strong:font-bold">

                    <h2>{{ __('impressum.section_provider_title') }}</h2>
                    <dl class="not-prose border border-ink bg-paper divide-y divide-rule">
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                            <dt class="bp-spec text-ink-muted w-full sm:w-56 shrink-0">{{ __('impressum.company_name_label') }}</dt>
                            <dd class="text-sm text-ink font-medium">{{ $company['name'] }}</dd>
                        </div>
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                            <dt class="bp-spec text-ink-muted w-full sm:w-56 shrink-0">{{ __('impressum.address_label') }}</dt>
                            <dd class="text-sm text-ink">
                                @if($company['address'])
                                    {{ $company['address'] }}
                                @else
                                    <span class="text-ink-muted italic">{{ __('impressum.address_not_set') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                            <dt class="bp-spec text-ink-muted w-full sm:w-56 shrink-0">{{ __('impressum.managing_director_label') }}</dt>
                            <dd class="text-sm text-ink">
                                @if($company['managing_director'])
                                    {{ $company['managing_director'] }}
                                @else
                                    <span class="text-ink-muted italic">{{ __('impressum.address_not_set') }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <h2>{{ __('impressum.section_contact_title') }}</h2>
                    <dl class="not-prose border border-ink bg-paper divide-y divide-rule">
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                            <dt class="bp-spec text-ink-muted w-full sm:w-56 shrink-0">{{ __('impressum.email_label') }}</dt>
                            <dd class="text-sm text-ink">
                                @if($company['email'])
                                    <a href="mailto:{{ $company['email'] }}" class="text-ink underline underline-offset-4 decoration-amber decoration-2 hover:text-amber-ink">{{ $company['email'] }}</a>
                                @else
                                    <span class="text-ink-muted italic">{{ __('impressum.contact_not_set') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                            <dt class="bp-spec text-ink-muted w-full sm:w-56 shrink-0">{{ __('impressum.phone_label') }}</dt>
                            <dd class="text-sm text-ink font-mono">
                                @if($company['phone'])
                                    <a href="tel:{{ preg_replace('/\s+/', '', $company['phone']) }}" class="text-ink hover:text-amber-ink">{{ $company['phone'] }}</a>
                                @else
                                    <span class="text-ink-muted italic font-sans">{{ __('impressum.contact_not_set') }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <h2>{{ __('impressum.section_register_title') }}</h2>
                    <dl class="not-prose border border-ink bg-paper divide-y divide-rule">
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                            <dt class="bp-spec text-ink-muted w-full sm:w-56 shrink-0">{{ __('impressum.registration_number_label') }}</dt>
                            <dd class="text-sm text-ink font-mono">
                                @if($company['registration_number'])
                                    {{ $company['registration_number'] }}
                                @else
                                    <span class="text-ink-muted italic font-sans">{{ __('impressum.register_not_set') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                            <dt class="bp-spec text-ink-muted w-full sm:w-56 shrink-0">{{ __('impressum.vat_number_label') }}</dt>
                            <dd class="text-sm text-ink font-mono">
                                @if($company['vat_number'])
                                    {{ $company['vat_number'] }}
                                @else
                                    <span class="text-ink-muted italic font-sans">{{ __('impressum.register_not_set') }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <h2>{{ __('impressum.section_responsible_title') }}</h2>
                    <p>{{ __('impressum.responsible_body') }}</p>

                    <h2>{{ __('impressum.section_dispute_title') }}</h2>
                    <p>
                        {{ __('impressum.dispute_body') }}
                        <a href="https://consumer-redress.ec.europa.eu/index_en" target="_blank" rel="noopener noreferrer">{{ __('impressum.dispute_link_text') }}</a>
                    </p>
                    <p>{{ __('impressum.dispute_note') }}</p>

                    <h2>{{ __('impressum.section_liability_title') }}</h2>
                    <p>{{ __('impressum.liability_content') }}</p>
                    <p>{{ __('impressum.liability_links') }}</p>
                </div>
            </article>

            {{-- ── Aside ── --}}
            <aside class="col-span-12 lg:col-span-4 space-y-6 lg:sticky lg:top-10 lg:h-fit">

                {{-- Related pages --}}
                <div class="border border-ink bg-paper">
                    <div class="px-5 py-3 bg-ink text-ivory">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('impressum.related_pages_heading') }}</span>
                    </div>
                    <ul class="divide-y divide-rule">
                        @foreach([
                            ['privacy-policy',   __('impressum.link_privacy')],
                            ['terms-of-service', __('impressum.link_terms')],
                            ['returns-policy',   __('impressum.link_returns')],
                        ] as [$relSlug, $relLabel])
                            <li>
                                <a href="{{ url('/'.$lang.'/'.$relSlug) }}"
                                   class="group flex items-center justify-between gap-3 px-5 py-3 text-ink hover:bg-ivory-alt transition-colors">
                                    <span class="font-display text-sm font-bold tracking-[-0.01em]">{{ $relLabel }}</span>
                                    <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-ink transition-colors" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Language alternates --}}
                <div class="border border-ink bg-paper">
                    <div class="px-5 py-3 bg-ink text-ivory">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('pages.languages') }}</span>
                    </div>
                    <div class="p-4 grid grid-cols-5 gap-1">
                        @foreach(['en' => 'EN', 'de' => 'DE', 'lt' => 'LT', 'fr' => 'FR', 'es' => 'ES'] as $code => $label)
                            <a href="{{ url('/'.$code.'/impressum') }}"
                               class="inline-flex items-center justify-center h-9 font-mono text-[11px] font-bold tracking-[0.14em]
                                      border transition-colors
                                      {{ $code === $lang
                                          ? 'bg-ink text-amber border-ink'
                                          : 'bg-paper text-ink border-rule-strong hover:bg-ink hover:text-amber hover:border-ink' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Contact CTA --}}
                <div class="border border-ink bg-ink text-ivory p-5">
                    <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber mb-3">{{ __('pages.questions') }}</p>
                    <p class="font-display text-base font-extrabold tracking-[-0.02em] leading-tight">
                        {{ __('pages.contact_our_desk') }}
                    </p>
                    <a href="{{ url('/'.$lang.'/contact') }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-paper transition-colors">
                        {{ __('impressum.link_contact') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>

@endsection
