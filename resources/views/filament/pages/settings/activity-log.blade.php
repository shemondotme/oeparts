<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-clock class="w-5 h-5" style="color: var(--color-warning-500);" />
                        Settings Activity Log
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Audit trail of all settings modifications across your platform. Shows who changed what and when.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--color-success-400);">
                    <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--color-success-500);"></span>
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
