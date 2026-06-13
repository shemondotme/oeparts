@props([
    'items' => [],
    'theme' => 'light',
    'docId' => null,
    'homeLabel' => null,
])

@php
    $themeClasses = $theme === 'dark'
        ? ['link' => 'text-ivory/50 hover:text-amber transition-colors', 'separator' => 'text-ivory/30', 'current' => 'text-ivory']
        : ['link' => 'text-ink-muted hover:text-ink transition-colors', 'separator' => 'text-rule-strong', 'current' => 'text-ink'];
@endphp

<nav class="flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.16em] {{ $theme === 'dark' ? 'text-ivory/60' : 'text-ink-muted' }}" aria-label="Breadcrumb">
    <a href="{{ url('/'.app()->getLocale().'/') }}" class="{{ $themeClasses['link'] }}">{{ $homeLabel ?? 'Home' }}</a>
    @foreach($items as $item)
        <span class="{{ $themeClasses['separator'] }}">/</span>
        @if(!empty($item['url']))
            <a href="{{ $item['url'] }}" class="{{ $themeClasses['link'] }}">{{ $item['label'] }}</a>
        @else
            <span class="{{ $themeClasses['current'] }}" aria-current="page">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
