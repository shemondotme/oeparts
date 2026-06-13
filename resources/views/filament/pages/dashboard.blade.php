<x-filament-panels::page class="fi-dashboard">
    <div class="flex justify-end mb-6">
        <div class="flex items-center gap-2 p-1 rounded-xl" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); box-shadow: var(--shadow-1);">
            @foreach(['1' => 'Today', '7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y'] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('period', '{{ $value }}')"
                    aria-pressed="{{ $this->period === $value ? 'true' : 'false' }}"
                    aria-label="Show data for {{ $label }}"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200"
                    style="{{ $this->period === $value
                        ? 'background: var(--color-brand-600); color: white; box-shadow: var(--shadow-1);'
                        : 'color: var(--color-text-secondary);' }}"
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($this->editMode)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm flex items-center gap-2"
             style="background: var(--color-warning-50, #fffbeb); border: 1px dashed var(--color-warning-400, #fbbf24); color: var(--color-warning-700, #b45309);">
            <x-heroicon-o-arrows-pointing-out class="w-4 h-4" />
            Edit mode — drag widgets to move, pull the corner to resize. Changes save automatically.
        </div>
    @endif

    <div
        id="dashboard-canvas"
        wire:ignore.self
        class="grid-stack"
        data-edit-mode="{{ $this->editMode ? '1' : '0' }}"
        data-dashboard-id="{{ $this->activeDashboardId }}"
    >
        @foreach ($this->getCanvasItems() as $item)
            <div
                class="grid-stack-item"
                gs-id="{{ $item['id'] }}"
                gs-x="{{ $item['x'] }}"
                gs-y="{{ $item['y'] }}"
                gs-w="{{ $item['w'] }}"
                gs-h="{{ $item['h'] }}"
            >
                <div class="grid-stack-item-content">
                    @livewire($item['class'], key("widget-{$this->activeDashboardId}-{$item['id']}"))
                </div>
            </div>
        @endforeach
    </div>

    @if ($this->getCanvasItems() === [])
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <x-heroicon-o-squares-2x2 class="w-10 h-10 mb-3" style="color: var(--color-text-secondary);" />
            <p class="text-sm font-semibold" style="color: var(--color-text-primary);">This dashboard is empty</p>
            <p class="text-xs mt-1" style="color: var(--color-text-secondary);">Use “Manage Widgets” to add widgets to this canvas.</p>
        </div>
    @endif
</x-filament-panels::page>
