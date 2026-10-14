@extends('layouts.installer')

@section('title', 'Step 3: Package')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10">
    <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">Your package</h1>
    <p class="text-ink-muted mb-8">Upload your .oeparts file to the folder below using FTP or SFTP, then pick it here.</p>

    @if($ipChanged)
    <div class="mb-6 p-4 bg-amber/10 border border-amber/30 rounded-xl text-sm text-amber-text">
        This page was started from a different network address than the one you are using now. If that was not you, stop here.
    </div>
    @endif

    <p class="mb-4 p-3 bg-bg-page border border-rule rounded-xl font-mono text-sm text-ink break-all">{{ $inboxPath }}/</p>

    @if(! $listing)
    <p class="mb-6 text-sm text-ink-muted">The list of files is switched off on this server. Type the name of the file instead.</p>
    @elseif(count($files) === 0)
    <div class="mb-6 p-4 bg-bg-page border border-rule rounded-xl">
        <p class="text-sm text-body">No package found yet. Upload the file, then press Check again.</p>
        <p class="mt-2 font-mono text-xs text-ink-muted break-all">scp my-shop.oeparts user@your-server:{{ $inboxPath }}/</p>
    </div>
    @else
    <div class="mb-6 overflow-x-auto border border-rule rounded-xl">
        <table class="min-w-full text-sm">
            <thead class="bg-bg-page text-left font-mono text-[11px] uppercase tracking-wider text-ink-muted">
                <tr>
                    <th class="px-4 py-3">File</th>
                    <th class="px-4 py-3">Size</th>
                    <th class="px-4 py-3">Uploaded</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-rule">
                @foreach($files as $file)
                <tr @class(['text-ink-muted' => ! $file['stable']])>
                    <td class="px-4 py-3 font-mono break-all">{{ $file['name'] }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Number::fileSize($file['size']) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->diffForHumans() }}</td>
                    <td class="px-4 py-3">
                        @if(! $file['stable'])
                        <span class="text-amber-text">Still uploading. Wait until your FTP program has finished.</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="flex justify-between items-center pt-6 border-t border-rule">
        <form method="POST" action="{{ route('installer.import.cancel') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-bg-page transition-all duration-200">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Install a new site instead
            </button>
        </form>
        <a href="{{ route('installer.import.package') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
            Check again
            <x-heroicon-o-arrow-path class="w-4 h-4 ml-2" />
        </a>
    </div>
</div>
@endsection
