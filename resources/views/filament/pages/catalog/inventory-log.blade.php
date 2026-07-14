<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5" style="color: var(--warning-500);" />
                        Inventory Change Log
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted-on-accent, rgba(228, 228, 231, 0.72));">
                        Complete audit trail of all inventory status changes. Tracks CSV imports, manual updates, bulk operations, and system changes.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--success-400);">
                    <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--success-500);"></span>
                    LIVE
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="p-0">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
