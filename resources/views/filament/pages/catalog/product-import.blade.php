<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2"
                        style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-arrow-up-tray class="w-5 h-5" style="color: var(--warning-500, #f59e0b);" />
                        Import Products
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted-on-accent, rgba(228, 228, 231, 0.72));">
                        Bulk create or update products from a CSV file. Large files are processed in
                        small chunks — safe for files with millions of rows, keep this window open
                        while it runs.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="downloadQuickTemplate"
                        class="op-focus-ring inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="border: 1px solid rgba(255, 255, 255, 0.2); color: var(--color-text-on-accent, #ffffff);">
                        <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                        Quick Template
                    </button>
                    <button wire:click="downloadFullTemplate"
                        class="op-focus-ring inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="border: 1px solid rgba(255, 255, 255, 0.2); color: var(--color-text-on-accent, #ffffff);">
                        <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                        Full Template
                    </button>
                </div>
            </div>
        </div>

        @if(! $running)
            {{-- Upload form --}}
            <div class="op-card relative overflow-hidden p-6"
                style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
                <div class="text-sm font-bold uppercase tracking-widest font-mono mb-3" style="color: var(--color-text-muted, #6b7280);">
                    Upload CSV
                </div>
                <p class="text-sm mb-4" style="color: var(--color-text-muted, #6b7280);">
                    Required columns: <span class="font-mono">{{ implode(', ', $this->requiredColumns()) }}</span>.
                    Not sure where to start? Download the Quick Template above.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-text-muted, #6b7280);">CSV File</label>
                        <input type="file" wire:model="csvFile" accept=".csv,text/csv,text/plain"
                            class="op-focus-ring block w-full text-sm rounded-xl px-3 py-2"
                            style="background: var(--color-bg-inset, #f3f4f6); border: 1px solid var(--color-border-subtle, #e5e7eb); color: var(--color-text-primary, #111827);" />
                        <div wire:loading wire:target="csvFile" class="mt-1 text-xs" style="color: var(--color-text-muted, #6b7280);">Uploading…</div>
                        @error('csvFile')<div class="mt-1 text-xs" style="color: var(--danger-500, #dc2626);">{{ $message }}</div>@enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm" style="color: var(--color-text-primary, #111827);">
                        <input type="checkbox" wire:model="updateExisting" class="op-focus-ring rounded" />
                        Update existing products (matched by manufacturer + OEM number) — otherwise they're skipped
                    </label>

                    <button wire:click="startImport" wire:loading.attr="disabled" wire:target="startImport,csvFile"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                        style="background: var(--primary-600, #2563eb); color: white;">
                        <x-heroicon-o-arrow-up-tray class="w-3.5 h-3.5" wire:loading.remove wire:target="startImport" />
                        <svg wire:loading wire:target="startImport" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Start Import
                    </button>
                </div>
            </div>
        @else
            {{-- Live progress --}}
            <div wire:poll.1s="pollImport"
                class="op-card relative overflow-hidden p-6"
                style="background: var(--color-bg-surface, #ffffff); border: 1px solid var(--color-border-subtle, #e5e7eb);">
                @php
                    $p = $progress ?? [];
                    $total = $p['total_rows'] ?? null;
                    $processed = $p['processed_rows'] ?? 0;
                    $pct = $total ? min(100, (int) round(($processed / max(1, $total)) * 100)) : null;
                @endphp

                <div class="flex items-center gap-3 mb-4">
                    <svg class="animate-spin h-4 w-4" style="color: var(--primary-600, #2563eb);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-sm font-bold" style="color: var(--color-text-primary, #111827);">
                        Importing… {{ number_format($processed) }}{{ $total ? ' of ~'.number_format($total) : '' }} rows
                        @if($pct !== null) ({{ $pct }}%) @endif
                    </span>
                </div>

                @if($pct !== null)
                    <div class="w-full rounded-full h-2 mb-4 overflow-hidden" style="background: var(--color-bg-inset, #f3f4f6);">
                        <div class="h-2 rounded-full transition-all duration-300" style="width: {{ $pct }}%; background: var(--primary-600, #2563eb);"></div>
                    </div>
                @endif

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">Created</div>
                        <div class="text-lg font-bold font-mono" style="color: var(--success-600, #16a34a);">{{ number_format($p['created_count'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">Updated</div>
                        <div class="text-lg font-bold font-mono" style="color: var(--primary-600, #2563eb);">{{ number_format($p['updated_count'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">Skipped</div>
                        <div class="text-lg font-bold font-mono" style="color: var(--color-text-primary, #111827);">{{ number_format($p['skipped_count'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted, #6b7280);">Errors</div>
                        <div class="text-lg font-bold font-mono" style="color: var(--danger-500, #dc2626);">{{ number_format($p['error_count'] ?? 0) }}</div>
                    </div>
                </div>

                <p class="text-xs" style="color: var(--color-text-muted, #6b7280);">
                    Keep this window open — you can also close it and reopen this page later, the import
                    resumes automatically from where it stopped.
                </p>
            </div>
        @endif

        {{-- Terminal summary (visible right after a run finishes, before the next mount) --}}
        @if(! $running && $progress && in_array($progress['status'] ?? null, ['success', 'failed'], true))
            <div class="op-card relative overflow-hidden p-6"
                style="background: var(--color-bg-surface, #ffffff); border: 1px solid {{ ($progress['status'] ?? '') === 'failed' ? 'var(--danger-500, #dc2626)' : 'var(--color-border-subtle, #e5e7eb)' }};">
                <div class="text-sm font-bold uppercase tracking-widest font-mono mb-3" style="color: var(--color-text-muted, #6b7280);">
                    Last Import {{ ($progress['status'] ?? '') === 'failed' ? '— Failed' : '— Complete' }}
                </div>

                @if(($progress['status'] ?? '') === 'failed')
                    <p class="text-sm" style="color: var(--danger-500, #dc2626);">{{ $progress['error'] ?? 'Unknown error.' }}</p>
                @else
                    <p class="text-sm mb-3" style="color: var(--color-text-muted, #6b7280);">
                        {{ number_format($progress['created_count'] ?? 0) }} created,
                        {{ number_format($progress['updated_count'] ?? 0) }} updated,
                        {{ number_format($progress['skipped_count'] ?? 0) }} skipped,
                        {{ number_format($progress['error_count'] ?? 0) }} errors.
                    </p>
                @endif

                @if(! empty($progress['errors']))
                    <details class="text-xs">
                        <summary class="cursor-pointer font-bold uppercase tracking-wider" style="color: var(--color-text-muted, #6b7280);">
                            Show row errors ({{ count($progress['errors']) }}{{ ($progress['error_count'] ?? 0) > count($progress['errors']) ? '+' : '' }})
                        </summary>
                        <ul class="mt-2 space-y-1 font-mono max-h-64 overflow-y-auto" style="color: var(--color-text-primary, #111827);">
                            @foreach($progress['errors'] as $err)
                                <li>Row {{ $err['row'] }}: {{ $err['message'] }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                <a href="{{ \App\Filament\Pages\Catalog\BulkUpdateLogPage::getUrl() }}"
                    class="mt-4 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider op-focus-ring"
                    style="color: var(--primary-600, #2563eb);">
                    <x-heroicon-o-document-check class="w-3.5 h-3.5" />
                    View full import history
                </a>
            </div>
        @endif
    </div>
</x-filament-panels::page>
