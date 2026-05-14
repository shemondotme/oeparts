@props([
    'sortBy' => null,
    'sortDir' => 'asc',
    'currentSort' => null,
    'currentDir' => null,
])

@php
    $isSorted = $currentSort === $sortBy;
    $icon = 'heroicon-o-arrows-up-down';
    if ($isSorted) {
        $icon = $currentDir === 'asc' ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down';
    }
@endphp

<th {{ $attributes->merge(['scope' => 'col', 'class' => 'px-5 py-3 text-left font-mono text-xs font-medium uppercase tracking-wider text-ink-muted cursor-pointer hover:bg-ivory-alt transition-colors']) }}
    @if($sortBy) data-sort-by="{{ $sortBy }}" @endif>
    <div class="flex items-center gap-1">
        {{ $slot }}
        @if($sortBy)
            <x-dynamic-component :component="$icon" class="w-3 h-3 text-ink-muted" />
        @endif
    </div>
</th>