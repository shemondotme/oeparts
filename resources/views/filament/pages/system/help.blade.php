<x-filament-panels::page>
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="op-card p-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary); font-family: var(--font-display);">Help & Documentation</h1>
            <p class="mt-2 text-sm" style="color: var(--color-text-muted);">Quick reference for admin panel usage and keyboard shortcuts.</p>
        </div>

        {{-- Keyboard Shortcuts --}}
        <div class="op-card p-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <h2 class="text-lg font-bold mb-4" style="color: var(--color-text-primary);">Keyboard Shortcuts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach([
                    ['keys' => ['⌘', 'S'], 'description' => 'Save current form'],
                    ['keys' => ['⌘', '⌫'], 'description' => 'Go back'],
                    ['keys' => ['⌘', '⇧', 'P'], 'description' => 'Toggle dark mode'],
                    ['keys' => ['⌘', '⇧', 'F'], 'description' => 'Toggle sidebar'],
                    ['keys' => ['Esc'], 'description' => 'Close modal / cancel'],
                    ['keys' => ['↑', '↓'], 'description' => 'Navigate table rows'],
                    ['keys' => ['Enter'], 'description' => 'Open selected row'],
                ] as $shortcut)
                    <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <span class="text-sm" style="color: var(--color-text-muted);">{{ $shortcut['description'] }}</span>
                        <div class="flex items-center gap-1">
                            @foreach($shortcut['keys'] as $key)
                                <kbd class="px-2 py-0.5 text-xs font-mono font-bold rounded" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary);">{{ $key }}</kbd>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="op-card p-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <h2 class="text-lg font-bold mb-4" style="color: var(--color-text-primary);">Quick Reference</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach([
                    ['title' => 'Widget Management', 'description' => 'Click the gear icon on the dashboard to show/hide widgets and reorder them. Changes are saved per-admin.', 'icon' => 'heroicon-o-cog-6-tooth'],
                    ['title' => 'Bulk Actions', 'description' => 'Select multiple rows with checkboxes, then use the bulk actions dropdown for operations like export, status change, or delete.', 'icon' => 'heroicon-o-check-circle'],
                    ['title' => 'CSV Exports', 'description' => 'Most list pages offer CSV export via bulk actions. Select rows, click Export CSV, and download from the notification.', 'icon' => 'heroicon-o-arrow-down-tray'],
                    ['title' => 'Inline Editing', 'description' => 'Some fields (like stock status) can be toggled directly in the table without opening the edit form.', 'icon' => 'heroicon-o-pencil'],
                    ['title' => 'Column Visibility', 'description' => 'Click the columns icon in the table header to show/hide columns. Preferences are saved per-admin.', 'icon' => 'heroicon-o-table-cells'],
                ] as $item)
                    <div class="flex items-start gap-3 p-4 rounded-lg" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <div class="w-8 h-8 flex items-center justify-center rounded-lg shrink-0" style="background: rgba(99,102,241,0.1); color: var(--primary-500);">
                            <x-dynamic-component :component="$item['icon']" class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-sm font-bold" style="color: var(--color-text-primary);">{{ $item['title'] }}</p>
                            <p class="text-xs mt-1" style="color: var(--color-text-muted);">{{ $item['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- System Info --}}
        <div class="op-card p-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <h2 class="text-lg font-bold mb-4" style="color: var(--color-text-primary);">System Information</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div>
                    <p class="text-xs uppercase tracking-widest" style="color: var(--color-text-muted);">Laravel</p>
                    <p class="text-lg font-bold font-mono" style="color: var(--color-text-primary);">{{ app()->version() }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest" style="color: var(--color-text-muted);">PHP</p>
                    <p class="text-lg font-bold font-mono" style="color: var(--color-text-primary);">{{ PHP_VERSION }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest" style="color: var(--color-text-muted);">Filament</p>
                    <p class="text-lg font-bold font-mono" style="color: var(--color-text-primary);">{{ \Composer\InstalledVersions::getPrettyVersion('filament/filament') ?? config('filament.version', '5.x') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest" style="color: var(--color-text-muted);">Environment</p>
                    <p class="text-lg font-bold font-mono" style="color: var(--color-text-primary);">{{ config('app.env') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
