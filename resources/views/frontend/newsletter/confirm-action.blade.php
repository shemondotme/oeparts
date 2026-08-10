@extends('layouts.app')

@php
    $siteName = settings('general.site_name', 'OeParts');
@endphp

@section('title'){{ $heading }} · {{ $siteName }}@endsection
@section('meta_robots')
    <meta name="robots" content="noindex, nofollow">
@endsection

{{--
    A bare GET link that mutates state (unsubscribe/confirm) is a classic
    email-security-scanner trap: "Safe Links"-style prefetchers and spam
    filters follow every link in an inbound email BEFORE a human ever
    clicks, silently unsubscribing or confirming on the recipient's behalf.
    This page is the read-only GET landing — it changes nothing by itself.
    Only submitting the form below (a real POST, from a real click) performs
    the actual mutation, via NewsletterController::{unsubscribe,confirm}Confirmed().
--}}
@section('content')
<div class="relative bg-ivory text-ink min-h-screen flex items-center">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-lg mx-auto px-6 py-24 text-center">
        <div class="flex items-center justify-center gap-3 mb-6">
            <span class="w-8 h-[3px] bg-amber inline-block"></span>
            <span class="bp-spec text-amber-ink">{{ __('newsletter.confirm_page_eyebrow') }}</span>
        </div>

        <h1 class="font-display font-extrabold text-ink leading-tight tracking-[-0.02em] text-3xl sm:text-4xl mb-4">
            {{ $heading }}
        </h1>

        <p class="text-base text-ink/80 leading-relaxed mb-10">
            {{ $description }}
        </p>

        <form method="POST" action="{{ $actionUrl }}">
            @csrf
            <button type="submit"
                class="inline-flex items-center justify-center px-8 py-3 bg-ink text-ivory font-medium rounded-md hover:bg-ink/90 transition-colors">
                {{ $buttonLabel }}
            </button>
        </form>
    </div>
</div>
@endsection
