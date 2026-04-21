@extends('layouts.app')

@section('title', trans_field($page->title) . ' — ' . settings('general.site_name', 'OEMHub'))

@section('canonical')
    <link rel="canonical" href="{{ url('/' . app()->getLocale() . '/' . $page->slug) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ url('/' . $hLang . '/' . $page->slug) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/en/' . $page->slug) }}">
@endsection

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="font-display text-3xl font-bold text-navy mb-6">
        {{ trans_field($page->title) }}
    </h1>
    <div class="prose prose-slate max-w-none">
        {!! trans_field($page->content) !!}
    </div>
</div>
@endsection
