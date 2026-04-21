@extends('layouts.app')

@section('title', 'Account Settings')

@section('content')
@php
    $lang = app()->getLocale();
@endphp

{{-- ── Breadcrumb ──────────────────────────────────────────────────────── --}}
<div class="bg-gray-50 border-b border-gray-100 py-3 px-4">
    <div class="max-w-5xl mx-auto">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
            <li>
                <a href="/{{ $lang }}/" class="hover:text-amber-text transition-colors font-medium">Home</a>
            </li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li>
                <a href="{{ route('frontend.account.dashboard', ['lang' => $lang]) }}" class="hover:text-amber-text transition-colors font-medium">My Account</a>
            </li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li class="text-navy font-semibold">Settings</li>
        </ol>
    </div>
</div>

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="bg-gradient-to-r from-navy via-navy to-blue-900 text-white py-8 px-4">
    <div class="max-w-5xl mx-auto">
        <h1 class="font-display text-3xl md:text-4xl font-bold mb-2">Account Settings</h1>
        <p class="text-white/70">Manage your profile, security, and preferences.</p>
    </div>
</div>

{{-- ── Main Content ────────────────────────────────────────────────────── --}}
<div class="bg-bg-page min-h-screen">
    <div class="max-w-3xl mx-auto px-4 py-8 space-y-8">

        @if(session('success'))
        {{-- ── Success Alert ───────────────────────────────────────────── --}}
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-start gap-3">
            <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-green-800">Success</p>
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        </div>
        @endif

        @if($errors->any())
        {{-- ── Error Alert ─────────────────────────────────────────────── --}}
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
            <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-red-800">Please fix the errors below</p>
                <ul class="text-sm text-red-700 mt-1 list-disc list-inside">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- ── Personal Information ────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-8 h-8 rounded-lg bg-amber/10 flex items-center justify-center">
                    <x-heroicon-o-user class="w-4 h-4 text-amber" />
                </div>
                <h2 class="text-base font-bold text-navy">Personal Information</h2>
            </div>
            <form action="{{ route('frontend.account.settings.update', ['lang' => $lang]) }}" method="POST" class="space-y-5" id="personal-info-form" novalidate>
                @csrf
                @method('PUT')

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="first_name" class="block text-sm font-semibold text-navy mb-2">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            value="{{ old('first_name', $user->first_name ?? '') }}"
                            required
                            class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 text-navy
                                   focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                   transition-all duration-200
                                   @error('first_name') border-red-400 focus:border-red-500 focus:ring-red/20 @enderror"
                            aria-describedby="first_name-error"
                        >
                        @error('first_name')
                        <p id="first_name-error" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                            <x-heroicon-o-exclamation-circle class="w-3.5 h-3.5 inline" />
                            {{ $message }}
                        </p>
                        @enderror
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-semibold text-navy mb-2">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            value="{{ old('last_name', $user->last_name ?? '') }}"
                            required
                            class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 text-navy
                                   focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                   transition-all duration-200
                                   @error('last_name') border-red-400 focus:border-red-500 focus:ring-red/20 @enderror"
                            aria-describedby="last_name-error"
                        >
                        @error('last_name')
                        <p id="last_name-error" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                            <x-heroicon-o-exclamation-circle class="w-3.5 h-3.5 inline" />
                            {{ $message }}
                        </p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-navy mb-2">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user->email ?? '') }}"
                        required
                        class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 text-navy
                               focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                               transition-all duration-200
                               @error('email') border-red-400 focus:border-red-500 focus:ring-red/20 @enderror"
                        aria-describedby="email-error"
                    >
                    @error('email')
                    <p id="email-error" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-o-exclamation-circle class="w-3.5 h-3.5 inline" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-semibold text-navy mb-2">
                        Phone Number
                    </label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ old('phone', $user->phone ?? '') }}"
                        class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 text-navy
                               focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                               transition-all duration-200"
                    >
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl
                               bg-gradient-to-r from-amber to-orange-500 text-navy
                               font-bold text-sm shadow-lg shadow-amber/30
                               hover:from-amber/90 hover:to-orange-400 hover:shadow-xl hover:shadow-amber/40
                               focus:outline-none focus:ring-4 focus:ring-amber/30
                               active:scale-[0.98] transition-all duration-200"
                    >
                        <x-heroicon-o-check class="w-4 h-4" />
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Change Password ─────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                    <x-heroicon-o-lock-closed class="w-4 h-4 text-red-500" />
                </div>
                <h2 class="text-base font-bold text-navy">Change Password</h2>
            </div>
            <form action="{{ route('frontend.account.password.update', ['lang' => $lang]) }}" method="POST" class="space-y-5" id="password-form" novalidate>
                @csrf

                <div>
                    <label for="current_password" class="block text-sm font-semibold text-navy mb-2">
                        Current Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            required
                            class="w-full px-4 py-2.5 pr-10 rounded-xl border-2 border-gray-200 text-navy
                                   focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                   transition-all duration-200
                                   @error('current_password') border-red-400 focus:border-red-500 focus:ring-red/20 @enderror"
                            aria-describedby="current_password-error"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('current_password')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-navy transition-colors"
                            tabindex="-1"
                        >
                            <x-heroicon-o-eye class="w-4 h-4" />
                        </button>
                    </div>
                    @error('current_password')
                    <p id="current_password-error" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-o-exclamation-circle class="w-3.5 h-3.5 inline" />
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-semibold text-navy mb-2">
                        New Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            required
                            minlength="8"
                            class="w-full px-4 py-2.5 pr-10 rounded-xl border-2 border-gray-200 text-navy
                                   focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                   transition-all duration-200
                                   @error('new_password') border-red-400 focus:border-red-500 focus:ring-red/20 @enderror"
                            aria-describedby="new_password-error password-strength-hint"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('new_password')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-navy transition-colors"
                            tabindex="-1"
                        >
                            <x-heroicon-o-eye class="w-4 h-4" />
                        </button>
                    </div>
                    @error('new_password')
                    <p id="new_password-error" class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <x-heroicon-o-exclamation-circle class="w-3.5 h-3.5 inline" />
                        {{ $message }}
                    </p>
                    @enderror
                    <p id="password-strength-hint" class="mt-1.5 text-xs text-muted">
                        Must be at least 8 characters with a mix of letters, numbers, and symbols.
                    </p>
                </div>

                <div>
                    <label for="new_password_confirmation" class="block text-sm font-semibold text-navy mb-2">
                        Confirm New Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="new_password_confirmation"
                            name="new_password_confirmation"
                            required
                            minlength="8"
                            class="w-full px-4 py-2.5 pr-10 rounded-xl border-2 border-gray-200 text-navy
                                   focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                   transition-all duration-200"
                        >
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('new_password_confirmation')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-navy transition-colors"
                            tabindex="-1"
                        >
                            <x-heroicon-o-eye class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl
                               bg-gradient-to-r from-amber to-orange-500 text-navy
                               font-bold text-sm shadow-lg shadow-amber/30
                               hover:from-amber/90 hover:to-orange-400 hover:shadow-xl hover:shadow-amber/40
                               focus:outline-none focus:ring-4 focus:ring-amber/30
                               active:scale-[0.98] transition-all duration-200"
                    >
                        <x-heroicon-o-lock-closed class="w-4 h-4" />
                        Update Password
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Notification Preferences ────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                    <x-heroicon-o-bell class="w-4 h-4 text-blue-500" />
                </div>
                <h2 class="text-base font-bold text-navy">Notification Preferences</h2>
            </div>
            <form action="{{ route('frontend.account.notifications.update', ['lang' => $lang]) }}" method="POST" class="space-y-5">
                @csrf

                <div class="space-y-4">
                    <label class="flex items-center justify-between p-4 rounded-xl border-2 border-gray-100 hover:border-gray-200 transition-colors cursor-pointer">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-shopping-bag class="w-5 h-5 text-navy" />
                            <div>
                                <p class="text-sm font-semibold text-navy">Order Updates</p>
                                <p class="text-xs text-muted">Receive notifications about order status changes</p>
                            </div>
                        </div>
                        <div class="relative">
                            <input
                                type="checkbox"
                                name="notifications[order_updates]"
                                value="1"
                                {{ old('notifications.order_updates', $user->prefers_order_notifications ?? true) ? 'checked' : '' }}
                                class="sr-only peer"
                            >
                            <div class="w-10 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber/20 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber"></div>
                        </div>
                    </label>

                    <label class="flex items-center justify-between p-4 rounded-xl border-2 border-gray-100 hover:border-gray-200 transition-colors cursor-pointer">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-envelope class="w-5 h-5 text-navy" />
                            <div>
                                <p class="text-sm font-semibold text-navy">Email Notifications</p>
                                <p class="text-xs text-muted">Get important updates via email</p>
                            </div>
                        </div>
                        <div class="relative">
                            <input
                                type="checkbox"
                                name="notifications[email_notifications]"
                                value="1"
                                {{ old('notifications.email_notifications', $user->prefers_email_notifications ?? true) ? 'checked' : '' }}
                                class="sr-only peer"
                            >
                            <div class="w-10 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber/20 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber"></div>
                        </div>
                    </label>

                    <label class="flex items-center justify-between p-4 rounded-xl border-2 border-gray-100 hover:border-gray-200 transition-colors cursor-pointer">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-megaphone class="w-5 h-5 text-navy" />
                            <div>
                                <p class="text-sm font-semibold text-navy">Promotional Emails</p>
                                <p class="text-xs text-muted">Receive deals, discounts, and new product announcements</p>
                            </div>
                        </div>
                        <div class="relative">
                            <input
                                type="checkbox"
                                name="notifications[promotional_emails]"
                                value="1"
                                {{ old('notifications.promotional_emails', $user->prefers_promotional_emails ?? false) ? 'checked' : '' }}
                                class="sr-only peer"
                            >
                            <div class="w-10 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber/20 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber"></div>
                        </div>
                    </label>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl
                               bg-gradient-to-r from-amber to-orange-500 text-navy
                               font-bold text-sm shadow-lg shadow-amber/30
                               hover:from-amber/90 hover:to-orange-400 hover:shadow-xl hover:shadow-amber/40
                               focus:outline-none focus:ring-4 focus:ring-amber/30
                               active:scale-[0.98] transition-all duration-200"
                    >
                        <x-heroicon-o-check class="w-4 h-4" />
                        Save Preferences
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Language & Region ───────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                    <x-heroicon-o-globe-alt class="w-4 h-4 text-green-500" />
                </div>
                <h2 class="text-base font-bold text-navy">Language & Region</h2>
            </div>
            <form action="{{ route('frontend.account.language.update', ['lang' => $lang]) }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="language" class="block text-sm font-semibold text-navy mb-2">
                        Preferred Language
                    </label>
                    <select
                        id="language"
                        name="language"
                        class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 text-navy bg-white
                               focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                               transition-all duration-200"
                    >
                        @php $prefLang = old('language', $user->preferred_locale ?? $lang); @endphp
                        <option value="en" {{ $prefLang === 'en' ? 'selected' : '' }}>English</option>
                        <option value="de" {{ $prefLang === 'de' ? 'selected' : '' }}>Deutsch (German)</option>
                        <option value="lt" {{ $prefLang === 'lt' ? 'selected' : '' }}>Lietuvių (Lithuanian)</option>
                        <option value="fr" {{ $prefLang === 'fr' ? 'selected' : '' }}>Français (French)</option>
                        <option value="es" {{ $prefLang === 'es' ? 'selected' : '' }}>Español (Spanish)</option>
                    </select>
                </div>

                <div>
                    <label for="timezone" class="block text-sm font-semibold text-navy mb-2">
                        Timezone
                    </label>
                    <select
                        id="timezone"
                        name="timezone"
                        class="w-full px-4 py-2.5 rounded-xl border-2 border-gray-200 text-navy bg-white
                               focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                               transition-all duration-200"
                    >
                        <option value="UTC" {{ old('timezone', $user->timezone ?? 'UTC') === 'UTC' ? 'selected' : '' }}>UTC</option>
                        <option value="America/New_York" {{ old('timezone', $user->timezone ?? '') === 'America/New_York' ? 'selected' : '' }}>Eastern Time (ET)</option>
                        <option value="America/Chicago" {{ old('timezone', $user->timezone ?? '') === 'America/Chicago' ? 'selected' : '' }}>Central Time (CT)</option>
                        <option value="America/Denver" {{ old('timezone', $user->timezone ?? '') === 'America/Denver' ? 'selected' : '' }}>Mountain Time (MT)</option>
                        <option value="America/Los_Angeles" {{ old('timezone', $user->timezone ?? '') === 'America/Los_Angeles' ? 'selected' : '' }}>Pacific Time (PT)</option>
                        <option value="Asia/Shanghai" {{ old('timezone', $user->timezone ?? '') === 'Asia/Shanghai' ? 'selected' : '' }}>China Standard Time (CST)</option>
                        <option value="Asia/Tokyo" {{ old('timezone', $user->timezone ?? '') === 'Asia/Tokyo' ? 'selected' : '' }}>Japan Standard Time (JST)</option>
                        <option value="Europe/London" {{ old('timezone', $user->timezone ?? '') === 'Europe/London' ? 'selected' : '' }}>Greenwich Mean Time (GMT)</option>
                    </select>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl
                               bg-gradient-to-r from-amber to-orange-500 text-navy
                               font-bold text-sm shadow-lg shadow-amber/30
                               hover:from-amber/90 hover:to-orange-400 hover:shadow-xl hover:shadow-amber/40
                               focus:outline-none focus:ring-4 focus:ring-amber/30
                               active:scale-[0.98] transition-all duration-200"
                    >
                        <x-heroicon-o-check class="w-4 h-4" />
                        Save Preferences
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Danger Zone ─────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border-2 border-red-100 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-500" />
                </div>
                <h2 class="text-base font-bold text-red-600">Danger Zone</h2>
            </div>
            <p class="text-sm text-muted mb-4">
                Once you delete your account, there is no going back. Please be certain.
            </p>
            <form id="account-delete-form" action="{{ route('frontend.account.delete', ['lang' => $lang]) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
            <button
                type="button"
                onclick="confirmAccountDeletion()"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl
                       border-2 border-red-300 text-red-600
                       font-bold text-sm
                       hover:bg-red-50 hover:border-red-400
                       focus:outline-none focus:ring-4 focus:ring-red/20
                       active:scale-[0.98] transition-all duration-200"
            >
                <x-heroicon-o-trash class="w-4 h-4" />
                Delete Account
            </button>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    // Toggle password visibility
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        if (input.type === 'password') {
            input.type = 'text';
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.52 10.52 0 0 1-5.228 6.186M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /></svg>`;
        } else {
            input.type = 'password';
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>`;
        }
    }

    // Client-side form validation feedback
    document.querySelectorAll('form[novalidate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('border-red-400');
                    field.classList.remove('border-gray-200', 'border-amber');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-400');
                    field.classList.add('border-gray-200');
                }
            });

            // Password match validation
            const newPassword = form.querySelector('#new_password');
            const confirmPassword = form.querySelector('#new_password_confirmation');
            if (newPassword && confirmPassword && confirmPassword.value) {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.classList.add('border-red-400');
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
            }

            form.classList.add('was-validated');
        });

        // Real-time validation feedback on blur
        form.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('border-red-400');
                    this.classList.remove('border-gray-200');
                } else {
                    this.classList.remove('border-red-400');
                    this.classList.add('border-gray-200');
                }
            });

            field.addEventListener('input', function() {
                if (this.classList.contains('border-red-400') && this.value.trim()) {
                    this.classList.remove('border-red-400');
                    this.classList.add('border-gray-200');
                }
            });
        });
    });

    // Account deletion confirmation
    function confirmAccountDeletion() {
        if (confirm('Are you absolutely sure? This action cannot be undone.')) {
            document.getElementById('account-delete-form').submit();
        }
    }
</script>
@endpush
