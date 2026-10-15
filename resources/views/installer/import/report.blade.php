@extends('layouts.installer')

@section('title', 'Import report')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10">
    @if($report['complete'])
        <div class="flex items-center gap-3 mb-2">
            <x-heroicon-o-check-circle class="w-8 h-8 text-green-500 shrink-0" />
            <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink">Your site has been imported</h1>
        </div>
        <p class="text-ink-muted mb-8">
            @if(count($report['warnings']) > 0)
                {{ count($report['warnings']) }} {{ count($report['warnings']) === 1 ? 'thing needs' : 'things need' }} your attention.
            @else
                Everything went through without a warning.
            @endif
            Use the admin email and password from your OLD site.
        </p>
    @elseif($report['rolled_back'])
        <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">The import did not finish</h1>
        <p class="text-ink-muted mb-8">Your data is safe: everything was put back as it was before the import started.</p>
    @else
        <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">The import did not finish</h1>
        <p class="text-ink-muted mb-8">
            @if($report['changed_the_site'])
                The import stopped after it started loading data. Run <code class="font-mono text-xs">php artisan oeparts:migrate:import --resume</code> on the server, or <code class="font-mono text-xs">--rollback</code> to go back.
            @else
                The import stopped before anything was changed. You can start again.
            @endif
        </p>
    @endif

    <dl class="mb-8 grid sm:grid-cols-3 gap-4 text-sm">
        <div class="p-4 bg-bg-page border border-rule rounded-xl">
            <dt class="text-ink-muted">Database</dt>
            <dd class="font-semibold text-ink">{{ number_format($report['rows']) }} rows in {{ number_format($report['tables']) }} tables</dd>
        </div>
        <div class="p-4 bg-bg-page border border-rule rounded-xl">
            <dt class="text-ink-muted">Files</dt>
            <dd class="font-semibold text-ink">
                {{ number_format($report['files_written']) }} copied, {{ number_format($report['files_unchanged']) }} already there
                @if($report['files_refused'] > 0)
                    , {{ number_format($report['files_refused']) }} refused for safety
                @endif
            </dd>
        </div>
        <div class="p-4 bg-bg-page border border-rule rounded-xl">
            <dt class="text-ink-muted">This site is</dt>
            <dd class="font-semibold text-ink">{{ $report['launch'] === 'staging' ? 'a test copy' : ($report['launch'] === 'live' ? 'your live site' : 'imported') }}</dd>
        </div>
    </dl>

    @if($report['complete'])
    <section class="mb-8">
        <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Do this before going live</h2>
        <ul class="list-disc pl-5 space-y-1 text-sm text-body">
            <li>Point your domain at this server.</li>
            <li>Turn on https for the new address.</li>
            <li>Enter your payment keys again and register the payment webhooks with the new address.</li>
            <li>Send a test email from the admin panel.</li>
            <li>Turn on cron and the queue worker.</li>
            <li>Save the NEW backup key that was added to the <code class="font-mono text-xs">.env</code> file of this site.</li>
        </ul>
    </section>

    <section class="mb-8">
        <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Good to do</h2>
        <ul class="list-disc pl-5 space-y-1 text-sm text-body">
            <li>Make the sitemap again and send it to Search Console.</li>
            <li>Check that the robots file says what you want.</li>
        </ul>
    </section>
    @endif

    @if(count($report['reenter']) > 0)
    <section class="mb-8">
        <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Enter these settings again</h2>
        <p class="text-sm text-ink-muted mb-2">They were not carried over. Open the admin panel, Settings, and type them in.</p>
        <ul class="list-disc pl-5 space-y-1 text-sm font-mono text-body">
            @foreach($report['reenter'] as $name)
            <li>{{ $name }}</li>
            @endforeach
        </ul>
    </section>
    @endif

    @if(count($report['adjusted']) > 0 || count($report['switched_off']) > 0)
    <section class="mb-8">
        <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">What we adjusted for you</h2>
        <ul class="list-disc pl-5 space-y-1 text-sm text-body">
            @foreach($report['adjusted'] as $name => $note)
            <li><span class="font-mono text-xs">{{ $name }}</span>: {{ $note }}</li>
            @endforeach
            @foreach($report['switched_off'] as $name)
            <li><span class="font-mono text-xs">{{ $name }}</span>: switched off on the new site. Switch it on when you are ready.</li>
            @endforeach
        </ul>
    </section>
    @endif

    @if(count($report['warnings']) > 0)
    <section class="mb-8">
        <h2 class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Things to check</h2>
        <ul class="list-disc pl-5 space-y-1 text-sm text-body">
            @foreach($report['warnings'] as $warning)
            <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </section>
    @endif

    <p class="text-xs text-muted">This page works for two hours after the import. It shows counts and names only, never a password or a key.</p>

    @if($report['complete'])
    <div class="flex justify-end pt-6 mt-6 border-t border-rule">
        <a href="{{ url('/admin') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
            Go to the admin panel
            <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
        </a>
    </div>
    @endif
</div>
@endsection
