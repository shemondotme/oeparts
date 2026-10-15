@extends('layouts.installer')

@section('title', 'Step 4: Choose')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10">
    <h1 class="font-display text-2xl sm:text-3xl font-extrabold text-ink mb-2">What should we bring over?</h1>
    <p class="text-ink-muted mb-8">Always included: settings, languages, admin accounts.</p>

    @if($saved)
    <div class="mb-6 flex items-start gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm" role="status">
        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0 mt-0.5" />
        <span>Your choices are saved.</span>
    </div>
    @endif

    <form method="POST" action="{{ route('installer.import.choose.save') }}" autocomplete="off">
        @csrf

        {{-- 1. What to bring --}}
        <fieldset class="mb-8">
            <legend class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Parts of the site</legend>
            @error('selection')
            <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="space-y-3">
                @foreach($presets as $preset)
                <label class="flex items-start gap-3 p-4 border border-rule rounded-xl cursor-pointer has-[:checked]:border-navy has-[:checked]:bg-bg-page">
                    <input type="radio" name="selection" value="preset:{{ $preset['key'] }}" class="mt-1" @checked($selection === 'preset:'.$preset['key'])>
                    <span>
                        <span class="block font-semibold text-ink">{{ $preset['label'] }}</span>
                        <span class="block text-sm text-ink-muted">{{ $preset['description'] }}</span>
                    </span>
                </label>
                @endforeach

                <label class="flex items-start gap-3 p-4 border border-rule rounded-xl cursor-pointer has-[:checked]:border-navy has-[:checked]:bg-bg-page">
                    <input type="radio" name="selection" value="custom" class="mt-1" @checked($selection === 'custom')>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-ink">Let me choose</span>
                        <span class="block text-sm text-ink-muted mb-3">Tick the parts you want. We check what your choice needs once the database is prepared, and ask you to confirm anything we add.</span>
                        @error('groups')
                        <span class="block mb-2 text-sm text-red-600">{{ $message }}</span>
                        @enderror
                        <span class="grid sm:grid-cols-2 gap-x-6 gap-y-2">
                            @foreach($groups as $group)
                            <span class="flex items-start gap-2">
                                <input type="checkbox" name="groups[]" value="{{ $group['key'] }}" id="group-{{ $group['key'] }}" class="mt-1"
                                    @checked($group['mandatory'] || in_array($group['key'], $ticked, true)) @disabled($group['mandatory'])>
                                <label for="group-{{ $group['key'] }}" class="text-sm">
                                    <span class="block font-medium text-ink">{{ $group['label'] }}</span>
                                    <span class="block text-xs text-ink-muted">{{ $group['description'] }}</span>
                                </label>
                            </span>
                            @endforeach
                        </span>
                    </span>
                </label>
            </div>
        </fieldset>

        {{-- 2. Live or test: no default --}}
        <fieldset class="mb-8">
            <legend class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-3">Is this your real, live site?</legend>
            @error('launch')
            <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <div class="space-y-3">
                <label class="flex items-start gap-3 p-4 border border-rule rounded-xl cursor-pointer has-[:checked]:border-navy has-[:checked]:bg-bg-page">
                    <input type="radio" name="launch" value="live" class="mt-1" @checked($launch === 'live')>
                    <span class="font-semibold text-ink">Yes, this will be my live site</span>
                </label>
                <label class="flex items-start gap-3 p-4 border border-rule rounded-xl cursor-pointer has-[:checked]:border-navy has-[:checked]:bg-bg-page">
                    <input type="radio" name="launch" value="staging" class="mt-1" @checked($launch === 'staging')>
                    <span>
                        <span class="block font-semibold text-ink">No, this is a test copy</span>
                        <span class="block text-sm text-ink-muted">We switch off real emails, scheduled jobs, payments, search engine visibility and tracking so nothing reaches your customers. Order and invoice numbers start in a separate range.</span>
                    </span>
                </label>
            </div>
        </fieldset>

        {{-- 3. Address --}}
        <div class="mb-8">
            <label for="site_url" class="block font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink mb-2">Address of this site</label>
            <input type="text" id="site_url" name="site_url" value="{{ $siteUrl }}" class="form-input w-full font-mono" inputmode="url" required>
            @error('site_url')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">This is where visitors will find the site. An https:// address is accepted only when you opened this page over https.</p>
        </div>

        {{-- 4. Secrets, read only --}}
        <div class="mb-6 p-4 bg-bg-page border border-rule rounded-xl">
            <h2 class="font-semibold text-ink mb-1">Passwords and keys</h2>
            <p class="text-sm text-body">
                @if(! empty($open['summary']['secrets_included']))
                    This package carries the saved passwords and API keys of the old site. They are re-locked for this server.
                @else
                    Saved passwords and API keys (mail, payment, shipping) are not in the package. You will enter them again after the import. We list them in the report.
                @endif
            </p>
        </div>

        {{-- 5. What we adjust --}}
        <div class="mb-8 p-4 bg-amber/10 border border-amber/30 rounded-xl">
            <h2 class="font-semibold text-amber-text mb-2">What we will adjust for you</h2>
            <ul class="list-disc pl-5 space-y-1 text-sm text-amber-text">
                <li>The site address is set to the one above so visitors are not sent to your old site.</li>
                <li>Maintenance mode is switched off so your shop is visible.</li>
                <li>Two-step customer login is switched off until your email works.</li>
                @if($anonymised)
                <li>Customer details in this package were scrambled when it was exported. They stay scrambled.</li>
                @endif
            </ul>
        </div>

        <div class="flex justify-between items-center pt-6 border-t border-rule">
            <a href="{{ route('installer.import.package') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-bg-page transition-all duration-200">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to the package
            </a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Save my choices
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </button>
        </div>
    </form>
</div>
@endsection
