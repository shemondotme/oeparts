<x-filament-widgets::widget class="fi-wi-parts-inquiry">
    <div class="p-4" style="background: var(--surface-card); border-radius: 12px;">

        @if ($isEmpty)
            <div class="flex flex-col items-center justify-center py-6 text-center gap-2">
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                    style="background: var(--surface-base); border: 1px solid var(--border-primary);">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                        style="color: var(--text-secondary);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold" style="color: var(--text-primary);">No inquiries yet</p>
                <p class="text-xs op-empty-text">Parts inquiries will appear here as customers submit requests</p>
                <a href="{{ $indexUrl }}" wire:navigate class="text-xs font-medium" style="color: var(--accent-brand);">
                    View all inquiries →
                </a>
            </div>

        @else
            @php
                $circumference = round(2 * M_PI * 40, 2);
                $offset        = round($circumference * (1 - $responseRate / 100), 2);
                $ringColor     = $responseRate >= 90
                    ? 'var(--accent-success)'
                    : ($responseRate >= 70 ? 'var(--accent-warning)' : 'var(--accent-danger)');
            @endphp

            <div class="flex items-center gap-4"
                x-data x-init="$nextTick(() => {
                    const r = $el.querySelector('.op-ring-progress');
                    if (r) r.style.strokeDashoffset = '{{ $offset }}';
                })">

                {{-- Circular progress ring --}}
                <div class="relative flex-shrink-0" aria-label="Response rate {{ $responseRate }}%" role="img">
                    <svg width="96" height="96" viewBox="0 0 100 100"
                        xmlns="http://www.w3.org/2000/svg"
                        style="overflow: visible;">
                        {{-- track --}}
                        <circle cx="50" cy="50" r="40" fill="none"
                            stroke="var(--border-primary)" stroke-width="8" />
                        {{-- progress, starts at 12 o'clock via rotate(-90,50,50) --}}
                        <circle class="op-ring-progress" cx="50" cy="50" r="40" fill="none"
                            stroke="{{ $ringColor }}"
                            stroke-width="8"
                            stroke-linecap="round"
                            transform="rotate(-90, 50, 50)"
                            style="stroke-dasharray: {{ $circumference }};
                                   stroke-dashoffset: {{ $circumference }};
                                   transition: stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1);" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center" style="pointer-events: none;">
                        <span class="text-lg font-bold leading-none" style="color: var(--text-primary); font-family: var(--font-mono);">{{ $responseRate }}%</span>
                        <span class="text-[9px] font-semibold uppercase tracking-widest mt-0.5" style="color: var(--text-secondary);">Rate</span>
                    </div>
                </div>

                {{-- Stats --}}
                <div class="flex-1 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color: var(--text-secondary);">Today</span>
                        <a href="{{ $newUrl }}" wire:navigate
                            class="text-sm font-bold leading-none" style="color: var(--accent-brand); font-family: var(--font-mono);">
                            {{ number_format($today) }}
                            @if($pending > 0)
                                <span class="text-[10px] font-normal" style="color: var(--accent-warning);">&nbsp;({{ $pending }} new)</span>
                            @endif
                        </a>
                    </div>
                    <div class="h-px" style="background: var(--border-primary);"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color: var(--text-secondary);">This Week</span>
                        <span class="text-sm font-bold leading-none" style="color: var(--text-primary); font-family: var(--font-mono);">{{ number_format($thisWeek) }}</span>
                    </div>
                    <div class="h-px" style="background: var(--border-primary);"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color: var(--text-secondary);">Avg Response</span>
                        <span class="text-sm font-bold leading-none" style="font-family: var(--font-mono);
                            color: {{ $avgHours > 0 && $avgHours <= 4 ? 'var(--accent-success)' : ($avgHours > 0 ? 'var(--accent-warning)' : 'var(--text-secondary)') }};">
                            {{ $avgHours > 0 ? $avgHours . 'h' : 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>

            @if($belowThreshold)
                <div class="mt-3 px-2.5 py-1.5 rounded-md text-xs flex items-center gap-1.5"
                    style="background: var(--color-warning-50, #fffbeb); color: var(--color-warning-700, #b45309); border: 1px solid var(--color-warning-200, #fde68a);">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Response rate is below the 90% target
                </div>
            @endif
        @endif
    </div>
</x-filament-widgets::widget>
