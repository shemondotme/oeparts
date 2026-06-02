@props([
    'active'     => 'dashboard',
    'docId'      => null,
    'eyebrow'    => null,
    'title'      => 'My account',
    'subtitle'   => null,
    'breadcrumb' => [],
])

@php
    $lang = app()->getLocale();
    $user = auth()->user();

    $nav = [
        [
            'key'   => 'dashboard',
            'label' => __('Dashboard'),
            'num'   => '01',
            'href'  => route('frontend.account.dashboard', ['lang' => $lang]),
            'icon'  => 'heroicon-o-squares-2x2',
        ],
        [
            'key'   => 'orders',
            'label' => __('Orders'),
            'num'   => '02',
            'href'  => route('frontend.account.orders', ['lang' => $lang]),
            'icon'  => 'heroicon-o-shopping-bag',
        ],
        [
            'key'   => 'refunds',
            'label' => __('Refunds'),
            'num'   => '03',
            'href'  => route('frontend.account.refunds', ['lang' => $lang]),
            'icon'  => 'heroicon-o-arrow-path',
        ],
        [
            'key'   => 'addresses',
            'label' => __('Addresses'),
            'num'   => '04',
            'href'  => route('frontend.account.addresses', ['lang' => $lang]),
            'icon'  => 'heroicon-o-map-pin',
        ],
        [
            'key'   => 'settings',
            'label' => __('Settings'),
            'num'   => '05',
            'href'  => route('frontend.account.settings', ['lang' => $lang]),
            'icon'  => 'heroicon-o-cog-6-tooth',
        ],
    ];

    $displayName = $user?->first_name ?: ($user?->name ?: ($user?->email ?? ''));
    $initial = $user ? strtoupper(mb_substr($displayName ?: 'U', 0, 1)) : 'U';
@endphp

