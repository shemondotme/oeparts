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

<th {{ $attributes->merge(['scope' => 'col', 'class' => 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors']) }}
    @if($sortBy) data-sort-by="{{ $sortBy }}" @endif>
    <div class="flex items-center gap-1">
        {{ $slot }}
        @if($sortBy)
            <x-dynamic-component :component="$icon" class="w-3 h-3 text-gray-400" />
        @endif
    </div>
</th>