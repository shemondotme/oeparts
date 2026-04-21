@extends('frontend.checkout.layout')

@section('checkout_content')
@php
    $selected = (string) old('shipping_method_id', $selectedId ?? '');
@endphp

<div class="space-y-6">

    {{-- Sub-header --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            Shipping method<span class="text-amber">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            Carrier · Transit time · Cost
        </p>
    </header>

    {{-- Carrier options --}}
    <div class="border border-ink bg-paper" x-data="{ selected: '{{ $selected }}' }">
        @forelse($methods as $method)
            @php
                $methodId = (string) $method->id;
                $isSelected = $selected === $methodId;
                $isFree = (float) $method->flat_rate <= 0;
                $days = $method->estimated_days_min && $method->estimated_days_max
                    ? $method->estimated_days_min . '–' . $method->estimated_days_max . ' business days'
                    : 'Fast EU delivery';
                $num = str_pad((string)($loop->iteration), 2, '0', STR_PAD_LEFT);
            @endphp
            <label class="flex items-center gap-4 p-4 sm:p-5 cursor-pointer transition-colors {{ $loop->last ? '' : 'border-b border-rule' }}"
                   :class="selected === '{{ $methodId }}' ? 'bg-amber/10' : 'bg-paper hover:bg-ivory-alt'">

                {{-- Row number + radio --}}
                <div class="flex items-center gap-3 shrink-0">
                    <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted w-6">{{ $num }}</span>
                    <input type="radio"
                           name="shipping_method_id"
                           value="{{ $method->id }}"
                           x-model="selected"
                           {{ $isSelected ? 'checked' : '' }}
                           required
                           class="w-4 h-4 border-ink text-amber focus:ring-amber focus:ring-offset-0">
                </div>

                {{-- Icon --}}
                <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0 relative"
                     :class="selected === '{{ $methodId }}' ? 'border-ink bg-paper' : ''">
                    <x-heroicon-o-truck class="w-5 h-5 text-ink" />
                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-amber" aria-hidden="true"></span>
                </div>

                {{-- Text --}}
                <div class="flex-1 min-w-0">
                    <p class="font-display text-base font-bold text-ink tracking-[-0.01em]">{{ trans_field($method->name) }}</p>
                    <p class="mt-0.5 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                        <x-heroicon-o-clock class="w-3 h-3" />
                        {{ $days }}
                    </p>
                </div>

                {{-- Price --}}
                <div class="shrink-0 text-right">
                    @if($isFree)
                        <span class="inline-flex items-center gap-1 px-2 py-1 border border-amber bg-paper font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-amber-ink">
                            Free
                        </span>
                    @else
                        <span class="font-mono text-xl font-medium text-ink tabular-nums tracking-tight">€{{ number_format((float) $method->flat_rate, 2) }}</span>
                    @endif
                </div>
            </label>
        @empty
            <div class="p-5 font-mono text-xs tracking-[0.18em] uppercase text-amber-ink flex items-center gap-2">
                <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                No shipping methods available for your selected country
            </div>
        @endforelse
    </div>

    @error('shipping_method_id')
        <p class="flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
            <x-heroicon-s-exclamation-circle class="w-3 h-3" />
            {{ $message }}
        </p>
    @enderror

    {{-- Info note --}}
    <div class="flex items-start gap-3 p-4 border border-rule-strong bg-ivory-alt">
        <div class="w-8 h-8 border border-ink bg-paper flex items-center justify-center shrink-0">
            <x-heroicon-s-information-circle class="w-4 h-4 text-amber-ink" />
        </div>
        <div>
            <p class="bp-spec text-amber-ink mb-1">§ Shipping note</p>
            <p class="text-xs text-body">Free standard delivery available on all orders. Faster options calculated on the order subtotal.</p>
        </div>
    </div>
</div>
@endsection
