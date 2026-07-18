@props(['tone' => 'light', 'size' => 'sm', 'as' => 'p'])

@php
    $siteName = settings('general.site_name', 'OeParts');
    [$heavy, $light] = brand_wordmark_parts($siteName);

    $sizeClasses = $size === 'lg'
        ? 'text-4xl sm:text-5xl tracking-[-0.03em] leading-[0.95]'
        : 'text-[22px] tracking-[-0.02em] leading-none';

    $heavyColor = $tone === 'dark' ? 'text-ivory' : 'text-ink';
    $lightColor = $tone === 'dark' ? 'text-ivory/55' : 'text-ink-muted';

    $tag = $as;
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => "font-display {$sizeClasses}"]) }}>
    <span class="font-extrabold {{ $heavyColor }}">{{ $heavy }}</span><span class="font-normal {{ $lightColor }}">{{ $light }}</span><span class="font-extrabold text-amber">.</span>
</{{ $tag }}>
