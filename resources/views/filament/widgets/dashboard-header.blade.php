<x-filament-widgets::widget class="fi-wi-dashboard-header">
    <div class="relative overflow-hidden rounded-xl"
         style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); border-top: 3px solid var(--widget-accent, var(--color-brand-500)); min-height: 120px;">

        <div class="relative flex flex-col md:flex-row justify-between items-start md:items-center gap-4 px-6 py-5 z-10">

            {{-- Left: Greeting + role + health + quick stats --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-1.5 flex-wrap">
                    <h2 class="text-xl font-bold tracking-tight"
                        style="font-family: var(--font-display); color: var(--color-text-primary);">
                        {{ $greeting }}, {{ $adminName }}
                    </h2>

                    @if ($roleBadge ?? false)
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest rounded-full"
                              style="background: color-mix(in srgb, var(--color-brand-500) 10%, transparent); color: var(--color-brand-600); border: 1px solid color-mix(in srgb, var(--color-brand-500) 25%, transparent);">
                            {{ $roleBadge }}
                        </span>
                    @endif

                    @php
                        $healthStyles = match($systemHealth ?? 'unknown') {
                            'healthy'  => ['bg' => 'var(--color-success-500)', 'text' => 'var(--color-success-700)', 'surface' => 'var(--color-success-50)'],
                            'degraded' => ['bg' => 'var(--color-warning-500)', 'text' => 'var(--color-warning-700)', 'surface' => 'var(--color-warning-50)'],
                            default    => ['bg' => 'var(--color-danger-500)',  'text' => 'var(--color-danger-700)',  'surface' => 'var(--color-danger-50)'],
                        };
                    @endphp
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest"
                         style="background: {{ $healthStyles['surface'] }}; color: {{ $healthStyles['text'] }}; border: 1px solid color-mix(in srgb, {{ $healthStyles['bg'] }} 30%, transparent);">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ in_array($systemHealth ?? '', ['degraded','critical']) ? 'op-badge-pulse' : '' }}"
                              style="background: {{ $healthStyles['bg'] }};"></span>
                        {{ $systemHealth ?? 'Unknown' }}
                    </div>
                </div>

                <p class="text-xs mb-3" style="color: var(--color-text-muted);">
                    {{ $currentDate }} — OeParts Admin Panel
                </p>

                {{-- Quick stats row --}}
                <div class="flex items-center gap-5 flex-wrap">
                    @if ($showRevenue ?? true)
                        <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-secondary);">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-semibold tabular-nums" style="font-family: var(--font-mono);">{{ $todayRevenue }}</span>
                            <span>today</span>
                        </div>
                        <div class="w-px h-3 flex-shrink-0" style="background: var(--color-border-default);"></div>
                    @endif

                    <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-secondary);">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>
                        </svg>
                        <span class="font-semibold tabular-nums" style="font-family: var(--font-mono);" data-countup>{{ number_format($todayOrders) }}</span>
                        <span>orders</span>
                    </div>

                    @if (($pendingOrders ?? 0) > 0)
                        <div class="w-px h-3 flex-shrink-0" style="background: var(--color-border-default);"></div>
                        <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-warning-600);">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-semibold tabular-nums" style="font-family: var(--font-mono);" data-countup>{{ number_format($pendingOrders) }}</span>
                            <span>pending</span>
                        </div>
                    @endif

                    @if (($failedJobs ?? 0) > 0)
                        <div class="w-px h-3 flex-shrink-0" style="background: var(--color-border-default);"></div>
                        <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-danger-600);">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                            </svg>
                            <span class="font-semibold tabular-nums" style="font-family: var(--font-mono);">{{ number_format($failedJobs) }}</span>
                            <span>failed jobs</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right: Search + voice + CTA --}}
            <div class="flex items-center gap-2.5 flex-shrink-0">
                <button
                    data-trigger="command-palette"
                    type="button"
                    class="op-focus-ring inline-flex items-center gap-2 px-3 py-2 text-xs rounded-lg transition-all duration-200"
                    style="background: var(--color-bg-inset); border: 1px solid var(--color-border-default); color: var(--color-text-muted);"
                    aria-label="Open command palette"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    <span>Search</span>
                    <kbd class="px-1.5 py-0.5 text-[9px] font-mono font-bold rounded"
                         style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); color: var(--color-text-muted);"
                         aria-label="Keyboard shortcut: Command K">⌘K</kbd>
                </button>

                {{-- Voice search (progressive enhancement — hidden if unsupported) --}}
                <div x-data="{
                    available: false,
                    listening: false,
                    recognition: null,
                    init() {
                        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                        this.available = !!SR;
                        if (!SR) return;
                        this.recognition = new SR();
                        this.recognition.lang = document.documentElement.lang || 'en-US';
                        this.recognition.interimResults = false;
                        this.recognition.maxAlternatives = 1;
                        this.recognition.onstart = () => { this.listening = true; };
                        this.recognition.onend   = () => { this.listening = false; };
                        this.recognition.onerror = () => { this.listening = false; };
                        this.recognition.onresult = (e) => {
                            const q = e.results[0][0].transcript;
                            document.querySelector('[data-trigger=command-palette]')?.click();
                            this.$nextTick(() => {
                                const inp = document.querySelector('[data-spotlight-search-input], input[placeholder*=earch]');
                                if (inp) { inp.value = q; inp.dispatchEvent(new Event('input', { bubbles: true })); }
                            });
                        };
                    },
                    toggle() { this.listening ? this.recognition?.stop() : this.recognition?.start(); }
                }" x-init="init()">
                    <button
                        x-show="available"
                        x-cloak
                        @click="toggle()"
                        type="button"
                        :aria-label="listening ? 'Stop voice search' : 'Start voice search'"
                        :title="listening ? 'Listening — click to stop' : 'Voice search'"
                        class="op-focus-ring inline-flex items-center justify-center w-9 h-9 rounded-lg transition-all duration-200"
                        :style="listening
                            ? 'background: color-mix(in srgb, var(--color-danger-500) 10%, transparent); border: 1px solid color-mix(in srgb, var(--color-danger-500) 30%, transparent); color: var(--color-danger-600); animation: pulse 1s ease-in-out infinite;'
                            : 'background: var(--color-bg-inset); border: 1px solid var(--color-border-default); color: var(--color-text-muted);'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                        </svg>
                    </button>
                </div>

                <a href="{{ \App\Filament\Resources\ProductResource::getUrl('create') }}"
                   wire:navigate
                   class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200"
                   style="background: var(--color-brand-500); color: #ffffff;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Product
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
