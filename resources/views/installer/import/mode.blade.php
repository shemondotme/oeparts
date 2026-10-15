@extends('layouts.installer')

@section('title', 'Import an existing site')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10">
    <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">Import an existing site</h1>
    <p class="text-ink-muted mb-8">Bring your whole shop from another server using one .oeparts file.</p>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <div>
            <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">What you need</h2>
            <ul class="space-y-2 text-body text-sm">
                <li class="flex items-start gap-3">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0 mt-0.5" />
                    <span>A .oeparts file exported from your other site.</span>
                </li>
                <li class="flex items-start gap-3">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0 mt-0.5" />
                    <span>The passphrase you chose when you exported it.</span>
                </li>
                <li class="flex items-start gap-3">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0 mt-0.5" />
                    <span>An empty database on this server.</span>
                </li>
            </ul>
        </div>
        <div>
            <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Where the file goes</h2>
            @if($inboxPath)
            <p class="text-sm text-body">Upload your .oeparts file with FTP or SFTP to this folder on the server:</p>
            <p class="mt-2 p-3 bg-bg-page border border-rule rounded-xl font-mono text-sm text-ink break-all">{{ $inboxPath }}/</p>
            @else
            <p class="text-sm text-body">Upload your .oeparts file with FTP or SFTP to the package folder of this server. You will type its name on the next page.</p>
            @endif
            @if($inboxPath && $packagesWaiting > 0)
            <p class="mt-3 text-sm text-green-700">
                We found {{ $packagesWaiting }} {{ $packagesWaiting === 1 ? 'package' : 'packages' }} in {{ $inboxPath }}/
            </p>
            @endif
        </div>
    </div>

    <div class="mb-8 p-4 bg-amber/10 border border-amber/30 rounded-xl">
        <div class="flex items-start gap-2">
            <x-heroicon-o-information-circle class="w-5 h-5 text-amber-text shrink-0 mt-0.5" />
            <p class="text-sm text-amber-text">
                This page belongs to the browser you start it in. Nobody else who opens the installer can see or change your choices.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('installer.import.start') }}">
        @csrf
        <div class="flex justify-between items-center pt-6 border-t border-rule">
            <a href="{{ route('installer.index') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-bg-page transition-all duration-200">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back
            </a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Start the import
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </button>
        </div>
    </form>
</div>
@endsection
