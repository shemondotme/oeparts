<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    class="op-jump-to-oem relative"
>
    <button
        type="button"
        x-on:click="open = !open; if (open) { $nextTick(() => $refs.oemInput.focus()) }"
        x-bind:aria-expanded="open"
        aria-haspopup="true"
        class="fi-topbar-item-button flex items-center justify-center w-9 h-9 transition-all duration-200"
        title="Jump to OEM number"
        aria-label="Jump to OEM number"
    >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="color: var(--color-text-muted);">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
        </svg>
    </button>

    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
        class="op-jump-oem-dropdown"
        role="menu"
        aria-label="Jump to OEM number"
        style="display: none;"
    >
        <form wire:submit="selectFirst" class="flex items-center gap-2">
            <input
                type="text"
                wire:model.live.debounce.300ms="oem"
                x-ref="oemInput"
                placeholder="OEM number..."
                class="op-jump-oem-input"
                autocomplete="off"
            />

            <button type="submit" class="op-jump-oem-submit" title="Jump">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </form>

        @php
            $badges = [
                'exact' => ['label' => 'Exact', 'class' => 'op-jump-oem-badge-exact'],
                'cross_reference' => ['label' => 'Cross-Ref', 'class' => 'op-jump-oem-badge-cross'],
                'partial' => ['label' => 'Partial', 'class' => 'op-jump-oem-badge-partial'],
            ];
        @endphp

        @if ($oem === '')
            <p class="op-jump-oem-section-label">Recently Viewed</p>

            @php $recents = $this->recents(); @endphp

            @if (count($recents) > 0)
                <div class="op-jump-oem-results">
                    @foreach ($recents as $item)
                        <a href="{{ $item['url'] }}" wire:navigate class="op-jump-oem-result">
                            <span class="op-jump-oem-result-oem">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="op-jump-oem-empty">Pages you visit will appear here.</p>
            @endif
        @elseif (count($results) > 0)
            <p class="op-jump-oem-section-label">
                <span class="op-jump-oem-badge {{ $badges[$searchType]['class'] ?? '' }}">{{ $badges[$searchType]['label'] ?? '' }}</span>
            </p>

            <div class="op-jump-oem-results">
                @foreach ($results as $result)
                    <a href="{{ $result['url'] }}" wire:navigate class="op-jump-oem-result">
                        <span class="op-jump-oem-result-oem">{{ $result['oem'] }}</span>
                        @if ($result['title'] || $result['manufacturer'])
                            <span class="op-jump-oem-result-meta">
                                {{ trim(($result['manufacturer'] ?? '') . ' ' . ($result['title'] ?? '')) }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <p class="op-jump-oem-error">No match for &quot;{{ $oem }}&quot;.</p>
        @endif
    </div>
</div>
