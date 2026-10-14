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

    @if(! empty($importError))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl" role="alert">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
            <div>
                <h3 class="font-semibold text-red-800">{{ $importError['title'] }}</h3>
                <p class="text-sm text-red-700 mt-1">{{ $importError['message'] }}</p>
                @if(! empty($importError['fix']))
                <p class="text-sm text-red-700 mt-1">{{ $importError['fix'] }}</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if($open)
        @include('installer.import.partials.package-open')
    @else
        <p class="mb-4 p-3 bg-bg-page border border-rule rounded-xl font-mono text-sm text-ink break-all">{{ $inboxPath }}/</p>

        @if(! $canTypePassphrase)
        <div class="mb-6 p-4 bg-amber/10 border border-amber/30 rounded-xl text-sm text-amber-text">
            <p class="font-semibold">{{ $insecureNotice }}</p>
            <p class="mt-2">Open this page over https, or run the import from the command line on this server:</p>
            <pre class="mt-2 p-3 bg-paper border border-rule rounded-lg font-mono text-xs text-ink overflow-x-auto">php artisan oeparts:migrate:import {{ $inboxPath }}/{{ $selected ?: 'your-file.oeparts' }} --launch=live</pre>
        </div>
        @endif

        <form method="POST" action="{{ route('installer.import.package.open') }}" autocomplete="off">
            @csrf

            @if(! $listing)
            <div class="mb-6">
                <label for="package" class="block text-sm font-semibold text-ink mb-1.5">File name</label>
                <input type="text" id="package" name="package" value="{{ $selected }}" class="form-input w-full font-mono"
                    placeholder="my-shop.oeparts" required @disabled(! $canTypePassphrase)>
                <p class="mt-1 text-xs text-muted">The list of files is switched off on this server. Type the name of the file instead.</p>
            </div>
            @elseif(count($files) === 0)
            <div class="mb-6 p-4 bg-bg-page border border-rule rounded-xl">
                <p class="text-sm text-body">No package found yet. Upload the file, then press Check again.</p>
                <p class="mt-2 font-mono text-xs text-ink-muted break-all">scp my-shop.oeparts user@your-server:{{ $inboxPath }}/</p>
            </div>
            @else
            <fieldset class="mb-6">
                <legend class="sr-only">Packages in {{ $inboxPath }}</legend>
                <div class="overflow-x-auto border border-rule rounded-xl">
                    <table class="min-w-full text-sm">
                        <thead class="bg-bg-page text-left font-mono text-[11px] uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th class="px-4 py-3 w-10"><span class="sr-only">Choose</span></th>
                                <th class="px-4 py-3">File</th>
                                <th class="px-4 py-3">Size</th>
                                <th class="px-4 py-3">Uploaded</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule">
                            @foreach($files as $file)
                            @php($usable = $file['stable'] && empty($file['problem']))
                            <tr @class(['text-ink-muted' => ! $usable])>
                                <td class="px-4 py-3">
                                    <input type="radio" name="package" value="{{ $file['name'] }}" id="pkg-{{ $loop->index }}"
                                        @checked($selected === $file['name']) @disabled(! $usable || ! $canTypePassphrase) @required($usable && $canTypePassphrase)>
                                </td>
                                <td class="px-4 py-3 font-mono break-all"><label for="pkg-{{ $loop->index }}">{{ $file['name'] }}</label></td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Number::fileSize($file['size']) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->diffForHumans() }}</td>
                                <td class="px-4 py-3">
                                    @if(! $file['stable'])
                                    <span class="text-amber-text">Still uploading. Wait until your FTP program has finished.</span>
                                    @elseif(! empty($file['problem']))
                                    <span class="text-red-600">{{ $file['problem']['title'] }}</span>
                                    @else
                                    <span class="text-green-700">Ready</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </fieldset>
            @endif

            @if($canTypePassphrase)
            <div class="mb-6">
                <label for="passphrase" class="block text-sm font-semibold text-ink mb-1.5">Passphrase</label>
                <input type="password" id="passphrase" name="passphrase" class="form-input w-full" autocomplete="off" required>
                <p class="mt-1 text-xs text-muted">The passphrase you chose when you exported the site.</p>
            </div>
            @endif

            <div class="flex justify-between items-center pt-6 border-t border-rule">
                <button type="submit" form="leave-import" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-bg-page transition-all duration-200">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    Install a new site instead
                </button>
                <div class="flex items-center gap-3">
                    <a href="{{ route('installer.import.package') }}" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-bg-page transition-all duration-200">
                        Check again
                        <x-heroicon-o-arrow-path class="w-4 h-4 ml-2" />
                    </a>
                    @if($canTypePassphrase)
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                        Open package
                        <x-heroicon-o-lock-open class="w-4 h-4 ml-2" />
                    </button>
                    @endif
                </div>
            </div>
        </form>
    @endif

    <form id="leave-import" method="POST" action="{{ route('installer.import.cancel') }}" class="hidden">
        @csrf
    </form>
</div>
@endsection
