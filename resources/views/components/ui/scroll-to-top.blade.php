{{-- ── Scroll to Top (global — mounted once in layouts.app) ──────────────── --}}
<div x-data="{ show: false }"
     x-init="window.addEventListener('scroll', () => { show = window.scrollY > 400 }, { passive: true })"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-cloak
     class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40">
    <button @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="w-10 h-10 bg-ink border border-ink text-ivory flex items-center justify-center
                   hover:bg-amber hover:text-ink transition-colors"
            title="{{ ui_copy('search_scroll_to_top', 'search.scroll_to_top') }}"
            aria-label="{{ ui_copy('search_scroll_to_top', 'search.scroll_to_top') }}">
        <x-heroicon-s-arrow-up class="w-4 h-4" />
    </button>
</div>
