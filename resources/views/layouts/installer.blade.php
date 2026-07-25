<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Installation') — OeParts</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-body bg-bg-page antialiased">

<div class="min-h-full flex flex-col">

    {{-- Installer header — exact dark-background brand mark (identical markup to
         components/footer.blade.php's logo block: same four SVG paths, same
         tone="dark" wordmark). Static here (no href/hover), since this page has
         nowhere else to navigate to yet. --}}
    <header class="relative bg-ink py-6 px-6 border-b border-black/20 overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy-fine bg-grid-sm opacity-50 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-3xl mx-auto flex items-center gap-4">
            <x-brand-icon tone="dark" size="sm" class="group" />
            <div class="min-w-0">
                <x-brand-wordmark tone="dark" size="sm" />
                <p class="mt-1 font-mono text-[10px] tracking-[0.24em] uppercase text-amber">
                    Installer
                </p>
            </div>
        </div>
    </header>

    {{-- Step progress --}}
    @isset($currentStep)
    <div class="bg-paper border-b border-rule">
        <div class="max-w-3xl mx-auto px-6 py-5">
            <div class="flex items-start">
                @foreach([
                    1 => 'Requirements',
                    2 => 'Database',
                    3 => 'Site Settings',
                    4 => 'Admin Account',
                    5 => 'Email Setup',
                    6 => 'Complete',
                ] as $step => $label)
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex flex-col items-center shrink-0">
                        <div @class([
                            'w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold font-mono transition-colors duration-200 shrink-0',
                            'bg-amber text-ink' => $step < $currentStep,
                            'bg-ink text-ivory ring-4 ring-amber/25' => $step === $currentStep,
                            'bg-paper border-2 border-rule text-muted' => $step > $currentStep,
                        ])>
                            @if($step < $currentStep)
                                <x-heroicon-o-check class="w-4 h-4" />
                            @else
                                {{ $step }}
                            @endif
                        </div>
                        <span @class([
                            'mt-2 font-mono text-[10px] tracking-wider uppercase hidden sm:block text-center leading-tight',
                            'text-ink font-bold' => $step === $currentStep,
                            'text-ink-muted' => $step !== $currentStep,
                        ])>
                            {{ $label }}
                        </span>
                    </div>
                    @if(!$loop->last)
                    <div @class([
                        'flex-1 h-px mx-2 mb-4 sm:mb-[18px] transition-colors duration-200',
                        'bg-amber' => $step < $currentStep,
                        'bg-rule' => $step >= $currentStep,
                    ])></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endisset

    {{-- Main content --}}
    <main class="flex-1 py-10 px-6">
        <div class="max-w-3xl mx-auto">

            {{-- Flash messages --}}
            @if(session('error'))
            <div class="mb-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-red-800 text-sm">
                <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                <span>{{ session('error') }}</span>
            </div>
            @endif
            @if(session('success'))
            <div class="mb-6 flex items-start gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm">
                <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0 mt-0.5" />
                <span>{{ session('success') }}</span>
            </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="py-6 text-center font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
        OeParts — Open Source OEM Parts Platform
    </footer>
</div>

@stack('scripts')
</body>
</html>
