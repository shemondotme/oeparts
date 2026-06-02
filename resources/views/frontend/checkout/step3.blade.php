@extends('frontend.checkout.layout')

@section('checkout_content')
@php
    $selected = (string) old('shipping_method_id', $selectedId ?? '');

    $shippingOptions = [
        ['id' => 1, 'name' => 'Express Shipping', 'days_min' => 3, 'days_max' => 5, 'price' => 75.00, 'icon' => 'rocket-launch'],
        ['id' => 2, 'name' => 'Standard Shipping', 'days_min' => 5, 'days_max' => 7, 'price' => 40.00, 'icon' => 'truck'],
        ['id' => 3, 'name' => 'Economy Shipping', 'days_min' => null, 'days_max' => 15, 'price' => 30.00, 'icon' => 'globe-alt'],
    ];
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
        @foreach($shippingOptions as $option)
            @php
                $optId = (string) $option['id'];
                $isSelected = $selected === $optId;
                $num = str_pad((string)($loop->iteration), 2, '0', STR_PAD_LEFT);
            @endphp
            <label class="flex items-center gap-4 p-4 sm:p-5 cursor-pointer transition-colors {{ $loop->last ? '' : 'border-b border-rule' }}"
                   :class="selected === '{{ $optId }}' ? 'bg-amber/10' : 'bg-paper hover:bg-ivory-alt'">

                {{-- Row number + radio --}}
                <div class="flex items-center gap-3 shrink-0">
                    <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted w-6">{{ $num }}</span>
                    <input type="radio"
                           name="shipping_method_id"
                           value="{{ $option['id'] }}"
                           x-model="selected"
                           {{ $isSelected ? 'checked' : '' }}
                           required
                           class="w-4 h-4 border-ink text-amber focus:ring-amber focus:ring-offset-0">
                </div>

                {{-- Icon --}}
                <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0 relative"
                     :class="selected === '{{ $optId }}' ? 'border-ink bg-paper' : ''">
                    @if($option['icon'] === 'rocket-launch')
                        <x-heroicon-o-rocket-launch class="w-5 h-5 text-ink" />
                    @elseif($option['icon'] === 'globe-alt')
                        <x-heroicon-o-globe-alt class="w-5 h-5 text-ink" />
                    @else
                        <x-heroicon-o-truck class="w-5 h-5 text-ink" />
                    @endif
                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-amber" aria-hidden="true"></span>
                </div>

                {{-- Text --}}
                <div class="flex-1 min-w-0">
                    <p class="font-display text-base font-bold text-ink tracking-[-0.01em]">{{ $option['name'] }}</p>
                    <p class="mt-0.5 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                        <x-heroicon-o-clock class="w-3 h-3" />
                        @if($option['days_min'] && $option['days_max'])
                            Estimated delivery within {{ $option['days_min'] }}–{{ $option['days_max'] }} business days
                        @elseif($option['days_max'])
                            Estimated delivery within up to {{ $option['days_max'] }} business days
                        @else
                            Fast EU delivery
                        @endif
                    </p>
                </div>

                {{-- Price --}}
                <div class="shrink-0 text-right">
                    <span class="font-mono text-xl font-medium text-ink tabular-nums tracking-tight">€{{ number_format($option['price'], 2) }}</span>
                </div>
            </label>
        @endforeach
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
            <p class="text-xs text-body">All shipments tracked and insured. Delivery times are estimates from dispatch.</p>
        </div>
    </div>
</div>
@endsection
