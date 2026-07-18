@extends('frontend.checkout.layout')

@section('checkout_content')
@php
    $selected = (string) old('shipping_method_id', $selectedId ?? '');
    $originCountry = (string) settings('shipping.default_origin_country', '');
@endphp

<div class="space-y-6">

    {{-- Sub-header --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            {{ ui_copy('checkout_shipping_method_heading', 'checkout.shipping_method_heading') }}<span class="text-amber-ink">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            {{ ui_copy('checkout_shipping_method_subtitle', 'checkout.shipping_method_subtitle') }}
            @if($originCountry)
                · {{ ui_copy('checkout_ships_from', 'checkout.ships_from', ['country' => localized_country_name($originCountry)]) }}
            @endif
        </p>
    </header>

    {{-- Carrier options --}}
    <div class="border border-ink bg-paper" x-data="{ selected: '{{ $selected }}' }">
        @forelse($shippingOptions as $option)
            @php
                $optId = (string) $option['id'];
                $isSelected = $selected === $optId;
            @endphp
            <label class="flex items-center gap-4 p-4 sm:p-5 cursor-pointer transition-colors {{ $loop->last ? '' : 'border-b border-rule' }}"
                   :class="selected === '{{ $optId }}' ? 'bg-amber/10' : 'bg-paper hover:bg-ivory-alt'">

                {{-- Radio --}}
                <div class="flex items-center gap-3 shrink-0">
                    <input type="radio"
                           name="shipping_method_id"
                           value="{{ $option['id'] }}"
                           x-model="selected"
                           {{ $isSelected ? 'checked' : '' }}
                           required
                           class="w-4 h-4 border-ink text-amber-ink focus:ring-amber-ink focus:ring-offset-0">
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
                            {{ ui_copy('checkout_delivery_estimate_range', 'checkout.delivery_estimate_range', ['min' => $option['days_min'], 'max' => $option['days_max']]) }}
                        @elseif($option['days_max'])
                            {{ ui_copy('checkout_delivery_estimate_upto', 'checkout.delivery_estimate_upto', ['max' => $option['days_max']]) }}
                        @else
                            {{ ui_copy('checkout_fast_eu_delivery', 'checkout.fast_eu_delivery') }}
                        @endif
                    </p>
                    @if(!empty($option['delivery_earliest']) && !empty($option['delivery_latest']))
                        @php
                            $deliveryFrom = $option['delivery_earliest']->clone()->locale(app()->getLocale());
                            $deliveryTo = $option['delivery_latest']->clone()->locale(app()->getLocale());
                            $sameDay = $deliveryFrom->isSameDay($deliveryTo);
                        @endphp
                        <p class="mt-1 font-mono text-[10px] tracking-[0.1em] text-ink-muted">
                            @if($sameDay)
                                {{ ui_copy('checkout_arrives_on', 'checkout.arrives_on', ['date' => $deliveryFrom->translatedFormat('D, j M')]) }}
                            @else
                                {{ ui_copy('checkout_arrives_between', 'checkout.arrives_between', ['from' => $deliveryFrom->translatedFormat('D, j M'), 'to' => $deliveryTo->translatedFormat('D, j M')]) }}
                            @endif
                            @if($option['dispatches_today'] ?? false)
                                · {{ ui_copy('checkout_dispatches_today', 'checkout.dispatches_today') }}
                            @endif
                        </p>
                    @endif
                </div>

                {{-- Price --}}
                <div class="shrink-0 text-right">
                    <span class="font-mono text-xl font-medium text-ink tabular-nums tracking-tight">{{ format_price($option['price']) }}</span>
                </div>
            </label>
        @empty
            <div class="p-8 text-center">
                <p class="text-sm text-ink-muted">{{ ui_copy('checkout_no_shipping_methods', 'checkout.no_shipping_methods') }}</p>
            </div>
        @endforelse
    </div>

    @error('shipping_method_id')
        <p class="flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
            <x-heroicon-s-exclamation-circle class="w-3 h-3" />
            {{ $message }}
        </p>
    @enderror

    @if(bccomp((string) ($handlingFee ?? '0.00'), '0', 2) > 0)
        <p class="font-mono text-[11px] text-ink-muted">
            {{ ui_copy('checkout_handling_fee_note', 'checkout.handling_fee_note', ['amount' => format_price($handlingFee)]) }}
        </p>
    @endif

    {{-- Rush processing add-on --}}
    @if($urgentProcessingEnabled ?? false)
    <div x-data="{ checked: {{ ($urgentProcessingSelected ?? false) ? 'true' : 'false' }} }"
         class="border border-ink bg-paper">
        <label class="flex items-start gap-4 p-4 sm:p-5 cursor-pointer transition-colors"
               :class="checked ? 'bg-amber/10' : 'hover:bg-ivory-alt'">
            <input type="checkbox" name="urgent_processing" value="1" x-model="checked"
                   {{ ($urgentProcessingSelected ?? false) ? 'checked' : '' }}
                   class="mt-1 w-4 h-4 border-ink text-amber-ink focus:ring-amber-ink focus:ring-offset-0">

            <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0"
                 :class="checked ? 'border-ink bg-paper' : ''">
                <x-heroicon-o-bolt class="w-5 h-5 text-amber-ink" />
            </div>

            <div class="flex-1 min-w-0">
                <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-amber-ink">{{ ui_copy('checkout_urgent_processing_eyebrow', 'checkout.urgent_processing_eyebrow') }}</p>
                <p class="mt-0.5 font-display text-base font-bold text-ink tracking-[-0.01em]">
                    {{ settings_trans('checkout.urgent_processing_label', 'Rush processing') }}
                </p>
                <p class="mt-0.5 text-xs text-body">
                    {{ settings_trans('checkout.urgent_processing_description', '') }}
                </p>
            </div>

            <div class="shrink-0 text-right">
                <span class="font-mono text-xl font-medium text-ink tabular-nums tracking-tight">+{{ format_price($urgentProcessingFee) }}</span>
            </div>
        </label>
    </div>
    @endif

    {{-- Info note --}}
    <div class="flex items-start gap-3 p-4 border border-rule-strong bg-ivory-alt">
        <div class="w-8 h-8 border border-ink bg-paper flex items-center justify-center shrink-0">
            <x-heroicon-s-information-circle class="w-4 h-4 text-amber-ink" />
        </div>
        <div>
            <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('checkout_shipping_note_heading', 'checkout.shipping_note_heading') }}</p>
            <p class="text-xs text-body">{{ settings_trans('shipping.note_text', 'All shipments tracked and insured. Delivery times are estimates from dispatch.') }}</p>
        </div>
    </div>
</div>
@endsection
