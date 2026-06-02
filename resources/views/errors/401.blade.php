@extends('layouts.app')

@section('title', __('401 · Unauthorized Access'))

@section('meta_description', __('Verification credentials are required to authenticate your connection to this directory.'))

@section('meta_robots')
    <meta name="robots" content="noindex,follow">
@endsection

@section('content')
@php
    $lang = app()->getLocale();
    $prev = url()->previous();
    $backUrl = ($prev && parse_url($prev, PHP_URL_HOST) === request()->getHost() && $prev !== url()->current())
        ? $prev
        : route('frontend.home', ['lang' => $lang]);
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
                    <span class="text-ivory">{{ __('Unauthorized') }}</span>
                </nav>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    DOC · ERR/401 · AUTH
                </span>
            </div>
            <div class="flex items-center gap-4 mb-4">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">
                    § Status · HTTP · 401
                </span>
            </div>
            <h1 class="font-display font-extrabold text-ivory leading-[0.9] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
                {{ __('Authentication Required') }}<span class="text-amber">.</span>
            </h1>
            <p class="mt-4 max-w-xl text-ivory/70 text-sm md:text-base leading-relaxed">
                {{ __('Access to this catalog register requires valid authentication tokens. Log in to verify your identity credentials.') }}
            </p>
        </div>
    </div>

    {{-- ── Main card ────────────────────────────────────────────────── --}}
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <div class="relative border border-ink bg-paper"
             style="box-shadow: 8px 8px 0 rgba(11, 58, 104, 1);">
            {{-- Corner ticks --}}
            <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

            {{-- Card header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink">§ Incident · Report</span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                    {{ now()->format('Y-m-d H:i T') }}
                </span>
            </div>

            <div class="p-6 sm:p-10">
                {{-- Giant code glyph + icon --}}
                <div class="flex items-center gap-6 pb-8 mb-8 border-b border-rule">
                    <div class="w-16 h-16 border border-ink bg-ivory-alt flex items-center justify-center shrink-0"
                          style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                        <x-heroicon-o-user-minus class="w-7 h-7 text-ink" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="bp-spec text-ink-muted mb-1">§ Error · Code</p>
                        <p class="font-display text-6xl md:text-7xl font-extrabold text-ink tabular-nums leading-none tracking-[-0.04em]">
                            4<span class="text-amber">0</span>1
                        </p>
                        <p class="mt-2 font-mono text-[11px] tracking-[0.22em] uppercase text-ink-muted">
                            {{ __('Verification Required') }} · {{ __('Unauthenticated') }}
                        </p>
                    </div>
                </div>

                {{-- Spec grid --}}
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-0 border border-ink divide-y sm:divide-y-0 sm:divide-x divide-rule mb-8">
                    <div class="px-4 py-3 bg-paper">
                        <dt class="bp-spec text-ink-muted">{{ __('Status') }}</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 bg-amber"></span>
                            401 · VACANT
                        </dd>
                    </div>
                    <div class="px-4 py-3 bg-paper">
                        <dt class="bp-spec text-ink-muted">{{ __('Pre-requisite') }}</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-ink uppercase tracking-wide">
                            {{ __('User Session') }}
                        </dd>
                    </div>
                    <div class="px-4 py-3 bg-paper">
                        <dt class="bp-spec text-ink-muted">{{ __('Identity Key') }}</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-ink uppercase tracking-wide">
                            {{ __('Guest') }}
                        </dd>
                    </div>
                </dl>

                {{-- Explanation block --}}
                <div class="border border-rule bg-ivory-alt p-5 mb-8">
                    <p class="bp-spec text-amber-ink mb-2">§ {{ __('What occurred') }}</p>
                    <p class="text-sm text-body leading-relaxed">
                        {{ __('The requested directory is secure. Access is limited to authenticated trade accounts or registered B2C buyers. Trigger the login panel below to establish secure session headers.') }}
                    </p>
                </div>

                {{-- Action row --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3"
                     x-data="{}">
                    <button type="button"
                            @click="$dispatch('open-auth-modal', { tab: 'login' })"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-ink border border-ink text-ivory
                                   font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                   hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                        <x-heroicon-s-user class="w-4 h-4" />
                        {{ __('Open Login') }}
                    </button>
                    <a href="{{ route('frontend.home', ['lang' => $lang]) }}"
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
                <span class="inline-flex items-center gap-1.5 font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                    <x-heroicon-s-lock-closed class="w-3 h-3 text-amber-ink" />
                    {{ __('Encrypted Session Layer') }}
                </span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                    AES · 256 GCM
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
