@extends('frontend.checkout.layout')

@section('checkout_content')
<h2 class="font-display text-lg font-bold text-navy mb-8">Shipping Address</h2>

<form method="POST" action="{{ route('frontend.checkout.store', ['lang' => app()->getLocale()]) }}" class="space-y-6">
    @csrf

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-bold text-navy mb-3">First Name *</label>
            <input type="text" name="first_name" value="{{ old('first_name') }}" required
                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all">
        </div>
        <div>
            <label class="block text-sm font-bold text-navy mb-3">Last Name *</label>
            <input type="text" name="last_name" value="{{ old('last_name') }}" required
                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all">
        </div>
    </div>

    <div>
        <label class="block text-sm font-bold text-navy mb-2">Street Address *</label>
        <input type="text" name="street" value="{{ old('street') }}" required
               class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all">
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-bold text-navy mb-3">City *</label>
            <input type="text" name="city" value="{{ old('city') }}" required
                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all">
        </div>
        <div>
            <label class="block text-sm font-bold text-navy mb-3">ZIP Code *</label>
            <input type="text" name="postal_code" value="{{ old('postal_code') }}" required inputmode="numeric"
                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all">
        </div>
    </div>

    <div>
        <label class="block text-sm font-bold text-navy mb-2">Country *</label>
        <select name="country_code" required
                class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all">
            <option value="">Select a country</option>
            <option value="DE">Germany</option>
            <option value="AT">Austria</option>
            <option value="FR">France</option>
            <option value="LT">Lithuania</option>
            <option value="ES">Spain</option>
        </select>
    </div>

    <div class="p-4 rounded-xl bg-blue-50 border-2 border-blue-100 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/></svg>
        <p class="text-sm text-blue-700">We ship to all EU countries. Shipping cost calculated in the next step.</p>
    </div>
</form>
@endsection
