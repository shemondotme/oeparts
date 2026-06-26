@props([])

@php
    $admin = auth('admin')->user();

    if (! $admin) {
        return;
    }

    // Role-default, permission-gated item list — see AdminUi::quickCreateItemsFor()
    // for the registry and the exact permission checks preserved per item.
    $items = \App\Filament\Support\AdminUi::quickCreateItemsFor($admin);
@endphp

@if (count($items) > 0)
    <div
        x-data="{ open: false }"
        x-on:keydown.escape.window="open = false"
        class="op-quick-create relative"
    >
        <button
            type="button"
            x-on:click="open = !open"
            x-bind:aria-expanded="open"
            aria-haspopup="true"
            class="op-quick-create-btn"
            title="Quick create"
        >
            <svg class="op-quick-create-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span class="op-quick-create-label">New</span>
            <svg class="op-quick-create-caret" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        {{-- Dropdown panel --}}
        <div
            x-show="open"
            x-on:click.outside="open = false"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
            class="op-quick-create-dropdown"
            role="menu"
            aria-label="Quick create options"
            style="display: none;"
        >
            <div class="op-quick-create-dropdown-header">
                Create new
            </div>

            @foreach ($items as $item)
                <a
                    href="{{ $item['url'] }}"
                    x-on:click="open = false"
                    class="op-quick-create-item"
                    role="menuitem"
                >
                    <span class="op-quick-create-item-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="w-4 h-4">
                            {!! $item['icon'] !!}
                        </svg>
                    </span>
                    <span class="op-quick-create-item-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
