@include('filament.components.admin-styles')
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="absolute inset-0" style="background: radial-gradient(circle at 30% 30%, rgba(245, 158, 11, 0.08), transparent 50%);"></div>
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-server-stack class="w-5 h-5" style="color: var(--color-warning-500);" />
                        Cache Dashboard
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Monitor Redis cache performance, memory usage, and key statistics. Auto-refreshes every 30 seconds.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                        style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--color-success-400);">
                        <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--color-success-500);"></span>
                        POLLING 30s
                    </div>
                    <button wire:click="clearApplicationCache"
                        x-data
                        x-on:click="if (!confirm('Clear application cache keys? This will not affect sessions.')) $event.preventDefault()"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all duration-200"
                        style="background: var(--color-warning-600); color: white;">
                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                        Clear App Cache
                    </button>
                    <span wire:loading wire:target="clearApplicationCache" class="ml-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        @php $stats = $this->getCacheStats(); @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-bolt class="w-4 h-4" style="color: var(--color-success-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Hit Rate</span>
                        </div>
                        <div class="text-3xl font-bold font-mono" style="color: var(--color-text-primary); font-family: var(--font-display);">
                            {{ $stats['hit_rate'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-cpu-chip class="w-4 h-4" style="color: var(--color-info-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Memory Used</span>
                        </div>
                        <div class="text-2xl font-bold font-mono" style="color: var(--color-text-primary); font-family: var(--font-display);">
                            {{ $stats['memory_used'] }}
                        </div>
                        <div class="text-xs font-mono mt-1" style="color: var(--color-text-muted);">Peak: {{ $stats['memory_peak'] }}</div>
                    </div>
                </div>
            </div>

            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-key class="w-4 h-4" style="color: var(--color-warning-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Total Keys</span>
                        </div>
                        <div class="text-3xl font-bold font-mono" style="color: var(--color-text-primary); font-family: var(--font-display);">
                            {{ number_format($stats['total_keys']) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="op-card op-hover-lift op-press relative overflow-hidden p-5 transition-all duration-300"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="relative flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                                <x-heroicon-o-clock class="w-4 h-4" style="color: var(--color-primary-500);" />
                            </div>
                            <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Uptime</span>
                        </div>
                        <div class="text-2xl font-bold font-mono" style="color: var(--color-text-primary); font-family: var(--font-display);">
                            {{ $stats['uptime'] }}
                        </div>
                        <div class="text-xs font-mono mt-1" style="color: var(--color-text-muted);">Clients: {{ $stats['connected_clients'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cache Keys Table --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Cached Keys (Top 50)
                </h3>
            </div>

            <div class="p-6">
                @php $keys = $this->getTopCachedKeys(); @endphp

                @if(empty($keys))
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="w-12 h-12 mx-auto mb-4" style="color: var(--color-success-500);" />
                        <p class="text-sm font-medium" style="color: var(--color-text-muted);">No cached keys found.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-subtle);">
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Key</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">TTL</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($keys as $item)
                                    <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                                        <td class="px-4 py-3 font-mono text-xs" style="color: var(--color-text-primary);" title="{{ $item['key'] }}">
                                            {{ Str::limit($item['key'], 60) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium font-mono"
                                                style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-secondary);">
                                                {{ $item['ttl'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button wire:click="clearKey(@js($item['key']))"
                                                x-data
                                                x-on:click="if (!confirm('Delete this cache key?')) $event.preventDefault()"
                                                class="op-focus-ring op-press inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-200"
                                                style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-muted);">
                                                <x-heroicon-o-x-mark class="w-3 h-3" />
                                                Clear
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
