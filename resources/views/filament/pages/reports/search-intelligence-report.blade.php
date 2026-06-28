<x-filament-panels::page>
    {{-- Header filter controls --}}
    <div class="flex justify-end mb-6">
        <div class="flex items-center gap-2 p-1 rounded-xl" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); box-shadow: var(--shadow-1);">
            @foreach(['7' => '7d', '30' => '30d', '90' => '90d'] as $value => $label)
                <button
                    wire:click="$set('period', '{{ $value }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200"
                    style="{{ $this->period === $value
                        ? 'background: var(--color-brand-600); color: white; box-shadow: var(--shadow-1);'
                        : 'color: var(--color-text-secondary);' }}"
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Search metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Total Searches --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-brand-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-brand-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Total Searches Logged</div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-text-primary);">{{ number_format($this->getTotalSearches()) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-brand-50); color: var(--color-brand-600); border: 1px solid var(--color-brand-100);">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                </div>
            </div>
        </div>

        {{-- Unresolved --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-danger-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-danger-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono flex items-center gap-1.5" style="color: var(--color-text-muted);">
                        Unresolved Queries
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background: var(--color-danger-400);"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2" style="background: var(--color-danger-500);"></span>
                        </span>
                    </div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-danger-600);">{{ number_format($this->getUnresolvedSearches()) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-danger-50); color: var(--color-danger-600); border: 1px solid var(--color-danger-100);">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Top Search Queries table --}}
    <div class="op-card overflow-hidden mb-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">Top OEM & Part Search Queries</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="text-[10px] font-bold tracking-wider uppercase font-mono" style="color: var(--color-text-muted); border-bottom: 1px solid var(--color-border-subtle);">
                        <th scope="col" class="px-6 py-3.5">OEM / Search Query</th>
                        <th scope="col" class="px-6 py-3.5 text-center">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->getTopSearches() as $search)
                        <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center font-mono tracking-wider text-xs font-semibold px-3 py-1 rounded-md uppercase"
                                    style="background: var(--color-brand-50); color: var(--color-brand-700); border: 1px solid var(--color-brand-100);">
                                    {{ $search['search_query'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold" style="color: var(--color-text-primary);">
                                {{ number_format($search['count']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-8 text-center text-sm font-medium" style="color: var(--color-text-muted);">
                                No search queries recorded for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Unresolved Queries table --}}
    <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">Unresolved Failures (Zero Matches & No Inquiry)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="text-[10px] font-bold tracking-wider uppercase font-mono" style="color: var(--color-text-muted); border-bottom: 1px solid var(--color-border-subtle);">
                        <th scope="col" class="px-6 py-3.5">Failed Query</th>
                        <th scope="col" class="px-6 py-3.5 text-center">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->getFailedQueries() as $failed)
                        <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center font-mono tracking-wider text-xs font-semibold px-3 py-1 rounded-md uppercase"
                                    style="background: var(--color-danger-50); color: var(--color-danger-600); border: 1px solid var(--color-danger-100);">
                                    {{ $failed['search_query'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold" style="color: var(--color-text-primary);">
                                {{ number_format($failed['count']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-8 text-center text-sm font-medium" style="color: var(--color-text-muted);">
                                No unresolved failed queries for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
