<x-filament-panels::page>
    <div class="space-y-6" x-data="{ search: '' }">
        {{-- Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-text-on-accent, #ffffff); font-family: var(--font-display);">
                        <x-heroicon-o-document-text class="w-5 h-5" style="color: var(--color-warning-500);" />
                        Log Viewer
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Browse and filter application log files. Search by text or filter by log level.
                    </p>
                </div>
            </div>
        </div>

        {{-- Controls --}}
        <div class="op-card overflow-hidden p-4" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="flex flex-col md:flex-row gap-4">
                {{-- File Selector --}}
                <div class="flex-1">
                    <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted);">Log File</label>
                    <select
                        wire:model.live="selectedFile"
                        class="w-full px-4 py-2.5 text-sm rounded-xl transition-all duration-200 focus:ring-2 focus:ring-offset-0"
                        style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); --tw-ring-color: var(--color-brand-500);"
                    >
                        @foreach($this->getLogFiles() as $file)
                            <option value="{{ $file['name'] }}">{{ $file['name'] }} ({{ $file['size'] }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Level Filter --}}
                <div class="w-full md:w-48">
                    <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted);">Level</label>
                    <select
                        wire:model.live="levelFilter"
                        class="w-full px-4 py-2.5 text-sm rounded-xl transition-all duration-200 focus:ring-2 focus:ring-offset-0"
                        style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); --tw-ring-color: var(--color-brand-500);"
                    >
                        <option value="">All Levels</option>
                        <option value="critical">Critical</option>
                        <option value="error">Error</option>
                        <option value="warning">Warning</option>
                        <option value="info">Info</option>
                        <option value="debug">Debug</option>
                    </select>
                </div>

                {{-- Search --}}
                <div class="flex-1">
                    <label class="text-xs font-bold uppercase tracking-widest font-mono mb-2 block" style="color: var(--color-text-muted);">Search</label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Search log content..."
                        class="w-full px-4 py-2.5 text-sm rounded-xl transition-all duration-200 focus:ring-2 focus:ring-offset-0"
                        style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); --tw-ring-color: var(--color-brand-500);"
                    />
                </div>

                {{-- Clear Button --}}
                <div class="flex items-end">
                    <button wire:click="clearLog"
                        x-data
                        x-on:click="if (!confirm('Clear this log file? This cannot be undone.')) $event.preventDefault()"
                        wire:loading
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        class="op-focus-ring op-press inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all duration-200"
                        style="background: var(--color-danger-600); color: white;">
                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                        Clear
                    </button>
                </div>
            </div>
        </div>

        {{-- Log Content --}}
        @php $logs = $this->getLogContent(); @endphp

        <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                </div>
                <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                    Log Entries ({{ count($logs) }} showing, max 200)
                </h3>
            </div>

            <div class="p-0">
                @if(empty($logs))
                    <div class="text-center py-12">
                        <x-heroicon-o-check-circle class="w-12 h-12 mx-auto mb-4" style="color: var(--color-success-500);" />
                        <p class="text-sm font-medium" style="color: var(--color-text-muted);">No log entries found.</p>
                    </div>
                @else
                    <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                        <pre class="p-4 text-xs font-mono leading-relaxed" style="color: var(--color-text-primary); background: var(--color-bg-inset);">@foreach($logs as $line)<div class="py-0.5 hover:bg-white/5" style="color: {{ $line['color'] }};">{{ $line['content'] }}</div>@endforeach</pre>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
