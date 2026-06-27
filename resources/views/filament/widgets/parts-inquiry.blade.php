<x-filament-widgets::widget class="fi-wi-parts-inquiry">
    @php
        $r             = 20;
        $sw            = 4;
        $circumference = round(2 * M_PI * $r, 2);
        $offset        = round($circumference * (1 - ($responseRate ?? 0) / 100), 2);
        $ringColor     = ($responseRate ?? 100) >= 90
            ? 'var(--color-success-500)'
            : (($responseRate ?? 100) >= 70 ? 'var(--color-warning-500)' : 'var(--color-danger-500)');
    @endphp

    <x-admin.kpi-card label="Parts Inquiries">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
            </svg>
        </x-slot:icon>

        @if ($isEmpty ?? false)
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center flex-1 py-2 text-center gap-1.5">
                <p class="text-sm font-semibold" style="color: var(--color-text-secondary);">No inquiries yet</p>
                <p class="text-xs" style="color: var(--color-text-muted);">Customer requests will appear here</p>
                <a href="{{ $indexUrl ?? '#' }}" wire:navigate
                   class="text-xs font-semibold mt-1"
                   style="color: var(--widget-accent, var(--color-brand-500));">
                    View all &rarr;
                </a>
            </div>
        @else
            {{-- Primary KPI value (today) + ring side by side --}}
            <div class="mt-3 flex items-center gap-4">
                {{-- Left: today count + week/response meta --}}
                <div class="flex-1 min-w-0">
                    <a href="{{ $newUrl ?? '#' }}" wire:navigate class="block group">
                        <span class="op-kpi-value tabular-nums font-mono block" data-countup>
                            {{ number_format($today ?? 0) }}
                        </span>
                        <span class="text-[11px]" style="color: var(--color-text-muted);">
                            today
                            @if (($pending ?? 0) > 0)
                                &nbsp;·&nbsp;<span style="color: var(--color-warning-600);">{{ $pending }} new</span>
                            @endif
                        </span>
                    </a>
                    <div class="mt-2 space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span style="color: var(--color-text-muted);">This week</span>
                            <span class="font-semibold tabular-nums" style="font-family: var(--font-mono); color: var(--color-text-primary);">
                                {{ number_format($thisWeek ?? 0) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span style="color: var(--color-text-muted);">Avg response</span>
                            <span class="font-semibold"
                                  style="font-family: var(--font-mono); color: {{ $avgHoursColor ?? 'var(--color-text-muted)' }};">
                                {{ $avgHoursLabel ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Right: compact 48px progress ring --}}
                <div class="flex-shrink-0 relative"
                     style="width: 48px; height: 48px;"
                     role="img"
                     aria-label="Response rate {{ $responseRate ?? 0 }}%">
                    <svg width="48" height="48" viewBox="0 0 48 48"
                         x-data
                         x-init="$nextTick(() => {
                             const el = $el.querySelector('.op-ring-progress');
                             if (el) el.style.strokeDashoffset = '{{ $offset }}';
                         })"
                         xmlns="http://www.w3.org/2000/svg"
                         style="overflow: visible;">
                        <circle cx="24" cy="24" r="{{ $r }}" fill="none"
                                stroke="var(--color-border-default)" stroke-width="{{ $sw }}"/>
                        <circle class="op-ring-progress"
                                cx="24" cy="24" r="{{ $r }}" fill="none"
                                stroke="{{ $ringColor }}"
                                stroke-width="{{ $sw }}"
                                stroke-linecap="round"
                                transform="rotate(-90, 24, 24)"
                                style="stroke-dasharray: {{ $circumference }}; stroke-dashoffset: {{ $circumference }}; transition: stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1);"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-[10px] font-bold leading-none"
                              style="font-family: var(--font-mono); color: var(--color-text-primary);">
                            {{ $responseRate ?? 0 }}%
                        </span>
                    </div>
                </div>
            </div>

            {{-- Below-threshold alert --}}
            @if ($belowThreshold ?? false)
                <div class="mt-3 px-2.5 py-1.5 rounded-md text-xs flex items-center gap-1.5"
                     style="background: color-mix(in srgb, var(--color-warning-500) 8%, transparent); color: var(--color-warning-700); border: 1px solid color-mix(in srgb, var(--color-warning-500) 25%, transparent);"
                     role="alert">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    Response rate below 90% target
                </div>
            @endif
        @endif
    </x-admin.kpi-card>
</x-filament-widgets::widget>
