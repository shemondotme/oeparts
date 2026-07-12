@extends('layouts.app')

@section('title', __('403 · Access Forbidden'))

@section('meta_description', __('You do not have authorization credentials required to access the requested system register.'))

@section('meta_robots')
    <meta name="robots" content="noindex,follow">
@endsection

@section('content')
@php
    $lang = app()->getLocale();
    $prev = url()->previous();
    $backUrl = ($prev && parse_url($prev, PHP_URL_HOST) === request()->getHost() && $prev !== url()->current())
        ? $prev
        : url('/'.$lang.'/');
@endphp

<div class="relative min-h-screen bg-ivory text-ink overflow-hidden">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- ── Dark document header ─────────────────────────────────────── --}}
    <div class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-8">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-6 border-b border-white/15">
                <nav class="flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60" aria-label="Breadcrumb">
                    <a href="{{ url('/'.$lang.'/') }}" class="hover:text-amber transition-colors">{{ __('Home') }}</a>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory">{{ __('Access Limit') }}</span>
                </nav>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    DOC · ERR/403 · SECURITY
                </span>
            </div>
            <div class="flex items-center gap-4 mb-4">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">
                    Status · HTTP · 403
                </span>
            </div>
            <h1 class="font-display font-extrabold text-ivory leading-[0.9] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
                {{ __('Access Forbidden') }}<span class="text-amber">.</span>
            </h1>
            <p class="mt-4 max-w-xl text-ivory/70 text-sm md:text-base leading-relaxed">
                {{ __('Your request parameters or authorization headers do not grant read/write access to this restricted directory ledger.') }}
            </p>
        </div>
    </div>

    {{-- ── Main card ────────────────────────────────────────────────── --}}
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <div class="relative border border-ink bg-paper"
             style="box-shadow: 8px 8px 0 rgba(220, 38, 38, 1);">
            {{-- Corner ticks --}}
            <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-red-600" aria-hidden="true"></span>
            <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-red-600" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-red-600" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-red-600" aria-hidden="true"></span>

            {{-- Card header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink">Incident · Report</span>
                <span class="bp-spec-mono">
                    {{ now()->format('Y-m-d H:i T') }}
                </span>
            </div>

            <div class="p-6 sm:p-10">
                {{-- Giant code glyph + icon --}}
                <div class="flex items-center gap-6 pb-8 mb-8 border-b border-rule">
                    <div class="w-16 h-16 border border-ink bg-ivory-alt flex items-center justify-center shrink-0"
                          style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                        <x-heroicon-o-lock-closed class="w-7 h-7 text-red-600" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="bp-spec text-red-700 mb-1">Error · Code</p>
                        <p class="font-display text-6xl md:text-7xl font-extrabold text-ink tabular-nums leading-none tracking-[-0.04em]">
                            4<span class="text-amber-ink">0</span>3
                        </p>
                        <p class="mt-2 font-mono text-[11px] tracking-[0.22em] uppercase text-ink-muted">
                            {{ __('Access Denied') }} · {{ __('Security restriction') }}
                        </p>
                    </div>
                </div>

                {{-- Spec grid --}}
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-0 border border-ink divide-y sm:divide-y-0 sm:divide-x divide-rule mb-8">
                    <div class="px-4 py-3 bg-paper">
                        <dt class="bp-spec text-ink-muted">{{ __('Status') }}</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 bg-red-600"></span>
                            403 · SHIELDED
                        </dd>
                    </div>
                    <div class="px-4 py-3 bg-paper">
                        <dt class="bp-spec text-ink-muted">{{ __('Pre-requisite') }}</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-ink uppercase tracking-wide">
                            {{ __('Auth Key') }}
                        </dd>
                    </div>
                    <div class="px-4 py-3 bg-paper">
                        <dt class="bp-spec text-ink-muted">{{ __('IP Protocol') }}</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums">
                            {{ substr(md5((string) request()->ip()), 0, 8) }}
                        </dd>
                    </div>
                </dl>

                {{-- Explanation block --}}
                <div class="border border-rule bg-ivory-alt p-5 mb-8">
                    <p class="bp-spec text-amber-ink mb-2">{{ __('What occurred') }}</p>
                    <p class="text-sm text-body leading-relaxed">
                        {{ __('The directory system detected unauthorized traffic parameters. This path is restricted to operators possessing higher verification keys or valid administrative security guards. Log in with compliant credentials or contact the support desk.') }}
                    </p>
                </div>

                {{-- Action row --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <a href="{{ $backUrl }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-ink border border-ink text-ivory
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                        <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                        {{ __('Return Back') }}
                    </a>
                    <a href="{{ url('/'.$lang.'/') }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-3 border border-ink text-ink
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-ink hover:text-ivory transition-colors">
                        <x-heroicon-s-home class="w-4 h-4" />
                        {{ __('Homepage') }}
                    </a>
                </div>
            </div>

            {{-- Trust strip --}}
            <div class="border-t border-rule bg-ivory-alt px-5 py-3 flex items-center justify-between gap-3">
                <span class="inline-flex items-center gap-1.5 bp-spec-mono">
                    <x-heroicon-s-shield-check class="w-3 h-3 text-amber-ink" />
                    {{ __('Protected system ledger') }}
                </span>
                <span class="bp-spec-mono">
                    SSL · TLS 1.3
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
