<div class="relative" x-show="true">
    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
        <svg class="w-4 h-4" style="color: var(--color-text-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
    </div>
    <input
        type="text"
        x-model="search"
        placeholder="{{ $placeholder ?? 'Search...' }}"
        aria-label="Search"
        class="w-full pl-11 pr-4 py-3 text-sm rounded-xl transition-all duration-200 focus:ring-2 focus:ring-offset-0"
        style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); color: var(--color-text-primary); --tw-ring-color: var(--primary-500);"
    />
    <div x-show="search.length > 0" class="absolute inset-y-0 right-0 pr-4 flex items-center">
        <button @click="search = ''" class="op-focus-ring text-xs font-medium px-2 py-1 rounded" style="color: var(--color-text-muted);">
            Clear
        </button>
    </div>
</div>
