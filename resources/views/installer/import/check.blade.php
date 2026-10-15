@extends('layouts.installer')

@section('title', 'Step 5: Check')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10">
    <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">Ready to check the package</h1>
    <p class="text-ink-muted mb-8">This is what you chose. Nothing has been loaded yet.</p>

    <dl class="mb-8 divide-y divide-rule border border-rule rounded-xl">
        <div class="px-4 py-3 sm:flex sm:gap-6">
            <dt class="sm:w-40 shrink-0 text-sm text-ink-muted">Package</dt>
            <dd class="font-mono text-sm text-ink break-all">
                {{ $open['package'] }}
                @if(! empty($open['summary']['source_domain']))
                <span class="block font-sans text-xs text-ink-muted">made on {{ $open['summary']['source_domain'] }}</span>
                @endif
            </dd>
        </div>
        <div class="px-4 py-3 sm:flex sm:gap-6">
            <dt class="sm:w-40 shrink-0 text-sm text-ink-muted">Bring over</dt>
            <dd class="text-sm text-ink">
                <span class="font-semibold">{{ $described['what'] }}</span>
                <span class="block text-xs text-ink-muted">{{ implode(', ', $described['parts']) }}</span>
            </dd>
        </div>
        <div class="px-4 py-3 sm:flex sm:gap-6">
            <dt class="sm:w-40 shrink-0 text-sm text-ink-muted">This will be</dt>
            <dd class="text-sm text-ink font-semibold">
                @if($choices['launch'] === 'live')
                    Your live site
                @else
                    A test copy
                @endif
            </dd>
        </div>
        <div class="px-4 py-3 sm:flex sm:gap-6">
            <dt class="sm:w-40 shrink-0 text-sm text-ink-muted">Address</dt>
            <dd class="font-mono text-sm text-ink break-all">{{ $choices['url'] }}</dd>
        </div>
    </dl>

    <div class="mb-8 p-4 bg-bg-page border border-rule rounded-xl text-sm text-body space-y-2">
        <p class="font-semibold text-ink">What happens next</p>
        <p>We check the file all the way through and prepare the empty database tables of this site. On the next page you see what we found.</p>
        <p>If what you chose needs other parts (for example orders need customers), we add them and ask you to confirm. Your data is loaded only after you press Start import there.</p>
    </div>

    <div class="flex justify-between items-center pt-6 border-t border-rule">
        <a href="{{ route('installer.import.choose') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-bg-page transition-all duration-200">
            <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
            Change my choices
        </a>
        <form method="POST" action="{{ route('installer.import.check.run') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Check the package
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </button>
        </form>
    </div>
</div>
@endsection
