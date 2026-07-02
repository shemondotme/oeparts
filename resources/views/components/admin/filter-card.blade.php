@props([
    'action',
    'method' => 'GET',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 mb-6 p-5']) }}>
    <form method="{{ $method }}" action="{{ $action }}" class="space-y-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            {{ $fields }}
        </div>

        <div class="flex justify-end gap-3 pt-2 md:col-span-4">
            <a href="{{ $action }}"
               class="inline-flex items-center justify-center rounded-md border border-zinc-300 dark:border-zinc-600 px-4 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-300 bg-white dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors duration-150">
                Reset
            </a>
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm bg-indigo-600 hover:bg-indigo-700 transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                Apply Filters
            </button>
        </div>
    </form>
</div>
