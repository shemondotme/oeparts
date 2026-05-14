@props([
    'action',
    'method' => 'GET',
])

<div {{ $attributes->merge(['class' => 'bp-card mb-6 p-5']) }}>
    <form method="{{ $method }}" action="{{ $action }}" class="space-y-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            {{ $fields }}
        </div>

        <div class="flex justify-end gap-3 pt-2 md:col-span-4">
            <a href="{{ $action }}" class="bp-btn-outline">
                Reset
            </a>
            <button type="submit" class="bp-btn-primary">
                Apply Filters
            </button>
        </div>
    </form>
</div>