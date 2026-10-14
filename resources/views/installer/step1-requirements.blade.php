@extends('layouts.installer')

@section('title', 'Step 1: Requirements')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10">
    <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">System Requirements</h1>
    <p class="text-ink-muted mb-8">Before proceeding, ensure your server meets the following requirements.</p>

    <div class="grid md:grid-cols-2 gap-8 mb-8">
        <div>
            <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">PHP Requirements</h2>
            <div class="space-y-2">
                @foreach($requirements as $label => $met)
                <div class="flex items-center gap-3">
                    @if($met)
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                    @else
                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0" />
                    @endif
                    <span class="{{ $met ? 'text-body' : 'text-red-600' }}">{{ $label }}</span>
                </div>
                @endforeach
            </div>

            <div class="mt-4 p-3 bg-bg-page border border-rule rounded-xl">
                <div class="text-sm text-body">
                    <span class="font-semibold text-ink">PHP Version:</span> {{ $phpVersion }}
                    @if(version_compare($phpVersion, $phpRequired, '>='))
                    <span class="ml-2 text-green-600 font-medium">✓ OK</span>
                    @else
                    <span class="ml-2 text-red-600 font-medium">✗ Requires PHP {{ $phpRequired }} or higher</span>
                    @endif
                </div>
            </div>

            @if(!empty($recommended))
            <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3 mt-6">Recommended (Optional)</h2>
            <div class="space-y-2">
                @foreach($recommended as $label => $met)
                <div class="flex items-center gap-3">
                    @if($met)
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                    @else
                    <x-heroicon-o-information-circle class="w-5 h-5 text-amber-500 shrink-0" />
                    @endif
                    <span class="{{ $met ? 'text-body' : 'text-ink-muted' }}">{{ $label }}</span>
                </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-ink-muted">Not required — the app runs fine on file/database cache and the sync queue driver. Install this only if you plan to use Redis for caching/queues.</p>
            @endif
        </div>

        <div>
            <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Directory Permissions</h2>
            <div class="space-y-2">
                @foreach($permissions as $path => $writable)
                <div class="flex items-center gap-3">
                    @if($writable)
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                    @else
                    <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0" />
                    @endif
                    <span class="{{ $writable ? 'text-body' : 'text-red-600' }}">{{ $path }}</span>
                    <span class="text-xs text-ink-muted">({{ $writable ? 'Writable' : 'Not writable' }})</span>
                </div>
                @endforeach
            </div>

            <div class="mt-4 p-3 bg-amber/10 border border-amber/30 rounded-xl">
                <div class="flex items-start gap-2">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-amber-text shrink-0 mt-0.5" />
                    <div class="text-sm text-amber-text">
                        <span class="font-semibold">Note:</span> If any permissions are missing, adjust them via FTP or SSH before continuing.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!collect($requirements)->every(fn($met) => $met) || !collect($permissions)->every(fn($writable) => $writable))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
            <div>
                <h3 class="font-semibold text-red-800">Requirements Not Met</h3>
                <p class="text-sm text-red-700 mt-1">Please fix the issues above before proceeding with the installation.</p>
            </div>
        </div>
    </div>
    @endif

    @if(collect($requirements)->every(fn($met) => $met) && collect($permissions)->every(fn($writable) => $writable))
    <div class="pt-6 border-t border-rule">
        <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-4">How do you want to start?</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="flex flex-col p-5 bg-bg-page border border-rule rounded-xl">
                <h3 class="font-semibold text-ink mb-1">Install a new site</h3>
                <p class="text-sm text-ink-muted mb-4">Set up a fresh shop with the guided steps.</p>
                <div class="mt-auto">
                    @if(! empty($importMode))
                    <form method="POST" action="{{ route('installer.import.cancel') }}">
                        @csrf
                        <input type="hidden" name="next" value="database">
                        <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200 w-full">
                            Continue to Database Setup
                            <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
                        </button>
                    </form>
                    @else
                    <a href="{{ route('installer.database') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200 w-full">
                        Continue to Database Setup
                        <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
                    </a>
                    @endif
                </div>
            </div>
            <div class="flex flex-col p-5 bg-bg-page border border-rule rounded-xl">
                <h3 class="font-semibold text-ink mb-1">Import an existing site (.oeparts file)</h3>
                <p class="text-sm text-ink-muted mb-3">Bring your whole shop from another server using one .oeparts file.</p>
                @if(($packagesWaiting ?? 0) > 0)
                <p class="mb-4 inline-flex self-start items-center px-2.5 py-1 rounded-full bg-green-50 border border-green-200 text-xs font-medium text-green-700">We found {{ $packagesWaiting }} {{ $packagesWaiting === 1 ? 'package' : 'packages' }} in {{ $inboxPath }}/</p>
                @else
                <div class="mb-4"></div>
                @endif
                <div class="mt-auto">
                    <a href="{{ route('installer.import.mode') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-navy text-navy hover:bg-navy hover:text-white transition-all duration-200 w-full">
                        {{ ! empty($importMode) ? 'Continue the import' : 'Import an existing site' }}
                        <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
                    </a>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="flex justify-between items-center pt-6 border-t border-rule">
        <div></div>
        <div>
            <button class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm opacity-50 cursor-not-allowed" disabled>
                Fix Requirements First
            </button>
        </div>
    </div>
    @endif
</div>
@endsection
