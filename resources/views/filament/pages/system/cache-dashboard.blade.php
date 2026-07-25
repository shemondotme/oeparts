<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Server Health renders above via getHeaderWidgets() (CacheHealthRows). --}}

        {{-- Category Breakdown --}}
        @php $categories = $this->getCategoryBreakdown(); @endphp

        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Category Breakdown
                </h3>
            </div>

            <div class="op-cat-row op-cat-row-head">
                <span>Category</span>
                <span style="text-align: right;">Keys</span>
                <span style="text-align: right;">TTL</span>
                <span style="text-align: right;">Last cleared</span>
                <span style="text-align: right;">Last warmed</span>
                <span style="text-align: right;">Actions</span>
            </div>

            @foreach ($categories as $cat)
                <div class="op-cat-row" wire:key="cat-row-{{ $cat['key'] }}">
                    <span>
                        <span style="display: block; font-weight: 600; font-size: 0.875rem;">{{ $cat['label'] }}</span>
                        <span class="op-widget-title" style="text-transform: none; font-weight: 400; opacity: 1; letter-spacing: normal;">{{ $cat['sub'] }}</span>
                    </span>

                    <span style="text-align: right;">
                        <span class="op-status-pill op-status-pill-muted">{{ number_format($cat['keyCount']) }}</span>
                    </span>

                    <span style="color: var(--text-secondary); font-size: 0.8rem; text-align: right;">{{ $cat['ttlMinutes'] }}m</span>

                    <span class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal; text-align: right;">{{ $cat['lastCleared'] ?? 'never' }}</span>

                    <span class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal; text-align: right;">{{ $cat['lastWarmed'] ?? 'never' }}</span>

                    <span style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                        @if ($cat['canWarm'])
                            <button wire:click="warmCategory('{{ $cat['key'] }}')"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                wire:target="warmCategory('{{ $cat['key'] }}')"
                                class="op-focus-ring op-press inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-200"
                                style="background: var(--info-600); color: white;">
                                <x-heroicon-o-fire class="w-3 h-3" />
                                Warm
                            </button>
                        @endif
                        <button wire:click="clearCategory('{{ $cat['key'] }}')"
                            x-data
                            x-on:click="if (!confirm('Clear all {{ $cat['label'] }} cache keys?')) $event.preventDefault()"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                            wire:target="clearCategory('{{ $cat['key'] }}')"
                            class="op-focus-ring op-press inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-200"
                            style="background: var(--danger-600); color: white;">
                            <x-heroicon-o-trash class="w-3 h-3" />
                            Clear
                        </button>
                    </span>
                </div>
            @endforeach
        </div>

        {{-- Key Browser --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Key Browser
                </h3>
            </div>

            <div class="p-4 flex flex-col md:flex-row gap-3" style="border-bottom: 1px solid var(--color-border-subtle);">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model="keyBrowserPattern"
                        wire:keydown.enter="searchKeys"
                        placeholder="Pattern, e.g. manufacturers.* or *"
                        class="w-full px-4 py-2.5 text-sm rounded-xl transition-all duration-200 focus:ring-2 focus:ring-offset-0 font-mono"
                        style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); --tw-ring-color: var(--primary-500);"
                    />
                </div>
                <button wire:click="searchKeys"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    wire:target="searchKeys"
                    class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all duration-200"
                    style="background: var(--primary-600); color: white;">
                    <x-heroicon-o-magnifying-glass class="w-3.5 h-3.5" />
                    Search
                </button>
            </div>

            @if (empty($scanResults))
                <div class="text-center py-12">
                    <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                    <p class="text-sm font-medium" style="color: var(--color-text-muted);">No keys match this pattern.</p>
                </div>
            @else
                <div class="op-kb-row op-kb-row-head">
                    <span>Key</span>
                    <span style="text-align: right;">TTL</span>
                    <span style="text-align: right;">Size</span>
                    <span style="text-align: right;">Actions</span>
                </div>

                @foreach ($scanResults as $result)
                    <div class="op-kb-row" wire:key="kb-row-{{ $result['key'] }}">
                        <span class="font-mono text-xs truncate" style="color: var(--color-text-primary);" title="{{ $result['key'] }}">{{ $result['key'] }}</span>

                        <span class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal; text-align: right;">
                            {{ $result['ttl'] > 0 ? $result['ttl'] . 's' : ($result['ttl'] === -1 ? 'no expiry' : '—') }}
                        </span>

                        <span class="op-widget-title" style="text-transform: none; opacity: 1; letter-spacing: normal; text-align: right;">
                            {{ $result['sizeBytes'] !== null ? number_format($result['sizeBytes']) . ' B' : '—' }}
                        </span>

                        <span style="text-align: right;">
                            <button wire:click="deleteKey('{{ $result['key'] }}')"
                                x-data
                                x-on:click="if (!confirm('Delete this key?')) $event.preventDefault()"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                wire:target="deleteKey('{{ $result['key'] }}')"
                                class="op-focus-ring op-press inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all duration-200"
                                style="background: var(--danger-600); color: white;">
                                <x-heroicon-o-trash class="w-3 h-3" />
                                Delete
                            </button>
                        </span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-filament-panels::page>
