@props([
    'label' => null,
    'accent' => null,
    'minHeight' => '140px',
])

@php
    $iconAccent = $accent ?? 'var(--primary-500)';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 relative overflow-hidden flex flex-col justify-between p-5 h-full']) }}
     style="min-height: {{ $minHeight }};{{ $accent ? ' border-top-color: '.$accent.' !important;' : '' }}">

    @if ($label || isset($icon) || isset($headerEnd))
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                @isset($icon)
                    <div class="w-10 h-10 rounded-md flex items-center justify-center flex-shrink-0"
                         style="background: color-mix(in srgb, {{ $iconAccent }} 12%, transparent); color: {{ $iconAccent }};"
                         aria-hidden="true">
                        {{ $icon }}
                    </div>
                @endisset
                @if ($label)
                    <span class="text-xs font-semibold uppercase tracking-wider opacity-70 text-zinc-600 dark:text-zinc-300 leading-tight">{{ $label }}</span>
                @endif
            </div>

            @isset($headerEnd)
                {{ $headerEnd }}
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
