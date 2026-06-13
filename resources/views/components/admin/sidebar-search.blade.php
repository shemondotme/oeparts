@props([])

<div
    x-data="{}"
    class="fi-sidebar-search"
>
    <button
        type="button"
        x-on:click="$dispatch('open-spotlight')"
        class="fi-sidebar-search-btn"
        aria-label="Search navigation"
        title="Search navigation (Cmd+K)"
    >
        <svg class="fi-sidebar-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <span class="fi-sidebar-search-text">Quick nav...</span>
        <kbd class="fi-sidebar-search-kbd">⌘K</kbd>
    </button>
</div>
