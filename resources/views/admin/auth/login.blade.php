<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin Login — {{ settings('general.site_name', 'OEMHub') }}</title>

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    <style>
        /* Industrial Blueprint Login Specifics */
        body {
            background-color: #F7F3E7; /* Ivory */
            color: #0A1228; /* Ink */
            font-family: 'Inter', sans-serif;
        }
        .login-container {
            background-color: #FFFFFF; /* Paper */
            border: 1px solid #D8CFB6; /* Rule */
            box-shadow: 4px 4px 0 rgba(20,22,29,1); /* Blueprint Stamp */
        }
        .bp-input {
            width: 100%;
            background-color: transparent;
            border: 1px solid #D8CFB6;
            padding: 0.75rem 1rem;
            color: #0A1228;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.875rem;
            transition: all 0.15s ease-in-out;
        }
        .bp-input:focus {
            outline: none;
            border-color: #0A1228;
            background-color: #FFFFFF;
        }
        .bp-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.75rem 1.5rem;
            background-color: #0A1228; /* Ink */
            color: #F7F3E7; /* Ivory */
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.875rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border: 1px solid #0A1228;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
        }
        .bp-btn-primary:hover {
            background-color: #F59E0B; /* Amber */
            color: #0A1228; /* Ink */
            border-color: #F59E0B;
        }
        .bp-spec {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #4E5A74; /* Muted */
        }
        .bg-grid-pattern {
            background-color: #F7F3E7;
            background-image:
                linear-gradient(to right, rgba(10,18,40,0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(10,18,40,0.05) 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="h-full bg-grid-pattern flex items-center justify-center p-4">

    {{-- ══════════════════════════════════════════════════════════════════════
         ADMIN LOGIN — INDUSTRIAL BLUEPRINT
         Technical access portal. Sharp, secure, authoritative.
         ═══════════════════════════════════════════════════════════════════ --}}

    <div class="w-full max-w-md login-container relative">

        {{-- Corner Register Marks --}}
        <div class="absolute top-0 left-0 w-3 h-3 border-l-2 border-t-2 border-ink/40 pointer-events-none"></div>
        <div class="absolute top-0 right-0 w-3 h-3 border-r-2 border-t-2 border-ink/40 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-3 h-3 border-l-2 border-b-2 border-ink/40 pointer-events-none"></div>
        <div class="absolute bottom-0 right-0 w-3 h-3 border-r-2 border-b-2 border-ink/40 pointer-events-none"></div>

        {{-- Header --}}
        <div class="px-8 pt-8 pb-6 border-b border-rule">
            <div class="flex items-center justify-center gap-3 mb-4">
                <svg viewBox="0 0 60 60" class="w-10 h-10" aria-hidden="true">
                    <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z" class="fill-ink"/>
                    <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z" class="fill-ivory"/>
                    <path d="M30 18 L30 42 M18 30 L42 30" class="stroke-ink" stroke-width="2.5" stroke-linecap="square"/>
                    <circle cx="30" cy="30" r="3.2" class="fill-amber"/>
                </svg>
                <div class="text-center">
                    <h1 class="font-display font-extrabold text-2xl tracking-[-0.02em] text-ink leading-none">
                        OEM<span class="text-amber">·</span>HUB
                    </h1>
                    <p class="mt-1 font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                        Admin Console
                    </p>
                </div>
            </div>
            <p class="text-center text-sm text-ink-muted mt-4">
                Enter your credentials to access the technical dashboard.
            </p>
        </div>

        {{-- Content --}}
        <div class="px-8 py-8">

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="mb-6 border border-red-600 bg-red-50 p-4">
                    <div class="flex items-start gap-3">
                        <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                        <div>
                            <p class="font-bold text-xs uppercase tracking-wide text-red-800 mb-1">Authentication Failed</p>
                            <ul class="list-disc pl-5 space-y-1 text-sm text-red-700">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Status Message --}}
            @if (session('status'))
                <div class="mb-6 border border-emerald-600 bg-emerald-50 p-4">
                    <div class="flex items-start gap-3">
                        <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                        <p class="text-sm text-emerald-800">{{ session('status') }}</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('admin.login.post') }}" method="POST" class="space-y-6">
                @csrf

                {{-- Email Field --}}
                <div>
                    <label for="email" class="block bp-spec mb-2">§ Email Address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}"
                           class="bp-input"
                           placeholder="admin@oemhub.eu">
                </div>

                {{-- Password Field --}}
                <div>
                    <label for="password" class="block bp-spec mb-2">§ Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="bp-input"
                           placeholder="••••••••">
                </div>

                {{-- Remember Me & Forgot Password --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox"
                               class="h-4 w-4 rounded-none border-rule text-amber focus:ring-amber focus:ring-offset-0">
                        <label for="remember" class="ml-2 block text-sm text-ink-muted">
                            Remember this device
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="#" class="font-medium text-amber-ink hover:text-amber underline decoration-1 underline-offset-2">
                            Forgot password?
                        </a>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div>
                    <button type="submit" class="bp-btn-primary">
                        Authenticate →
                    </button>
                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="px-8 py-4 bg-ivory-alt border-t border-rule text-center">
            <p class="text-xs text-ink-muted font-mono">
                SECURE CONNECTION · TLS 1.3 · {{ date('Y') }} OEMHUB
            </p>
        </div>
    </div>

</body>
</html>
