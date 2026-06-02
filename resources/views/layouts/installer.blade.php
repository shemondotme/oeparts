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

    {{-- Installer header --}}
    <header class="bg-navy py-4 px-6 shadow-sm">
        <div class="max-w-3xl mx-auto flex items-center gap-3">
            <span class="font-display font-bold text-xl text-white">OeParts</span>
            <span class="text-xs text-amber font-medium uppercase tracking-widest">Installer</span>
        </div>
    </header>

    {{-- Step progress --}}
    @isset($currentStep)
    <div class="bg-white border-b border-slate-200">
        <div class="max-w-3xl mx-auto px-6 py-4">
            <div class="flex items-center">
                @foreach([
                    1 => 'Requirements',
                    2 => 'Database',
                    3 => 'Site Settings',
                    4 => 'Admin Account',
                    5 => 'Email Setup',
                    6 => 'Complete',
                ] as $step => $label)
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
                            {{ $step < $currentStep ? 'bg-green-500 text-white' : ($step === $currentStep ? 'bg-navy text-white' : 'bg-slate-200 text-muted') }}">
                            @if($step < $currentStep)
                                <x-heroicon-o-check class="w-4 h-4" />
                            @else
                                {{ $step }}
                            @endif
                        </div>
                        <span class="mt-1 text-xs font-medium {{ $step === $currentStep ? 'text-navy' : 'text-muted' }} hidden sm:block">
                            {{ $label }}
                        </span>
                    </div>
                    @if(!$loop->last)
                    <div class="flex-1 h-0.5 mx-2 {{ $step < $currentStep ? 'bg-green-500' : 'bg-slate-200' }}"></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endisset

    {{-- Main content --}}
    <main class="flex-1 py-8 px-6">
        <div class="max-w-3xl mx-auto">

            {{-- Flash messages --}}
            @if(session('error'))
            <div class="mb-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                <span>{{ session('error') }}</span>
            </div>
            @endif
            @if(session('success'))
            <div class="mb-6 flex items-start gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
                <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0 mt-0.5" />
                <span>{{ session('success') }}</span>
            </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="py-4 text-center text-xs text-muted">
        OeParts — Open Source OEM Parts Platform
    </footer>
</div>

@stack('scripts')
</body>
</html>
