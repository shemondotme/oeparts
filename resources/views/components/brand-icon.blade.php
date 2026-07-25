@props(['tone' => 'light', 'size' => 'sm'])

@php
    // Single source of truth for the hex-bolt mark, extracted verbatim from
    // components/navbar.blade.php (tone="light") and components/footer.blade.php
    // (tone="dark") — every detail (badge dot, hover rotation, per-tone color
    // swap) preserved exactly so no future call site can silently drop one.
    //
    // The two original hand-authored versions are NOT symmetric: on the light
    // (navbar) mark, the inner hex path shifts ivory→ink on hover; on the dark
    // (footer) mark it stays ink always (that hover step was only needed to
    // keep the light version legible, per footer.blade.php's own comment).
    // Encoding both exactly as authored, not derived from one another.
    $dims = $size === 'lg'
        ? ['icon' => 'w-16 h-16', 'badge' => 'w-2 h-2']
        : ['icon' => 'w-11 h-11', 'badge' => 'w-1.5 h-1.5'];

    if ($tone === 'dark') {
        $outer = 'fill-ivory group-hover:fill-amber';
        $inner = 'fill-ink';
        $cross = 'stroke-ivory group-hover:stroke-amber';
        $dot = 'fill-amber group-hover:fill-ivory';
        $badge = 'bg-amber group-hover:bg-ivory';
    } else {
        $outer = 'fill-ink group-hover:fill-amber';
        $inner = 'fill-ivory group-hover:fill-ink';
        $cross = 'stroke-ink group-hover:stroke-amber';
        $dot = 'fill-amber group-hover:fill-ivory';
        $badge = 'bg-amber group-hover:bg-ink';
    }
@endphp

<div {{ $attributes->merge(['class' => "relative {$dims['icon']} shrink-0"]) }}>
    <div class="transition-transform duration-300 group-hover:rotate-[30deg]">
        <svg viewBox="0 0 60 60" class="w-full h-full" aria-hidden="true">
            <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z"
                  class="{{ $outer }} transition-colors duration-200"/>
            <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z"
                  class="{{ $inner }} transition-colors duration-200"/>
            <path d="M30 18 L30 42 M18 30 L42 30"
                  class="{{ $cross }} transition-colors duration-200"
                  stroke-width="2.5" stroke-linecap="square"/>
            <circle cx="30" cy="30" r="3.2"
                    class="{{ $dot }} transition-colors duration-200"/>
        </svg>
        <span class="absolute -top-0.5 -right-0.5 {{ $dims['badge'] }} {{ $badge }} transition-colors duration-200"></span>
    </div>
</div>
