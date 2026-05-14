@extends('layouts.app')

@section('title', __('Addresses') . ' — ' . settings('general.site_name', 'OEMHub'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php $lang = app()->getLocale(); @endphp

@section('content')
<x-account.shell
    active="addresses"
    eyebrow="§ 04 · Addresses · Register"
    title="Saved addresses"
    :subtitle="__('Dispatch endpoints tied to your account — ready to auto-fill at checkout and keep your procurement flow fast.')"
    docId="DOC · ADDRESS-REGISTER · {{ now()->format('Y.m.d') }}"
    :breadcrumb="[['label' => 'Addresses']]"
>
    <x-slot name="actions">
        <a href="{{ route('frontend.account.addresses.create', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink border border-amber
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:bg-paper hover:text-ink transition-colors">
            <x-heroicon-s-plus class="w-4 h-4" />
            {{ __('New address') }}
        </a>
    </x-slot>

    @if($addresses->isEmpty())
        {{-- ── Empty state ─────────────────────────────────────────────── --}}
        <div class="border border-ink bg-paper p-16 text-center" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-6">
                <x-heroicon-o-map-pin class="w-7 h-7 text-ink-muted" />
            </div>
            <h3 class="font-display text-3xl font-extrabold text-ink tracking-[-0.02em]">
                {{ __('No addresses on file') }}<span class="text-amber">.</span>
            </h3>
            <p class="mt-3 text-sm text-ink-muted max-w-md mx-auto leading-relaxed">
                {{ __("Add a dispatch address to speed up checkout on your next OEM order. We'll keep it on file for repeat procurement.") }}
            </p>
            <a href="{{ route('frontend.account.addresses.create', ['lang' => $lang]) }}"
               class="mt-8 inline-flex items-center gap-2 px-5 py-3 bg-ink text-ivory border border-ink
                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                      hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                <x-heroicon-s-plus class="w-4 h-4" />
                {{ __('Add first address') }}
            </a>
        </div>
    @else
        {{-- ── Summary strip ──────────────────────────────────────────── --}}
        <div class="mb-6 border border-ink bg-paper grid grid-cols-2 sm:grid-cols-3 divide-x divide-rule"
             style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('On file') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $addresses->count(), 2, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('Default') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-amber-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $addresses->where('is_default', true)->count(), 2, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="hidden sm:block px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('Countries') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $addresses->pluck('country_code')->filter()->unique()->count(), 2, '0', STR_PAD_LEFT) }}
                </p>
            </div>
        </div>

        {{-- ── Address cards grid ─────────────────────────────────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach($addresses as $idx => $addr)
                @php
                    $rowNum = $idx + 1;
                    $countryLabel = \App\Services\ViesService::getEuCountries()[$addr->country_code] ?? $addr->country_code;
                @endphp
                <div class="relative border border-ink bg-paper flex flex-col
                            {{ $addr->is_default ? 'ring-1 ring-amber' : '' }}"
                     style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">

                    {{-- Card header --}}
                    <div class="px-5 py-3 border-b border-ink bg-ivory-alt flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber-ink">
                                §{{ str_pad((string) $rowNum, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <span class="font-display text-sm font-bold tracking-[-0.01em] text-ink truncate">
                                {{ ucfirst($addr->label ?: __('Address')) }}
                            </span>
                        </div>
                        @if($addr->is_default)
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-amber text-ink
                                         font-mono text-[9px] font-bold tracking-[0.22em] uppercase">
                                <x-heroicon-s-check-badge class="w-3 h-3" />
                                {{ __('Default') }}
                            </span>
                        @else
                            <span class="font-mono text-[9px] tracking-[0.18em] uppercase text-ink-muted">
                                {{ $addr->country_code }}
                            </span>
                        @endif
                    </div>

                    {{-- Card body: leader-style rows --}}
                    <div class="p-5 flex-1 space-y-2.5">
                        <p class="font-display text-base font-extrabold tracking-[-0.01em] text-ink">
                            {{ $addr->first_name }} {{ $addr->last_name }}
                        </p>
                        @if($addr->company)
                            <p class="font-mono text-[11px] tracking-[0.08em] text-ink-muted uppercase">
                                {{ $addr->company }}
                            </p>
                        @endif

                        <div class="pt-2 space-y-1 text-sm text-body">
                            <p>{{ $addr->address_line1 }}</p>
                            @if($addr->address_line2)
                                <p class="text-ink-muted">{{ $addr->address_line2 }}</p>
                            @endif
                            <p class="font-mono tabular-nums">
                                <span class="font-bold text-ink">{{ $addr->postal_code }}</span>
                                <span class="mx-1 text-rule-strong">·</span>
                                <span>{{ $addr->city }}</span>
                            </p>
                            <p class="font-mono text-[12px] uppercase tracking-[0.12em] text-ink-muted">
                                {{ $countryLabel }}
                            </p>
                        </div>

                        @if($addr->phone)
                            <div class="pt-2 border-t border-rule mt-3 flex items-center gap-2 font-mono text-[11px] tabular-nums text-ink">
                                <x-heroicon-s-phone class="w-3.5 h-3.5 text-ink-muted" />
                                {{ $addr->phone }}
                            </div>
                        @endif
                    </div>

                    {{-- Card footer: actions --}}
                    <div class="px-5 py-3 border-t border-ink bg-ivory-alt flex items-center justify-between gap-2">
                        <a href="{{ route('frontend.account.addresses.edit', ['lang' => $lang, 'address' => $addr]) }}"
                           class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                                  border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                            <x-heroicon-s-pencil-square class="w-3.5 h-3.5" />
                            {{ __('Edit') }}
                        </a>

                        <form method="POST"
                              action="{{ route('frontend.account.addresses.destroy', ['lang' => $lang, 'address' => $addr]) }}"
                              onsubmit="return confirm('{{ __('Are you sure you want to delete this address?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-red-700
                                           border-b border-transparent hover:border-red-700 transition-colors pb-0.5">
                                <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                {{ __('Delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-account.shell>
@endsection
