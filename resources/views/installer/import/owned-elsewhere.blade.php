@extends('layouts.installer')

@section('title', $error['title'])

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10 text-center">
    <div class="mb-6 flex justify-center">
        <div class="w-16 h-16 rounded-full bg-amber/15 flex items-center justify-center">
            <x-heroicon-o-lock-closed class="w-8 h-8 text-amber-text" />
        </div>
    </div>
    <h1 class="font-display text-2xl font-extrabold text-ink mb-3">{{ $error['title'] }}</h1>
    <p class="text-lg text-ink-muted mb-2">{{ $error['message'] }}</p>
    <p class="text-sm text-ink-muted">{{ $error['fix'] }}</p>
</div>
@endsection
