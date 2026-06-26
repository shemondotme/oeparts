<div class="op-sidebar-pinned" role="navigation" aria-label="Pinned pages">
    <div class="op-sidebar-pinned-header">
        <span class="op-sidebar-pinned-title">Pinned</span>

        @if (count($availableToPin) > 0)
            <div x-data="{ open: false }" class="op-sidebar-pinned-add relative">
                <button
                    type="button"
                    x-on:click="open = !open"
                    x-on:click.outside="open = false"
                    class="op-sidebar-pinned-add-btn"
                    title="Pin a page"
                    aria-label="Pin a page"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>

                <div x-show="open" x-cloak x-on:click.outside="open = false" class="op-sidebar-pinned-add-menu" role="menu">
                    @foreach ($availableToPin as $item)
                        <button
                            type="button"
                            wire:click="pin('{{ $item['key'] }}')"
                            x-on:click="open = false"
                            class="op-sidebar-pinned-add-item"
                            role="menuitem"
                        >
                            <span class="op-sidebar-pinned-add-group">{{ $item['group'] }}</span>
                            <span>{{ $item['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if (count($pinned) > 0)
        <div class="op-sidebar-pinned-list">
            @foreach ($pinned as $item)
                <div class="op-sidebar-pinned-item">
                    <a href="{{ $item['url'] }}" wire:navigate class="op-sidebar-pinned-link">
                        {{ \Filament\Support\generate_icon_html($item['icon'], attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['op-sidebar-pinned-icon'])) }}
                        <span>{{ $item['label'] }}</span>
                    </a>
                    <button
                        type="button"
                        wire:click="unpin('{{ $item['key'] }}')"
                        class="op-sidebar-pinned-unpin"
                        title="Unpin"
                        aria-label="Unpin {{ $item['label'] }}"
                    >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="op-sidebar-pinned-empty">Pin pages here for quick access.</p>
    @endif
</div>
