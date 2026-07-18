@extends('layouts.app')

@section('title', __('auth.reset_password_title') . ' — ' . settings('general.site_name', 'OeParts'))
@section('meta_robots')<meta name="robots" content="noindex, follow">@endsection

@section('content')
@php
    $pwMin = settings('auth.customer_password_min', 8);
@endphp
<div class="relative min-h-screen bg-ivory text-ink overflow-hidden"
     x-data="{ showPw: false, showPw2: false }">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- Dark header --}}
    <div class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-8">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-5 border-b border-white/15">
                <nav class="flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    <a href="{{ url('/'.app()->getLocale().'/') }}" class="hover:text-amber transition-colors">{{ __('auth.breadcrumb_home') }}</a>
                    <span class="text-ivory/30">/</span>
                    <a href="{{ route('frontend.password.request', ['lang' => app()->getLocale()]) }}" class="hover:text-amber transition-colors">{{ __('auth.breadcrumb_reset_password') }}</a>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory">{{ __('auth.breadcrumb_new_password') }}</span>
                </nav>
            </div>
            <div class="flex items-center gap-4 mb-4">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">{{ __('auth.eyebrow_set_new_password') }}</span>
            </div>
            <h1 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl">
                {{ __('auth.new_password_heading') }}<span class="text-amber">.</span>
            </h1>
            <p class="mt-3 font-mono text-[11px] tracking-[0.22em] uppercase text-ivory/70">
                {{ __('auth.new_password_subtitle', ['min' => $pwMin]) }}
            </p>
        </div>
    </div>

    <div class="relative max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <div class="relative border border-ink bg-paper bp-shadow-lg" style="--bp-shadow-color: rgba(10,18,40,0.10);">
            <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

            <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink">{{ __('auth.credentials_reset_eyebrow') }}</span>
            </div>

            <div class="p-6 sm:p-8">
                <form method="POST" action="{{ route('frontend.password.update', ['lang' => app()->getLocale()]) }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                    @honeypot

                    {{-- Email --}}
                    <div>
                        <label for="email" class="bp-spec block mb-2 text-ink">{{ __('auth.email_address') }}</label>
                        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors
                                    @error('email') !border-red-600 @enderror">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                <x-heroicon-o-envelope class="w-4 h-4" />
                            </span>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ $email ?? old('email') }}"
                                required
                                autocomplete="email"
                                autofocus
                                class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none"
                                placeholder="you@example.com"
                            />
                        </div>
                        @error('email')
                            <p class="mt-2 flex items-center gap-1.5 font-mono text-[11px] tracking-[0.08em] text-red-700">
                                <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label for="password" class="bp-spec block mb-2 text-ink">{{ __('auth.new_password_heading') }}</label>
                        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors
                                    @error('password') !border-red-600 @enderror">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                <x-heroicon-o-lock-closed class="w-4 h-4" />
                            </span>
                            <input
                                id="password"
                                :type="showPw ? 'text' : 'password'"
                                name="password"
                                required
                                autocomplete="new-password"
                                minlength="{{ $pwMin }}"
                                class="w-full pl-10 pr-11 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none"
                                placeholder="{{ __('auth.min_characters', ['min' => $pwMin]) }}"
                            />
                            <button type="button" @click="showPw = !showPw"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-ink-muted hover:text-ink hover:bg-ivory-alt transition-colors"
                                    :aria-label="showPw ? '{{ addslashes(__('auth.hide_password')) }}' : '{{ addslashes(__('auth.show_password')) }}'">
                                <x-heroicon-o-eye class="w-4 h-4" x-show="!showPw" />
                                <x-heroicon-o-eye-slash class="w-4 h-4" x-show="showPw" x-cloak />
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 flex items-center gap-1.5 font-mono text-[11px] tracking-[0.08em] text-red-700">
                                <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Confirm --}}
                    <div>
                        <label for="password-confirm" class="bp-spec block mb-2 text-ink">{{ __('auth.confirm_password') }}</label>
                        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                <x-heroicon-o-lock-closed class="w-4 h-4" />
                            </span>
                            <input
                                id="password-confirm"
                                :type="showPw2 ? 'text' : 'password'"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                class="w-full pl-10 pr-11 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none"
                                placeholder="{{ __('auth.confirm_password') }}"
                            />
                            <button type="button" @click="showPw2 = !showPw2"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-ink-muted hover:text-ink hover:bg-ivory-alt transition-colors"
                                    :aria-label="showPw2 ? '{{ addslashes(__('auth.hide_password')) }}' : '{{ addslashes(__('auth.show_password')) }}'">
                                <x-heroicon-o-eye class="w-4 h-4" x-show="!showPw2" />
                                <x-heroicon-o-eye-slash class="w-4 h-4" x-show="showPw2" x-cloak />
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="bp-btn-primary w-full justify-center py-3.5 text-sm">
                        <x-heroicon-s-key class="w-4 h-4" />
                        {{ __('auth.reset_password_heading') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </button>
                </form>

                <div class="mt-6 flex items-center gap-3">
                    <span class="flex-1 h-px bg-rule"></span>
                    <span class="font-mono text-[10px] font-bold tracking-[0.24em] uppercase text-ink-muted">{{ __('auth.or_divider') }}</span>
                    <span class="flex-1 h-px bg-rule"></span>
                </div>

                <a href="{{ route('frontend.home', ['lang' => app()->getLocale()]) }}"
                   class="bp-btn-outline w-full justify-center mt-4 py-3 text-sm">
                    <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                    {{ __('auth.back_to_homepage') }}
                </a>
            </div>

            <div class="border-t border-rule bg-ivory-alt px-5 py-3 flex items-center justify-end gap-3">
                <span class="bp-spec-mono">
                    {{ __('auth.token_single_use') }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
