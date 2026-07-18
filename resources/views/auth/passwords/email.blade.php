@extends('layouts.app')

@section('title', __('auth.reset_password_title') . ' — ' . settings('general.site_name', 'OeParts'))
@section('meta_robots')<meta name="robots" content="noindex, follow">@endsection

@section('content')
<div class="relative min-h-screen bg-ivory text-ink overflow-hidden">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- Dark document header --}}
    <div class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-8">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-5 border-b border-white/15">
                <nav class="flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    <a href="{{ url('/'.app()->getLocale().'/') }}" class="hover:text-amber transition-colors">{{ __('auth.breadcrumb_home') }}</a>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory">{{ __('auth.breadcrumb_reset_password') }}</span>
                </nav>
            </div>
            <div class="flex items-center gap-4 mb-4">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">{{ __('auth.eyebrow_request_link') }}</span>
            </div>
            <h1 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl">
                {{ __('auth.reset_password_heading') }}<span class="text-amber">.</span>
            </h1>
            <p class="mt-3 font-mono text-[11px] tracking-[0.22em] uppercase text-ivory/70">
                {{ __('auth.request_link_subtitle') }}
            </p>
        </div>
    </div>

    {{-- Card --}}
    <div class="relative max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <div class="relative border border-ink bg-paper bp-shadow-lg" style="--bp-shadow-color: rgba(10,18,40,0.10);">
            <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

            {{-- Card header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink">{{ __('auth.email_verification_eyebrow') }}</span>
            </div>

            <div class="p-6 sm:p-8">
                {{-- Status alert --}}
                @if (session('status'))
                    <div class="mb-6 flex items-start gap-3 px-4 py-3 border border-emerald-600 bg-emerald-50">
                        <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                        <span class="font-mono text-[11px] tracking-[0.06em] text-emerald-700 leading-relaxed">
                            {{ session('status') }}
                        </span>
                    </div>
                @endif

                <form method="POST" action="{{ route('frontend.password.email', ['lang' => app()->getLocale()]) }}" class="space-y-5">
                    @csrf
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                    @honeypot

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
                                value="{{ old('email') }}"
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

                    <button type="submit" class="bp-btn-primary w-full justify-center py-3.5 text-sm">
                        <x-heroicon-s-paper-airplane class="w-4 h-4" />
                        {{ __('auth.send_reset_link') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </button>
                </form>

                {{-- Divider --}}
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

            {{-- Trust strip --}}
            <div class="border-t border-rule bg-ivory-alt px-5 py-3 flex items-center justify-between gap-3">
                <span class="bp-spec-mono">
                    {{ __('auth.expires_minutes', ['minutes' => config('auth.passwords.users.expire', 60)]) }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
