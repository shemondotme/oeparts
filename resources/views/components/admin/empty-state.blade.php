@props([
    'variant' => 'default', // default | filtered | error | first-time
    'title',
    'description',
    'icon' => null,
    'actionLabel' => null,
    'actionUrl' => null,
    'actionMethod' => null,
])

<style>
.empty-state-cta:hover { background: var(--color-brand-700) !important; }
</style>

@php
    $iconMap = [
        'default' => 'heroicon-o-inbox',
        'filtered' => 'heroicon-o-magnifying-glass',
        'error' => 'heroicon-o-exclamation-triangle',
        'first-time' => 'heroicon-o-rocket-launch',
    ];

    $iconColorMap = [
        'default' => 'var(--color-text-muted)',
        'filtered' => 'var(--color-warning-400)',
        'error' => 'var(--color-danger-400)',
        'first-time' => 'var(--color-brand-400)',
    ];

    $displayIcon = $icon ?? $iconMap[$variant] ?? $iconMap['default'];
    $color = $iconColorMap[$variant] ?? $iconColorMap['default'];
@endphp

<div class="op-empty" role="status" aria-label="{{ $title }}">
    {{-- Illustration --}}
    <div class="op-empty-illustration" style="color: {{ $color }};">
        @svg($displayIcon, 'w-full h-full')
    </div>

    {{-- Title --}}
    <h3 class="op-empty-title">{{ $title }}</h3>

    {{-- Description --}}
    @if($description)
        <p class="op-empty-desc">{{ $description }}</p>
    @endif

    {{-- Action --}}
    @if($actionLabel && ($actionUrl || $actionMethod))
        <div class="op-empty-actions">
            @if($actionUrl)
                <a href="{{ $actionUrl }}"
                   wire:navigate
                   class="op-focus-ring op-press empty-state-cta inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200"
                   style="background: var(--color-brand-600); color: white; box-shadow: var(--shadow-1);">
                    {{ $actionLabel }}
                </a>
            @elseif($actionMethod)
                <button wire:click="{{ $actionMethod }}"
                    class="op-focus-ring op-press empty-state-cta inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200"
                    style="background: var(--color-brand-600); color: white; box-shadow: var(--shadow-1);">
                    {{ $actionLabel }}
                </button>
            @endif
        </div>
    @endif

    {{-- Slot for additional content --}}
    @if(isset($slot) && $slot->isNotEmpty())
        <div class="mt-2">
            {{ $slot }}
        </div>
    @endif
</div>
