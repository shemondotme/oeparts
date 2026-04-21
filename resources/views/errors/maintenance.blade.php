@php
    /**
     * Multilang settings are stored as JSON strings in DB.
     * Normalise to an array so trans_field() can pick the right locale.
     */
    $normalizeMultilang = static function ($value): array|string|null {
        if (is_array($value)) return $value;
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $value;
        }
        return $value;
    };

    $maintenanceMessage = $normalizeMultilang($message ?? null)
        ?: ['en' => "We're currently performing scheduled maintenance. We'll be back shortly."];

    $brandName       = config('app.name');
    $maintenanceTitle = 'Under maintenance';
    $estimatedLabel  = 'Estimated return';
    $helpLabel       = 'Need help?';
    $checkBackLabel  = 'Please check back in a few minutes';
    $rightsLabel     = 'All rights reserved';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $maintenanceTitle }} — {{ $brandName }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800|jetbrains-mono:400,500,600,700" rel="stylesheet" />

    {{-- Tailwind CDN (maintenance-only, extended with blueprint tokens) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink:            '#0A1228',
                        'ink-muted':    '#4E5A74',
                        ivory:          '#F7F3E7',
                        'ivory-alt':    '#EFE9D6',
                        paper:          '#FFFFFF',
                        rule:           '#D8CFB6',
                        'rule-strong':  '#B8AE90',
                        'rule-dark':    '#1D2A44',
                        amber:          '#F59E0B',
                        'amber-ink':    '#9A5A00',
                    },
                    fontFamily: {
                        sans:    ['Inter', 'sans-serif'],
                        display: ['Plus Jakarta Sans', 'sans-serif'],
                        mono:    ['JetBrains Mono', 'ui-monospace', 'monospace'],
                    },
                },
            },
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }

        /* Blueprint grid backgrounds */
        .bp-grid-ivory {
            background-color: #F7F3E7;
            background-image:
                linear-gradient(to right, rgba(10,18,40,0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(10,18,40,0.05) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .bp-grid-navy {
            background-image:
                linear-gradient(to right, rgba(255,255,255,0.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 56px 56px;
        }
        .bp-spec {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            display: inline-block;
        }
        .bp-shadow-ink { box-shadow: 8px 8px 0 rgba(10,18,40,1); }
        .bp-shadow-amber { box-shadow: 8px 8px 0 rgba(245,158,11,1); }
        .bp-shadow-sm-ink { box-shadow: 4px 4px 0 rgba(10,18,40,1); }

        @keyframes bp-pulse {
            0%, 100% { opacity: 0.55; }
            50%      { opacity: 1; }
        }
        .bp-pulse { animation: bp-pulse 2.4s ease-in-out infinite; }

        @keyframes bp-tick {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .bp-tick { animation: bp-tick 6s linear infinite; transform-origin: center; }
    </style>
</head>
<body class="font-sans antialiased bp-grid-ivory text-ink min-h-screen">

    <div class="min-h-screen flex flex-col">

        {{-- ── Dark document header ─────────────────────────────────── --}}
        <header class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
            <div class="absolute inset-0 bp-grid-navy opacity-60 pointer-events-none" aria-hidden="true"></div>
            <div class="relative max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-10 py-6">
                    <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-block w-2.5 h-2.5 bg-amber bp-pulse"></span>
                        <span class="font-mono text-[10px] font-bold tracking-[0.26em] uppercase text-amber">
                            § System · Offline · 01
                        </span>
                    </div>
                    <div class="flex items-center gap-3 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                        <span class="hidden sm:inline">{{ $brandName }}</span>
                        <span class="hidden sm:inline text-ivory/30">/</span>
                        <span>DOC · MAINT/503</span>
                    </div>
                </div>
            </div>
        </header>

        {{-- ── Main content ─────────────────────────────────────────── --}}
        <main class="flex-1 flex items-center justify-center px-4 py-12 sm:py-16">
            <div class="w-full max-w-2xl">

                {{-- Primary card --}}
                <div class="relative border border-ink bg-paper bp-shadow-amber">
                    {{-- Corner ticks --}}
                    <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

                    {{-- Card header strip --}}
                    <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink">§ Incident · Report</span>
                        <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                            {{ now()->format('Y-m-d H:i T') }}
                        </span>
                    </div>

                    <div class="p-6 sm:p-10">

                        {{-- Icon + giant glyph --}}
                        <div class="flex items-center gap-5 pb-8 mb-8 border-b border-rule">
                            <div class="relative w-16 h-16 border border-ink bg-ivory-alt flex items-center justify-center shrink-0 bp-shadow-sm-ink">
                                <svg class="w-7 h-7 text-amber-ink bp-tick" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.109-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="bp-spec text-amber-ink mb-1">§ Status · Code</p>
                                <p class="font-display text-5xl md:text-6xl font-extrabold text-ink tabular-nums leading-none tracking-[-0.04em]">
                                    5<span class="text-amber">0</span>3
                                </p>
                                <p class="mt-2 font-mono text-[11px] tracking-[0.22em] uppercase text-ink-muted">
                                    Scheduled maintenance · Service unavailable
                                </p>
                            </div>
                        </div>

                        {{-- Title / Message --}}
                        <h1 class="font-display text-3xl md:text-4xl font-extrabold text-ink leading-[1.05] tracking-[-0.02em]">
                            {{ $maintenanceTitle }}<span class="text-amber">.</span>
                        </h1>

                        <p class="mt-4 text-base text-ink-muted leading-relaxed">
                            {{ trans_field($maintenanceMessage) }}
                        </p>

                        {{-- Spec grid --}}
                        <dl class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-0 border border-ink divide-y sm:divide-y-0 sm:divide-x divide-rule">
                            <div class="px-4 py-3 bg-paper">
                                <dt class="bp-spec text-ink-muted">Status</dt>
                                <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 bg-amber bp-pulse"></span>
                                    OFFLINE
                                </dd>
                            </div>
                            <div class="px-4 py-3 bg-paper">
                                <dt class="bp-spec text-ink-muted">Type</dt>
                                <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums">
                                    SCHEDULED
                                </dd>
                            </div>
                            <div class="px-4 py-3 bg-paper">
                                <dt class="bp-spec text-ink-muted">Impact</dt>
                                <dd class="mt-1 font-mono text-sm font-bold text-amber-ink tabular-nums">
                                    TEMPORARY
                                </dd>
                            </div>
                        </dl>

                        {{-- Estimated return --}}
                        @if($showEstimatedTime && !empty($estimatedBackAt))
                            <div class="mt-6 border border-ink bg-ivory-alt p-5">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 border border-ink bg-paper flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-amber-ink" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="bp-spec text-amber-ink mb-1">§ Estimated · Return</p>
                                        <p class="font-display text-xl font-extrabold text-ink tracking-[-0.02em] leading-tight">
                                            {{ \Carbon\Carbon::parse($estimatedBackAt)->format('M d, Y') }}
                                        </p>
                                        <p class="mt-1 font-mono text-sm font-bold text-ink-muted tabular-nums">
                                            {{ \Carbon\Carbon::parse($estimatedBackAt)->format('H:i T') }}
                                            · {{ $estimatedLabel }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Contact --}}
                        @if(!empty($contactEmail))
                            <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-6 border-t border-rule">
                                <p class="bp-spec text-ink-muted">§ {{ $helpLabel }}</p>
                                <a href="mailto:{{ $contactEmail }}"
                                   class="inline-flex items-center gap-2 font-mono text-xs font-bold text-ink
                                          border-b border-amber hover:text-amber-ink transition-colors pb-0.5 self-start sm:self-auto">
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                    {{ $contactEmail }}
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- Footer trust strip --}}
                    <div class="border-t border-rule bg-ivory-alt px-5 py-3 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                            <svg class="w-3 h-3 text-amber-ink" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd" />
                            </svg>
                            {{ $checkBackLabel }}
                        </span>
                        <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                            AUTO · RETRY
                        </span>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mt-10 text-center">
                    <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                        &copy; {{ date('Y') }} · {{ $brandName }} · {{ $rightsLabel }}
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
