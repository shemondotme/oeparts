<x-filament-widgets::widget class="fi-wi-dashboard-header op-fade-in">
    <div class="relative overflow-hidden p-6" style="background: var(--glass-bg); backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); border-top: 3px solid var(--aurora-indigo); border-radius: 20px; box-shadow: var(--glass-shadow); transition: all 300ms ease;">
        <div class="absolute inset-0 pointer-events-none" style="background: var(--aurora-gradient-soft);"></div>
        <div class="absolute top-0 right-0 w-96 h-96 pointer-events-none opacity-10" style="background: radial-gradient(circle, var(--aurora-indigo) 0%, transparent 70%); transform: translate(50%, -50%);"></div>

        <div class="relative flex flex-col md:flex-row justify-between items-start md:items-center gap-4 z-10">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-2xl font-bold tracking-tight" style="background: var(--aurora-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-family: var(--font-display); letter-spacing: var(--tracking-tight);">
                        {{ $greeting }}, <span style="color: var(--aurora-violet);">{{ $adminName }}</span>
                    </h1>
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest"
                        @if($systemHealth === 'healthy')
                            style="background: var(--color-success-50); color: var(--color-success-700); border: 1px solid color-mix(in srgb, var(--color-success-200), transparent 40%);"
                        @elseif($systemHealth === 'degraded')
                            style="background: var(--color-warning-50); color: var(--color-warning-700); border: 1px solid color-mix(in srgb, var(--color-warning-200), transparent 40%);"
                        @else
                            style="background: var(--color-danger-50); color: var(--color-danger-700); border: 1px solid color-mix(in srgb, var(--color-danger-200), transparent 40%);"
                        @endif
                    >
                        <span class="w-1.5 h-1.5 rounded-full @if($systemHealth === 'degraded' || $systemHealth === 'warning') op-badge-pulse @endif"
                            @if($systemHealth === 'healthy')
                                style="background: var(--color-success-500);"
                            @elseif($systemHealth === 'degraded')
                                style="background: var(--color-warning-500);"
                            @else
                                style="background: var(--color-danger-500);"
                            @endif
                        ></span>
                        {{ $systemHealth }}
                    </div>
                </div>
                <p class="text-sm" style="color: var(--color-text-muted);">
                    {{ $currentDate }} — OeParts Admin Panel
                </p>

                <div class="flex items-center gap-6 mt-3">
                    @if($showRevenue ?? true)
                    <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-secondary);">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="font-semibold" style="font-family: var(--font-mono);">{{ $todayRevenue }}</span>
                        <span>today</span>
                    </div>
                    @endif
                    <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-secondary);">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                        <span class="font-semibold" style="font-family: var(--font-mono);">{{ number_format($todayOrders) }}</span>
                        <span>orders</span>
                    </div>
                    @if($pendingOrders > 0)
                    <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-warning-600);">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="font-semibold" style="font-family: var(--font-mono);">{{ number_format($pendingOrders) }}</span>
                        <span>pending</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3 flex-shrink-0">
                <button
                    data-trigger="command-palette"
                    class="op-focus-ring flex items-center gap-2 px-3 py-2 text-xs rounded-lg transition-all duration-200"
                    style="background: var(--color-bg-inset); border: 1px solid var(--color-border-default); color: var(--color-text-muted);"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <span>Search</span>
                    <kbd class="px-1.5 py-0.5 text-[9px] font-mono font-bold rounded" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); color: var(--color-text-muted);">&#8984;K</kbd>
                </button>

                <a href="{{ \App\Filament\Resources\ProductResource::getUrl('create') }}" wire:navigate class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 font-medium text-sm rounded-lg transition-all hover:shadow-lg"
                    style="background: var(--aurora-gradient); color: white; box-shadow: var(--glass-shadow); border: 1px solid rgba(255,255,255,0.2);"
                    onmouseover="this.style.boxShadow='var(--glass-shadow), var(--glass-glow)'"
                    onmouseout="this.style.boxShadow='var(--glass-shadow)'"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    New Product
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