<div class="relative min-h-screen bg-ivory text-ink">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- ── Dark Doc Header ───────────────────────────────────────────── --}}
    <div class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-6">

            {{-- Breadcrumb / Doc-ID row --}}
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-6 border-b border-white/15">
                <nav class="flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    <a href="{{ url('/'.$lang.'/') }}" class="hover:text-amber transition-colors">Home</a>
                    <span class="text-ivory/30">/</span>
                    <a href="{{ route('frontend.account.dashboard', ['lang' => $lang]) }}" class="hover:text-amber transition-colors">Account</a>
                    @foreach($breadcrumb as $crumb)
                        <span class="text-ivory/30">/</span>
                        @if(!empty($crumb['href']))
                            <a href="{{ $crumb['href'] }}" class="hover:text-amber transition-colors">{{ $crumb['label'] }}</a>
                        @else
                            <span class="text-ivory">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
                @if($docId)
                    <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                        {{ $docId }}
                    </span>
                @endif
            </div>

            <div class="flex items-end justify-between gap-4 flex-wrap">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <span class="w-10 h-[3px] bg-amber inline-block"></span>
                        <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">
                            {{ $eyebrow ?: '§ Customer · Operating · Console' }}
                        </span>
                    </div>
                    <h1 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
                        {!! $title !!}<span class="text-amber">.</span>
                    </h1>
                    @if($subtitle)
                        <p class="mt-4 max-w-xl text-ivory/70 text-sm md:text-base leading-relaxed">
                            {{ $subtitle }}
                        </p>
                    @endif
                </div>

                {{-- Header actions slot --}}
                @if(isset($actions))
                    <div class="flex items-center gap-3 flex-wrap">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Content layout ────────────────────────────────────────────── --}}
    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-10">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mb-6 border border-emerald-600 bg-emerald-50 px-4 py-3 flex items-start gap-3"
                 style="box-shadow: 4px 4px 0 rgba(20,22,29,1);"
                 role="status" aria-live="polite">
                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" aria-hidden="true" />
                <p class="text-sm text-emerald-800">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 border border-red-600 bg-red-50 px-4 py-3 flex items-start gap-3"
                 style="box-shadow: 4px 4px 0 rgba(20,22,29,1);"
                 role="alert" aria-live="assertive">
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" aria-hidden="true" />
                <p class="text-sm text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 gap-y-8 items-start">

            {{-- ── Sidebar ───────────────────────────────────────── --}}
            <aside class="col-span-12 lg:col-span-3 lg:sticky lg:top-10 lg:h-fit">

                {{-- User identity panel --}}
                <div class="border border-ink bg-paper mb-4" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                    <div class="px-4 py-3 border-b border-ink bg-ivory-alt flex items-center justify-between">
                        <span class="bp-spec text-amber-ink">§ Account · ID</span>
                        <span class="font-mono text-[9px] tracking-[0.2em] uppercase text-emerald-700 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 bg-emerald-600 rounded-full"></span>
                            Active
                        </span>
                    </div>
                    <div class="p-4 flex items-center gap-3">
                        <div class="w-11 h-11 border border-ink bg-ink text-amber flex items-center justify-center
                                    font-display text-lg font-extrabold shrink-0"
                             style="box-shadow: 3px 3px 0 rgba(241,145,58,1);">
                            {{ $initial }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-display text-sm font-bold text-ink truncate">{{ $displayName }}</p>
                            <p class="font-mono text-[10px] tracking-[0.14em] uppercase text-ink-muted truncate">
                                {{ $user?->email }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Nav --}}
                <nav class="border border-ink bg-paper" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                    <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink">§ Nav · Index</span>
                    </div>
                    <ul class="divide-y divide-rule">
                        @foreach($nav as $item)
                            @php $isActive = $item['key'] === $active; @endphp
                            <li>
                                <a href="{{ $item['href'] }}"
                                   class="flex items-center gap-3 px-4 py-3 transition-colors group
                                          {{ $isActive ? 'bg-ink text-ivory' : 'text-ink hover:bg-ivory-alt' }}"
                                   @if($isActive) aria-current="page" @endif>
                                    <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase w-6
                                                 {{ $isActive ? 'text-amber' : 'text-ink-muted' }}">
                                        {{ $item['num'] }}
                                    </span>
                                    <x-dynamic-component :component="$item['icon']"
                                        :class="'w-4 h-4 shrink-0 ' . ($isActive ? 'text-amber' : 'text-ink-muted group-hover:text-ink')" />
                                    <span class="flex-1 font-display text-sm font-bold tracking-[-0.01em]">
                                        {{ $item['label'] }}
                                    </span>
                                    @if($isActive)
                                        <x-heroicon-s-arrow-long-right class="w-4 h-4 text-amber shrink-0" />
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                {{-- Logout --}}
                <form method="POST" action="{{ route('frontend.auth.logout', ['lang' => $lang]) }}" class="mt-4">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 border border-ink bg-paper
                                   font-mono text-[11px] font-bold tracking-[0.22em] uppercase text-ink
                                   hover:bg-red-600 hover:text-ivory hover:border-red-600 transition-colors">
                        <x-heroicon-o-arrow-left-on-rectangle class="w-4 h-4" />
                        {{ __('Sign out') }}
                    </button>
                </form>

                {{-- Support strip --}}
                <div class="mt-4 border border-rule bg-ivory-alt p-4">
                    <p class="bp-spec text-amber-ink mb-1.5">§ Support · Desk</p>
                    <p class="font-display text-sm font-bold text-ink leading-tight">Need help?</p>
                    <p class="mt-1 text-xs text-ink-muted leading-relaxed">
                        Our B2B team replies within 2 business hours.
                    </p>
                    <a href="mailto:{{ settings('general.contact_email', 'info@oeparts.lt') }}"
                       class="mt-3 inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                              border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                        Contact support
                        <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                    </a>
                </div>
            </aside>

            {{-- ── Main ────────────────────────────────────────────── --}}
            <main class="col-span-12 lg:col-span-9 min-w-0">
                {{ $slot }}
            </main>
        </div>
    </div>
</div>
