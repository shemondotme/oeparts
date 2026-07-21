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

    $brandName            = config('app.name');
    $maintenanceTitle     = __('maintenance.title');
    $maintenanceSubtitle  = __('maintenance.subtitle');
    $estimatedLabel       = __('maintenance.estimated_return');
    $helpLabel            = __('maintenance.need_help');
    $checkBackLabel       = __('maintenance.check_back');
    $refreshLabel         = __('maintenance.refresh');
    $rightsLabel          = __('maintenance.all_rights_reserved');
    $statusLabel          = __('maintenance.status_label');
    $estimatedSpec        = __('maintenance.estimated_return_spec');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $maintenanceTitle }} — {{ $brandName }}</title>

    {{-- Same compiled build every other page ships — self-hosted fonts, real
         design tokens, zero external CDN calls. A maintenance page is exactly
         the wrong place to depend on a third-party script loading correctly. --}}
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-ivory text-ink min-h-screen">

    <div class="relative min-h-screen flex flex-col overflow-hidden">
        <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

        {{-- ── Dark document header ─────────────────────────────────── --}}
        <header class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
            <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
            <div class="relative max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-10 py-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-block w-2.5 h-2.5 bg-amber animate-pulse"></span>
                        <span class="font-mono text-[10px] font-bold tracking-[0.26em] uppercase text-amber">
                            System · Offline
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
        <main class="relative flex-1 flex items-center justify-center px-4 py-12 sm:py-16">
            <div class="w-full max-w-2xl">

                {{-- Primary card --}}
                <div class="relative border border-ink bg-paper bp-shadow-lg" style="--bp-shadow-color: rgba(245,158,11,1);">
                    {{-- Corner ticks --}}
                    <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

                    {{-- Card header strip --}}
                    <div class="flex items-center justify-end px-5 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec-mono">
                            {{ now()->format('Y-m-d H:i T') }}
                        </span>
                    </div>

                    <div class="p-6 sm:p-10">

                        {{-- Icon + giant glyph --}}
                        <div class="flex items-center gap-5 pb-8 mb-8 border-b border-rule">
                            <div class="w-16 h-16 border border-ink bg-ivory-alt flex items-center justify-center shrink-0 bp-shadow-sm">
                                <x-heroicon-o-wrench-screwdriver class="w-7 h-7 text-amber-ink animate-[spin_6s_linear_infinite]" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-display text-5xl md:text-6xl font-extrabold text-ink tabular-nums leading-none tracking-[-0.04em]">
                                    5<span class="text-amber-ink">0</span>3
                                </p>
                                <p class="mt-2 font-mono text-[11px] tracking-[0.22em] uppercase text-ink-muted">
                                    {{ $maintenanceSubtitle }}
                                </p>
                            </div>
                        </div>

                        {{-- Title / Message --}}
                        <h1 class="font-display text-3xl md:text-4xl font-extrabold text-ink leading-[1.05] tracking-[-0.02em]">
                            {{ $maintenanceTitle }}<span class="text-amber-ink">.</span>
                        </h1>

                        <p class="mt-4 text-base text-ink-muted leading-relaxed">
                            {{ trans_field($maintenanceMessage) }}
                        </p>

                        {{-- Spec grid --}}
                        <dl class="mt-8 grid grid-cols-1 border border-ink">
                            <div class="px-4 py-3 bg-paper">
                                <dt class="bp-spec text-ink-muted">{{ $statusLabel }}</dt>
                                <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 bg-amber animate-pulse"></span>
                                    OFFLINE
                                </dd>
                            </div>
                        </dl>

                        {{-- Estimated return --}}
                        @if($showEstimatedTime && !empty($estimatedBackAt))
                            <div class="mt-6 border border-ink bg-ivory-alt p-5">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 border border-ink bg-paper flex items-center justify-center shrink-0">
                                        <x-heroicon-o-clock class="w-4 h-4 text-amber-ink" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="bp-spec text-amber-ink mb-1">{{ $estimatedSpec }}</p>
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

                        {{-- Actions: manual refresh + contact --}}
                        <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-6 border-t border-rule">
                            <button type="button" onclick="window.location.reload()"
                               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-ink border border-ink text-ivory
                                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                      hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                                <x-heroicon-s-arrow-path class="w-4 h-4" />
                                {{ $refreshLabel }}
                            </button>

                            @if(!empty($contactEmail))
                                <div class="flex flex-col sm:items-end gap-1">
                                    <span class="bp-spec text-ink-muted">{{ $helpLabel }}</span>
                                    <a href="mailto:{{ $contactEmail }}"
                                       class="inline-flex items-center gap-2 font-mono text-xs font-bold text-ink
                                              border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                                        <x-heroicon-s-envelope class="w-3.5 h-3.5" />
                                        {{ $contactEmail }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Footer trust strip --}}
                    <div class="border-t border-rule bg-ivory-alt px-5 py-3 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 bp-spec-mono">
                            <x-heroicon-s-shield-check class="w-3 h-3 text-amber-ink" />
                            {{ $checkBackLabel }}
                        </span>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="mt-10 text-center">
                    <p class="bp-spec-mono">
                        &copy; {{ date('Y') }} · {{ $brandName }} · {{ $rightsLabel }}
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
