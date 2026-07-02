@props([
    'variant' => 'default',
    'title',
    'description',
    'icon' => null,
    'actionLabel' => null,
    'actionUrl' => null,
    'actionMethod' => null,
])

@php
    $iconMap = [
        'default' => 'heroicon-o-inbox',
        'filtered' => 'heroicon-o-magnifying-glass',
        'error' => 'heroicon-o-exclamation-triangle',
        'first-time' => 'heroicon-o-rocket-launch',
    ];

    $iconColorMap = [
        'default' => 'text-zinc-400 dark:text-zinc-500',
        'filtered' => 'text-amber-400',
        'error' => 'text-rose-400',
        'first-time' => 'text-indigo-400',
    ];

    $displayIcon = $icon ?? $iconMap[$variant] ?? $iconMap['default'];
    $iconColor = $iconColorMap[$variant] ?? $iconColorMap['default'];
@endphp

<div class="text-center p-8" role="status" aria-label="{{ $title }}">
    <div class="mx-auto mb-4 opacity-50 w-16 h-16 {{ $iconColor }}">
        @svg($displayIcon, 'w-full h-full')
    </div>

    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ $title }}</h3>

    @if($description)
        <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto mb-6">{{ $description }}</p>
    @endif

    @if($actionLabel && ($actionUrl || $actionMethod))
        <div class="flex justify-center gap-3">
            @if($actionUrl)
                <a href="{{ $actionUrl }}"
                   wire:navigate
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                    {{ $actionLabel }}
                </a>
            @elseif($actionMethod)
                <button wire:click="{{ $actionMethod }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                    {{ $actionLabel }}
                </button>
            @endif
        </div>
    @endif

    @if(isset($slot) && $slot->isNotEmpty())
        <div class="mt-2">
            {{ $slot }}
        </div>
    @endif
</div>
