@extends('layouts.app')

@section('title', 'My Addresses')

@section('content')
@php
    $lang = app()->getLocale();
@endphp

{{-- ── Breadcrumb ──────────────────────────────────────────────────────── --}}
<div class="bg-gray-50 border-b border-gray-100 py-3 px-4">
    <div class="max-w-6xl mx-auto">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
            <li>
                <a href="/{{ $lang }}/" class="hover:text-amber-text transition-colors font-medium">Home</a>
            </li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li>
                <a href="{{ route('frontend.account.dashboard', ['lang' => $lang]) }}" class="hover:text-amber-text transition-colors font-medium">My Account</a>
            </li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li class="text-navy font-semibold">Addresses</li>
        </ol>
    </div>
</div>

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="bg-gradient-to-r from-navy via-navy to-blue-900 text-white py-8 px-4">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl md:text-4xl font-bold mb-2">Saved Addresses</h1>
            <p class="text-white/70">Manage your shipping addresses for faster checkout.</p>
        </div>
        <a href="{{ route('frontend.account.addresses.create', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-bold shadow-lg shadow-amber/30 hover:from-amber/90 hover:to-orange-400 transition-all duration-200 whitespace-nowrap">
            <x-heroicon-o-plus class="w-4 h-4" />
            Add New Address
        </a>
    </div>
</div>

{{-- ── Main Content ────────────────────────────────────────────────────── --}}
<div class="bg-bg-page min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="grid lg:grid-cols-4 gap-8">

            {{-- ── Sidebar ─────────────────────────────────────────────── --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sticky top-8 h-fit">
                    <nav class="space-y-1.5">
                        <a href="{{ route('frontend.account.dashboard', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-squares-2x2 class="w-5 h-5" />
                            Dashboard
                        </a>
                        <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-shopping-bag class="w-5 h-5" />
                            Orders
                        </a>
                        <a href="{{ route('frontend.account.refunds', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-arrow-path class="w-5 h-5" />
                            Refunds
                        </a>
                        <a href="{{ route('frontend.account.addresses', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-amber/10 text-amber font-semibold transition-colors hover:bg-amber/20">
                            <x-heroicon-o-map-pin class="w-5 h-5" />
                            Addresses
                        </a>
                        <a href="{{ route('frontend.account.settings', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                            Settings
                        </a>
                    </nav>
                </div>
            </div>

            {{-- ── Main Column ─────────────────────────────────────────── --}}
            <div class="lg:col-span-3">

                @if($addresses->isEmpty())
                {{-- ── Empty State ─────────────────────────────────────── --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-6">
                        <x-heroicon-o-map-pin class="w-8 h-8 text-muted" />
                    </div>
                    <h3 class="font-display text-xl font-bold text-navy mb-2">No Saved Addresses</h3>
                    <p class="text-sm text-muted max-w-md mx-auto mb-6">
                        You haven't saved any addresses yet. Add an address to speed up checkout on your next order.
                    </p>
                    <a href="{{ route('frontend.account.addresses.create', ['lang' => $lang]) }}"
                       class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-bold shadow-lg shadow-amber/30 hover:from-amber/90 hover:to-orange-400 transition-all duration-200">
                        <x-heroicon-o-plus class="w-4 h-4" />
                        Add Your First Address
                    </a>
                </div>
                @else
                {{-- ── Address Cards Grid ──────────────────────────────── --}}
                <div class="grid sm:grid-cols-2 gap-6">
                    @foreach($addresses as $addr)
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col">
                        {{-- Card Header: Icon + Label + Default Badge --}}
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber/10 flex items-center justify-center">
                                    <x-heroicon-o-map-pin class="w-5 h-5 text-amber" />
                                </div>
                                <span class="font-bold text-navy text-sm">{{ ucfirst($addr->label ?? 'Address') }}</span>
                            </div>
                            @if($addr->is_default)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">
                                <x-heroicon-o-check-circle class="w-3 h-3" />
                                Default
                            </span>
                            @endif
                        </div>

                        {{-- Address Details --}}
                        <div class="text-sm text-navy space-y-1 mb-4 flex-1">
                            <p class="font-semibold">{{ $addr->first_name }} {{ $addr->last_name }}</p>
                            @if($addr->company)
                            <p class="text-muted">{{ $addr->company }}</p>
                            @endif
                            <p class="text-muted">{{ $addr->address_line1 }}</p>
                            @if($addr->address_line2)
                            <p class="text-muted">{{ $addr->address_line2 }}</p>
                            @endif
                            <p class="text-muted">{{ $addr->postal_code }} {{ $addr->city }}</p>
                            <p class="text-muted">{{ \App\Services\ViesService::getEuCountries()[$addr->country_code] ?? $addr->country_code }}</p>
                            @if($addr->phone)
                            <p class="text-muted mt-2">
                                <x-heroicon-o-phone class="w-3.5 h-3.5 inline text-gray-400" />
                                {{ $addr->phone }}
                            </p>
                            @endif
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                            <a href="{{ route('frontend.account.addresses.edit', ['lang' => $lang, 'address' => $addr]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-navy bg-gray-50 hover:bg-gray-100 transition-colors">
                                <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                Edit
                            </a>
                            <form method="POST"
                                  action="{{ route('frontend.account.addresses.destroy', ['lang' => $lang, 'address' => $addr]) }}"
                                  class="inline"
                                  onsubmit="return confirm('Are you sure you want to delete this address?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
