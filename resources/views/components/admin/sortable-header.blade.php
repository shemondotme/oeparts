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

<th {{ $attributes->merge(['scope' => 'col', 'class' => 'cursor-pointer px-5 py-3.5 text-left text-[11px] font-semibold uppercase tracking-[0.08em] transition-colors']) }}
    style="color: var(--color-text-muted);"
    @if($sortBy) data-sort-by="{{ $sortBy }}" @endif>
    <div class="flex items-center gap-1">
        {{ $slot }}
        @if($sortBy)
            <x-dynamic-component :component="$icon" class="h-3 w-3" style="color: var(--color-text-muted);" />
        @endif
    </div>
</th>
