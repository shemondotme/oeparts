@props([
    'type'        => 'info',    // info | success | warning | error
    'dismissable' => false,
    'title'       => null,
])

@php
$configs = [
    'info'    => ['bg' => 'bg-blue-50',   'border' => 'border-blue-200',  'text' => 'text-blue-800',  'icon' => 'heroicon-o-information-circle', 'icon_color' => 'text-blue-400'],
    'success' => ['bg' => 'bg-green-50',  'border' => 'border-green-200', 'text' => 'text-green-800', 'icon' => 'heroicon-o-check-circle',        'icon_color' => 'text-green-400'],
    'warning' => ['bg' => 'bg-amber/10',  'border' => 'border-amber/30',  'text' => 'text-amber-text','icon' => 'heroicon-o-exclamation-triangle', 'icon_color' => 'text-amber'],
    'error'   => ['bg' => 'bg-red-50',    'border' => 'border-red-200',   'text' => 'text-red-800',   'icon' => 'heroicon-o-x-circle',            'icon_color' => 'text-red-400'],
];
$c = $configs[$type] ?? $configs['info'];
@endphp

<div
    @if($dismissable) x-data="{ show: true }" x-show="show" @endif
    {{ $attributes->merge(['class' => "flex items-start gap-3 px-4 py-3 rounded-lg border {$c['bg']} {$c['border']} {$c['text']} text-sm"]) }}
    role="alert"
>
    <x-dynamic-component :component="$c['icon']" class="w-5 h-5 shrink-0 mt-0.5 {{ $c['icon_color'] }}" />

    <div class="flex-1 min-w-0">
        @if($title)
        <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>

    @if($dismissable)
    <button @click="show = false" class="shrink-0 opacity-50 hover:opacity-100 transition-opacity ml-auto" aria-label="Dismiss">
        <x-heroicon-o-x-mark class="w-4 h-4" />
    </button>
    @endif
</div>
