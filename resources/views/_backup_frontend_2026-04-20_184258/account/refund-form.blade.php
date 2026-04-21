@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">{{ __('Request a Refund — Order #:number', ['number' => $order->order_number]) }}</h1>
            <p class="text-slate-600 mt-2">{{ __('Please provide details about your refund request.') }}</p>
        </div>

        <!-- Order Summary -->
        <div class="bg-slate-50 rounded-xl p-6 mb-8">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">{{ __('Order Summary') }}</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-slate-500">{{ __('Order Number') }}</p>
                    <p class="font-medium">{{ $order->order_number }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Order Date') }}</p>
                    <p class="font-medium">{{ $order->created_at->format('F j, Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Amount') }}</p>
                    <p class="font-medium">€{{ number_format($order->grand_total, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">{{ __('Status') }}</p>
                    <p class="font-medium">{{ $order->status->label() }}</p>
                </div>
            </div>
        </div>

        <!-- Refund Form -->
        <form method="POST" action="{{ route('frontend.account.order.refund.submit', ['lang' => app()->getLocale(), 'order' => $order]) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Honeypot -->
            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

            <!-- Reason -->
            <div>
                <label for="reason" class="block text-sm font-medium text-slate-700 mb-2">
                    {{ __('Reason for refund') }} <span class="text-red-500">*</span>
                </label>
                <textarea id="reason" name="reason" rows="5" required minlength="20" maxlength="2000"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    placeholder="{{ __('Please describe why you are requesting a refund (minimum 20 characters).') }}">{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-slate-500">{{ __('Minimum 20 characters, maximum 2000 characters.') }}</p>
            </div>

            <!-- Upload Images -->
            <div>
                <label for="return_images" class="block text-sm font-medium text-slate-700 mb-2">
                    {{ __('Upload photos (optional, max 5)') }}
                </label>
                <input type="file" id="return_images" name="return_images[]" multiple
                    accept="image/jpeg,image/png"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                @error('return_images')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('return_images.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-slate-500">{{ __('Upload up to 5 images (JPEG or PNG, max 2MB each) showing the product condition if applicable.') }}</p>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-between pt-6 border-t border-slate-200">
                <a href="{{ route('frontend.account.order.detail', ['lang' => app()->getLocale(), 'order' => $order]) }}"
                   class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    {{ __('Cancel') }}
                </a>
                <button type="submit"
                        class="px-6 py-3 bg-navy text-white font-medium rounded-lg hover:bg-navy-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-navy">
                    {{ __('Submit Refund Request') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection