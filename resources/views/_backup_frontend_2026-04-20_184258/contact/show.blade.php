@extends('layouts.app')

@section('title', trans('contact.title', [], $locale ?? 'en'))
@section('description', trans('contact.description', [], $locale ?? 'en'))

@section('content')
{{-- Hero Section --}}
<section class="bg-navy py-12 lg:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-3xl lg:text-4xl font-display font-bold text-white mb-4">
                {{ trans('contact.title', [], $locale ?? 'en') }}
            </h1>
            <p class="text-lg text-white/80 max-w-2xl mx-auto">
                {{ trans('contact.description', [], $locale ?? 'en') }}
            </p>
        </div>
    </div>
</section>

{{-- Contact Form Section --}}
<section class="py-12 lg:py-16 bg-gray-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:p-8">
            {{-- Success Message --}}
            <div id="success-message" class="hidden mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-600" />
                    <p class="text-emerald-800 font-medium" data-message="success"></p>
                </div>
            </div>

            {{-- Error Message --}}
            <div id="error-message" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-x-circle class="w-6 h-6 text-red-600" />
                    <p class="text-red-800 font-medium" data-message="error"></p>
                </div>
            </div>

            <form id="contact-form" class="space-y-6">
                @csrf

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ trans('contact.name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent"
                           placeholder="{{ trans('contact.name_placeholder') }}">
                    <p class="mt-1 text-sm text-red-500 hidden" data-error="name"></p>
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ trans('contact.email') }} <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required
                               class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent"
                               placeholder="{{ trans('contact.email_placeholder') }}">
                        <button type="button" 
                                id="send-otp-btn"
                                class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors text-sm font-medium whitespace-nowrap">
                            {{ trans('contact.verify_email') }}
                        </button>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ trans('contact.email_verification_note') }}</p>
                    <p class="mt-1 text-sm text-red-500 hidden" data-error="email"></p>
                </div>

                {{-- OTP Verification --}}
                <div id="otp-section" class="hidden">
                    <label for="otp" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ trans('contact.verification_code') }} <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="tel" 
                               id="otp" 
                               name="otp" 
                               maxlength="6"
                               inputmode="numeric"
                               class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent tracking-widest text-center text-lg"
                               placeholder="000000">
                        <button type="button" 
                                id="verify-otp-btn"
                                class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-navy/90 transition-colors text-sm font-medium whitespace-nowrap">
                            {{ trans('contact.verify') }}
                        </button>
                    </div>
                    <p class="mt-1 text-sm text-red-500 hidden" data-error="otp"></p>
                    <p class="mt-1 text-sm text-emerald-600 hidden" id="otp-verified">
                        <x-heroicon-o-check-circle class="w-4 h-4 inline mr-1" />
                        {{ trans('contact.email_verified') }}
                    </p>
                </div>

                {{-- Subject Type --}}
                <div>
                    <label for="subject_type" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ trans('contact.subject') }} <span class="text-red-500">*</span>
                    </label>
                    <select id="subject_type" 
                            name="subject_type" 
                            required
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent">
                        <option value="">{{ trans('contact.select_subject') }}</option>
                        <option value="general_inquiry">{{ trans('contact.subjects.general_inquiry') }}</option>
                        <option value="part_not_found">{{ trans('contact.subjects.part_not_found') }}</option>
                        <option value="order_issue">{{ trans('contact.subjects.order_issue') }}</option>
                        <option value="shipping_question">{{ trans('contact.subjects.shipping_question') }}</option>
                        <option value="return_refund">{{ trans('contact.subjects.return_refund') }}</option>
                        <option value="b2b_partnership">{{ trans('contact.subjects.b2b_partnership') }}</option>
                        <option value="other">{{ trans('contact.subjects.other') }}</option>
                    </select>
                    <p class="mt-1 text-sm text-red-500 hidden" data-error="subject_type"></p>
                </div>

                {{-- Order Number (optional) --}}
                <div>
                    <label for="order_number" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ trans('contact.order_number') }}
                    </label>
                    <input type="text" 
                           id="order_number" 
                           name="order_number"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent"
                           placeholder="ORD-2025-03-00123">
                </div>

                {{-- OEM Number (optional) --}}
                <div>
                    <label for="oem_number" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ trans('contact.oem_number') }}
                    </label>
                    <input type="text" 
                           id="oem_number" 
                           name="oem_number"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent font-mono"
                           placeholder="e.g., BMW-11127556503">
                </div>

                {{-- Message --}}
                <div>
                    <label for="message" class="block text-sm font-medium text-slate-700 mb-2">
                        {{ trans('contact.message') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea id="message" 
                              name="message" 
                              required
                              rows="6"
                              class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent resize-none"
                              placeholder="{{ trans('contact.message_placeholder') }}"></textarea>
                    <p class="mt-1 text-sm text-slate-500">{{ trans('contact.message_min_length') }}</p>
                    <p class="mt-1 text-sm text-red-500 hidden" data-error="message"></p>
                </div>

                {{-- Honeypot (spam protection) --}}
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                {{-- Submit Button --}}
                <div>
                    <button type="submit" 
                            id="submit-btn"
                            disabled
                            class="w-full px-6 py-3 bg-navy text-white rounded-lg hover:bg-navy/90 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ trans('contact.send_message') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Contact Info --}}
        <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center">
                <x-heroicon-o-envelope class="w-8 h-8 text-amber mx-auto mb-3" />
                <h3 class="font-semibold text-navy mb-1">{{ trans('contact.email_us') }}</h3>
                <a href="mailto:{{ config('mail.from.address') }}" class="text-amber hover:underline">
                    {{ config('mail.from.address') }}
                </a>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center">
                <x-heroicon-o-clock class="w-8 h-8 text-amber mx-auto mb-3" />
                <h3 class="font-semibold text-navy mb-1">{{ trans('contact.response_time') }}</h3>
                <p class="text-slate-600">{{ trans('contact.response_time_value') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center">
                <x-heroicon-o-shield-check class="w-8 h-8 text-amber mx-auto mb-3" />
                <h3 class="font-semibold text-navy mb-1">{{ trans('contact.secure') }}</h3>
                <p class="text-slate-600">{{ trans('contact.secure_note') }}</p>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact-form');
    const emailInput = document.getElementById('email');
    const sendOtpBtn = document.getElementById('send-otp-btn');
    const otpSection = document.getElementById('otp-section');
    const otpInput = document.getElementById('otp');
    const verifyOtpBtn = document.getElementById('verify-otp-btn');
    const submitBtn = document.getElementById('submit-btn');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    const otpVerified = document.getElementById('otp-verified');

    let isEmailVerified = false;

    // Show error message
    function showError(message) {
        errorMessage.classList.remove('hidden');
        errorMessage.querySelector('[data-message="error"]').textContent = message;
        successMessage.classList.add('hidden');
    }

    // Show success message
    function showSuccess(message) {
        successMessage.classList.remove('hidden');
        successMessage.querySelector('[data-message="success"]').textContent = message;
        errorMessage.classList.add('hidden');
    }

    // Clear errors
    function clearErrors() {
        document.querySelectorAll('[data-error]').forEach(el => el.classList.add('hidden'));
        errorMessage.classList.add('hidden');
    }

    // Send OTP
    sendOtpBtn.addEventListener('click', async function() {
        clearErrors();
        const email = emailInput.value.trim();

        if (!email) {
            document.querySelector('[data-error="email"]').classList.remove('hidden');
            document.querySelector('[data-error="email"]').textContent = 'Please enter your email';
            return;
        }

        sendOtpBtn.disabled = true;
        sendOtpBtn.textContent = '{{ trans("contact.sending") }}...';

        try {
            const response = await fetch('{{ route("frontend.contact.send-otp", ["lang" => $locale ?? "en"]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_csrf"]').value
                },
                body: JSON.stringify({ email })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                otpSection.classList.remove('hidden');
                otpInput.focus();
                showSuccess(data.message);
            } else {
                showError(data.message || 'Failed to send verification code');
            }
        } catch (error) {
            showError('An error occurred. Please try again.');
        } finally {
            sendOtpBtn.disabled = false;
            sendOtpBtn.textContent = '{{ trans("contact.verify_email") }}';
        }
    });

    // Verify OTP
    verifyOtpBtn.addEventListener('click', async function() {
        clearErrors();
        const email = emailInput.value.trim();
        const otp = otpInput.value.trim();

        if (!otp || otp.length !== 6) {
            document.querySelector('[data-error="otp"]').classList.remove('hidden');
            document.querySelector('[data-error="otp"]').textContent = 'Please enter a valid 6-digit code';
            return;
        }

        verifyOtpBtn.disabled = true;
        verifyOtpBtn.textContent = '{{ trans("contact.verifying") }}...';

        try {
            const response = await fetch('{{ route("frontend.contact.verify-otp", ["lang" => $locale ?? "en"]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_csrf"]').value
                },
                body: JSON.stringify({ email, otp })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                isEmailVerified = true;
                submitBtn.disabled = false;
                otpVerified.classList.remove('hidden');
                verifyOtpBtn.classList.add('hidden');
                showSuccess(data.message);
            } else {
                showError(data.message || 'Invalid verification code');
            }
        } catch (error) {
            showError('An error occurred. Please try again.');
        } finally {
            verifyOtpBtn.disabled = false;
            verifyOtpBtn.textContent = '{{ trans("contact.verify") }}';
        }
    });

    // Submit form
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        clearErrors();

        if (!isEmailVerified) {
            showError('Please verify your email first');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = '{{ trans("contact.sending") }}...';

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('{{ route("frontend.contact.submit", ["lang" => $locale ?? "en"]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_csrf"]').value
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showSuccess(result.message);
                form.reset();
                otpSection.classList.add('hidden');
                otpVerified.classList.add('hidden');
                verifyOtpBtn.classList.remove('hidden');
                submitBtn.disabled = true;
                isEmailVerified = false;
            } else {
                showError(result.message || 'Failed to send message');
            }
        } catch (error) {
            showError('An error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '{{ trans("contact.send_message") }}';
        }
    });
});
</script>
@endpush
@endsection
