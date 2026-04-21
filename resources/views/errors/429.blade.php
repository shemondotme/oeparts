@extends('layouts.app')

@section('title', __('search.error_429_title'))

@section('meta_description', __('search.error_429_message'))

@section('meta_robots')
    <meta name="robots" content="noindex,follow">
@endsection

@section('og_title', __('search.error_429_title'))

@section('og_description', __('search.error_429_message'))

@section('content')
<div class="max-w-lg mx-auto px-4 py-16 text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber/15 text-amber mb-6">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
    </div>
    <h1 class="font-display text-2xl font-bold text-navy mb-3">{{ __('search.error_429_title') }}</h1>
    <p class="text-muted text-sm leading-relaxed mb-8">{{ $message ?? __('search.error_429_message') }}</p>
    @php
        $prev = url()->previous();
        $backUrl = ($prev && parse_url($prev, PHP_URL_HOST) === request()->getHost() && $prev !== url()->current())
            ? $prev
            : route('frontend.home', ['lang' => app()->getLocale()]);
    @endphp
    <a href="{{ $backUrl }}"
       class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-navy text-white text-sm font-bold hover:bg-navy/90 transition-colors focus:outline-none focus:ring-2 focus:ring-amber/50">
        {{ __('search.error_429_back') }}
    </a>
</div>
@endsection
