@extends('frontend.checkout.layout')

@php
    $otpPendingEmail = $checkoutData['otp_pending_email'] ?? null;
    $otpCodeLength = (int) settings('auth.otp_length', 6);
@endphp

@section('checkout_content')
@if($otpPendingEmail)
    {{-- ═══ Guest checkout OTP verification sub-step ═══
         Server-side (CheckoutController::processStep1) already generates and
         verifies the code — this renders the missing UI for it: without
         this, a guest had no way to ever enter the code that was emailed to
         them. --}}
    <div class="space-y-6">
        <header class="pb-4 border-b border-rule">
            <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
                {{ ui_copy('checkout_otp_step_heading', 'checkout.otp_step_heading') }}<span class="text-amber">.</span>
            </h2>
            <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
                {{ ui_copy('checkout_otp_step_subtitle', 'checkout.otp_step_subtitle') }}
            </p>
        </header>

        <div class="text-center">
            <div class="inline-flex w-12 h-12 border border-ink bg-ivory-alt items-center justify-center mb-4">
                <x-heroicon-o-envelope-open class="w-6 h-6 text-amber-ink" />
            </div>
            <p class="text-sm text-body">{{ ui_copy('checkout_otp_sent_to', 'checkout.otp_sent_to') }}</p>
            <p class="mt-0.5 font-mono text-sm font-bold text-ink break-all">{{ $otpPendingEmail }}</p>
        </div>

        <input type="hidden" name="email" value="{{ $otpPendingEmail }}">
        <input type="hidden" name="phone" value="{{ $checkoutData['otp_pending_phone'] ?? '' }}">

        <div>
            <label for="checkout-otp" class="bp-spec block mb-2 text-ink text-center">
                {{ ui_copy('checkout_otp_code_label', 'checkout.otp_code_label') }}
            </label>
            <input type="text" id="checkout-otp" name="otp"
                   inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                   maxlength="{{ $otpCodeLength }}" required autofocus
                   class="w-full px-4 py-3 border border-ink bg-paper text-center font-mono text-2xl font-bold tracking-[0.5em] text-ink focus:outline-none focus:border-amber @error('otp') border-red-600 @enderror"
                   placeholder="{{ str_repeat('•', $otpCodeLength) }}">
            @error('otp')
                <p class="mt-2 flex items-center justify-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
                    <x-heroicon-s-exclamation-circle class="w-3 h-3" /> {{ $message }}
                </p>
            @enderror
            <p class="mt-2 text-center font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                {{ ui_copy('checkout_otp_help_note', 'checkout.otp_help_note') }}
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <button type="submit" name="resend" value="1" formnovalidate
                    class="bp-btn-outline flex-1 justify-center py-3 text-sm">
                {{ ui_copy('checkout_resend_code', 'checkout.resend_code') }}
            </button>
            <button type="submit" class="bp-btn-primary flex-1 justify-center py-3 text-sm">
                <x-heroicon-s-shield-check class="w-4 h-4" />
                {{ ui_copy('checkout_verify_and_continue', 'checkout.verify_and_continue') }}
            </button>
        </div>

        <button type="submit" name="change_email" value="1" formnovalidate
                class="w-full text-center font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-ink-muted hover:text-ink transition-colors">
            {{ ui_copy('checkout_use_different_email', 'checkout.use_different_email') }}
        </button>
    </div>
@else
    <div class="space-y-6">
        <header class="pb-4 border-b border-rule">
            <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
                {{ ui_copy('checkout_contact_info_heading', 'checkout.contact_info_heading') }}<span class="text-amber">.</span>
            </h2>
            <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
                {{ ui_copy('checkout_contact_info_subtitle', 'checkout.contact_info_subtitle') }}
            </p>
        </header>

        <div>
            <label for="checkout-email" class="bp-spec block mb-2 text-ink">
                {{ ui_copy('checkout_email_address_label', 'checkout.email_address_label') }} <span class="text-red-600 normal-case tracking-normal">*</span>
            </label>
            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors @error('email') border-red-600 @enderror">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-ink-muted">
                    <x-heroicon-o-envelope class="w-4 h-4" />
                </span>
                <input type="email" id="checkout-email" name="email"
                       value="{{ old('email', $checkoutData['contact_email'] ?? auth()->user()?->email ?? '') }}"
                       placeholder="your@email.com" inputmode="email" autocomplete="email" required
                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs placeholder:tracking-normal focus:outline-none">
            </div>
            @error('email')
                <p class="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
                    <x-heroicon-s-exclamation-circle class="w-3 h-3" /> {{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label for="checkout-phone" class="bp-spec block mb-2 text-ink">
                {{ ui_copy('checkout_phone_label', 'checkout.phone_label') }} <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">{{ ui_copy('checkout_optional', 'checkout.optional') }}</span>
            </label>
            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors @error('phone') border-red-600 @enderror">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-ink-muted">
                    <x-heroicon-o-phone class="w-4 h-4" />
                </span>
                <input type="tel" id="checkout-phone" name="phone"
                       value="{{ old('phone', $checkoutData['contact_phone'] ?? '') }}"
                       placeholder="+49 123 456 7890" inputmode="tel" autocomplete="tel"
                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs placeholder:tracking-normal focus:outline-none">
            </div>
            @error('phone')
                <p class="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
                    <x-heroicon-s-exclamation-circle class="w-3 h-3" /> {{ $message }}
                </p>
            @enderror
        </div>
    </div>
@endif
@endsection
