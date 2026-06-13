@extends('layouts.installer')

@section('title', 'Step 1: Requirements')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 md:p-8">
    <h1 class="text-2xl font-bold text-navy mb-2">System Requirements</h1>
    <p class="text-muted mb-6">Before proceeding, ensure your server meets the following requirements.</p>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <div>
            <h2 class="text-lg font-semibold text-slate-800 mb-3">PHP Requirements</h2>
            <div class="space-y-2">
                @foreach($requirements as $label => $met)
                <div class="flex items-center gap-3">
                    @if($met)
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                    @else
                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0" />
                    @endif
                    <span class="{{ $met ? 'text-slate-700' : 'text-red-600' }}">{{ $label }}</span>
                </div>
                @endforeach
            </div>

            <div class="mt-4 p-3 bg-slate-50 rounded-lg">
                <div class="text-sm text-slate-600">
                    <span class="font-medium">PHP Version:</span> {{ $phpVersion }}
                    @if(version_compare($phpVersion, $phpRequired, '>='))
                    <span class="ml-2 text-green-600 font-medium">✓ OK</span>
                    @else
                    <span class="ml-2 text-red-600 font-medium">✗ Requires PHP {{ $phpRequired }} or higher</span>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-slate-800 mb-3">Directory Permissions</h2>
            <div class="space-y-2">
                @foreach($permissions as $path => $writable)
                <div class="flex items-center gap-3">
                    @if($writable)
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                    @else
                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0" />
                    @endif
                    <span class="{{ $writable ? 'text-slate-700' : 'text-red-600' }}">{{ $path }}</span>
                    <span class="text-xs text-muted">({{ $writable ? 'Writable' : 'Not writable' }})</span>
                </div>
                @endforeach
            </div>

            <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="flex items-start gap-2">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div class="text-sm text-amber-800">
                        <span class="font-medium">Note:</span> If any permissions are missing, adjust them via FTP or SSH before continuing.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!collect($requirements)->every(fn($met) => $met) || !collect($permissions)->every(fn($writable) => $writable))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
            <div>
                <h3 class="font-medium text-red-800">Requirements Not Met</h3>
                <p class="text-sm text-red-700 mt-1">Please fix the issues above before proceeding with the installation.</p>
            </div>
        </div>
    </div>
    @endif

    <div class="flex justify-between items-center pt-6 border-t border-slate-200">
        <div></div>
        <div>
            @if(collect($requirements)->every(fn($met) => $met) && collect($permissions)->every(fn($writable) => $writable))
            <a href="{{ route('installer.database') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Continue to Database Setup
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </a>
            @else
            <button class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm opacity-50 cursor-not-allowed" disabled>
                Fix Requirements First
            </button>
            @endif
        </div>
    </div>
</div>
@endsection