@extends('layouts.installer')

@section('title', 'Step 2: Database Configuration')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10">
    <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">Database Configuration</h1>
    <p class="text-muted mb-8">Enter your MySQL database connection details.</p>

    <form method="POST" action="{{ route('installer.process-database') }}">
        @csrf

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="db_host" class="block text-sm font-semibold text-ink mb-1.5">
                    Database Host
                </label>
                <input type="text" id="db_host" name="db_host" value="{{ old('db_host', '127.0.0.1') }}"
                    class="form-input w-full @error('db_host') border-red-300 @enderror"
                    placeholder="127.0.0.1" required>
                @error('db_host')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">Usually <code>127.0.0.1</code> or <code>localhost</code></p>
            </div>

            <div>
                <label for="db_port" class="block text-sm font-semibold text-ink mb-1.5">
                    Database Port
                </label>
                <input type="number" id="db_port" name="db_port" value="{{ old('db_port', '3306') }}"
                    class="form-input w-full @error('db_port') border-red-300 @enderror"
                    placeholder="3306" required>
                @error('db_port')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">Default MySQL port is 3306</p>
            </div>
        </div>

        <div class="mb-6">
            <label for="db_name" class="block text-sm font-semibold text-ink mb-1.5">
                Database Name
            </label>
            <input type="text" id="db_name" name="db_name" value="{{ old('db_name') }}"
                class="form-input w-full @error('db_name') border-red-300 @enderror"
                placeholder="oeparts" required>
            @error('db_name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">Must already exist, unless you check "Create this database" below</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div>
                <label for="db_username" class="block text-sm font-semibold text-ink mb-1.5">
                    Database Username
                </label>
                <input type="text" id="db_username" name="db_username" value="{{ old('db_username') }}"
                    class="form-input w-full @error('db_username') border-red-300 @enderror"
                    placeholder="root" required>
                @error('db_username')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="db_password" class="block text-sm font-semibold text-ink mb-1.5">
                    Database Password
                </label>
                <input type="password" id="db_password" name="db_password" value="{{ old('db_password') }}"
                    class="form-input w-full @error('db_password') border-red-300 @enderror"
                    placeholder="Leave empty if none">
                @error('db_password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mb-6 p-4 border border-rule rounded-xl">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="create_database" value="1" {{ old('create_database') ? 'checked' : '' }}
                    class="mt-1 rounded border-rule text-navy focus:ring-amber">
                <span>
                    <span class="block text-sm font-semibold text-ink">Create this database if it doesn't exist yet</span>
                    <span class="block text-xs text-muted mt-0.5">Requires your database user to have CREATE DATABASE privilege — common on a VPS, usually not available on shared hosting where the database must already be created via the control panel. Leave unchecked if you already created it.</span>
                </span>
            </label>
        </div>

        <div class="mb-6 p-4 bg-navy/5 border border-navy/20 rounded-xl">
            <div class="flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-navy shrink-0 mt-0.5" />
                <div class="text-sm text-navy">
                    <span class="font-semibold">Note:</span> The installer will test the connection before proceeding. Ensure your database user has permission to create tables.
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center pt-6 border-t border-rule">
            <a href="{{ route('installer.index') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-bg-page transition-all duration-200">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back
            </a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Test Connection & Continue
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </button>
        </div>
    </form>
</div>
@endsection
