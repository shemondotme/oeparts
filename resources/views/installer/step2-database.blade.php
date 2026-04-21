@extends('layouts.installer')

@section('title', 'Step 2: Database Configuration')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 md:p-8">
    <h1 class="text-2xl font-bold text-navy mb-2">Database Configuration</h1>
    <p class="text-muted mb-6">Enter your MySQL database connection details.</p>

    <form method="POST" action="{{ route('installer.process-database') }}">
        @csrf

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="db_host" class="block text-sm font-medium text-slate-700 mb-1">
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
                <label for="db_port" class="block text-sm font-medium text-slate-700 mb-1">
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
            <label for="db_name" class="block text-sm font-medium text-slate-700 mb-1">
                Database Name
            </label>
            <input type="text" id="db_name" name="db_name" value="{{ old('db_name') }}"
                class="form-input w-full @error('db_name') border-red-300 @enderror"
                placeholder="oemhub" required>
            @error('db_name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">The database must already exist</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div>
                <label for="db_username" class="block text-sm font-medium text-slate-700 mb-1">
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
                <label for="db_password" class="block text-sm font-medium text-slate-700 mb-1">
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

        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                <div class="text-sm text-blue-800">
                    <span class="font-medium">Note:</span> The installer will test the connection before proceeding. Ensure your database user has permission to create tables.
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center pt-6 border-t border-slate-200">
            <a href="{{ route('installer.index') }}" class="btn-outline">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back
            </a>
            <button type="submit" class="btn-primary">
                Test Connection & Continue
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </button>
        </div>
    </form>
</div>
@endsection