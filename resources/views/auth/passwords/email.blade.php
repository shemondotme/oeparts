@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-slate-50 py-12 px-4">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-bold text-slate-900">
                {{ __('Reset Password') }}
            </h2>
            <p class="mt-2 text-center text-sm text-slate-600">
                {{ __('Enter your email address and we’ll send you a link to reset your password.') }}
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-lg bg-green-50 p-4 text-sm text-green-700" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email', ['lang' => app()->getLocale()]) }}" class="mt-8 space-y-6">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                    {{ __('Email address') }}
                </label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    autofocus
                    class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:outline-none focus:border-navy focus:ring-1 focus:ring-navy transition-colors @error('email') border-red-300 @enderror"
                    placeholder="you@example.com"
                />
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button
                    type="submit"
                    class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-navy hover:bg-navy/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-navy transition-colors"
                >
                    {{ __('Send Reset Link') }}
                </button>
            </div>

            <div class="text-center">
                <a href="{{ route('frontend.home', ['lang' => app()->getLocale()]) }}" class="text-sm text-amber-text hover:underline">
                    {{ __('Back to homepage') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection