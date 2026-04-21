@extends('layouts.installer')

@section('title', 'Step 4: Admin Account')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 md:p-8">
    <h1 class="text-2xl font-bold text-navy mb-2">Create Admin Account</h1>
    <p class="text-muted mb-6">Set up your super administrator account.</p>

    <form method="POST" action="{{ route('installer.process-admin-account') }}">
        @csrf

        <div class="mb-6">
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
                Full Name
            </label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                class="form-input w-full @error('name') border-red-300 @enderror"
                placeholder="John Doe" required>
            @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                Email Address
            </label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                class="form-input w-full @error('email') border-red-300 @enderror"
                placeholder="admin@example.com" required>
            @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">This will be used for login and notifications</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                    Password
                </label>
                <input type="password" id="password" name="password"
                    class="form-input w-full @error('password') border-red-300 @enderror"
                    placeholder="Minimum 8 characters" required>
                @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">At least 8 characters</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">
                    Confirm Password
                </label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                    class="form-input w-full"
                    placeholder="Repeat password" required>
            </div>
        </div>

        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-start gap-2">
                <x-heroicon-o-shield-exclamation class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div class="text-sm text-amber-800">
                    <span class="font-medium">Security Note:</span> This account will have full access to the entire system. Choose a strong password and keep it safe.
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center pt-6 border-t border-slate-200">
            <a href="{{ route('installer.site-settings') }}" class="btn-outline">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back
            </a>
            <button type="submit" class="btn-primary">
                Continue
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const password = document.getElementById('password');
        const confirm = document.getElementById('password_confirmation');
        
        function validatePasswords() {
            if (password.value && confirm.value) {
                if (password.value !== confirm.value) {
                    confirm.classList.add('border-red-300');
                    confirm.classList.remove('border-green-300');
                } else {
                    confirm.classList.remove('border-red-300');
                    confirm.classList.add('border-green-300');
                }
            }
        }
        
        password.addEventListener('input', validatePasswords);
        confirm.addEventListener('input', validatePasswords);
    });
</script>
@endpush
@endsection