@extends('frontend.checkout.layout')

@section('checkout_content')
<h2 class="font-display text-lg font-bold text-navy mb-8">Shipping Method</h2>
<p class="text-muted mb-8">Select your preferred shipping carrier</p>

<form method="POST" action="{{ route('frontend.checkout.store', ['lang' => app()->getLocale()]) }}">
    @csrf
    <div class="space-y-4">
        @foreach([
            ['id' => 'dhl', 'name' => 'DHL', 'days' => '2-3 business days', 'price' => '12.99'],
            ['id' => 'dpd', 'name' => 'DPD', 'days' => '2-3 business days', 'price' => '10.99'],
            ['id' => 'gls', 'name' => 'GLS', 'days' => '3-4 business days', 'price' => '8.99'],
            ['id' => 'free', 'name' => 'Standard', 'days' => '5-7 business days', 'price' => 'FREE'],
        ] as $carrier)
        <label class="block p-6 rounded-xl border-2 border-gray-200 hover:border-amber/50 hover:shadow-md cursor-pointer transition-all
                      {{ old('shipping_method') === $carrier['id'] ? 'border-amber bg-amber/5 shadow-md' : '' }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <input type="radio" name="shipping_method" value="{{ $carrier['id'] }}"
                           {{ old('shipping_method') === $carrier['id'] ? 'checked' : '' }} required class="w-4 h-4">
                    <div>
                        <span class="font-bold text-navy text-base">{{ $carrier['name'] }}</span>
                        <span class="text-sm text-muted ml-2">{{ $carrier['days'] }}</span>
                    </div>
                </div>
                <span class="font-bold text-lg text-amber">€{{ $carrier['price'] }}</span>
            </div>
        </label>
        @endforeach
    </div>
</form>
@endsection
