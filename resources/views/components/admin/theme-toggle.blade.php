@props([])

<div
    x-data="{
        dark: localStorage.getItem('theme') === 'dark',
        toggle() {
            this.dark = !this.dark;
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', this.dark);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: this.dark } }));
        },
        init() {
            if (this.dark) {
                document.documentElement.classList.add('dark');
            }
        }
    }"
    x-init="init()"
    @theme-changed.window="dark = $event.detail.dark ?? false"
>
    <button
        @click="toggle()"
        type="button"
        class="fi-topbar-item-button flex items-center justify-center w-9 h-9 rounded-lg transition-all duration-200"
        :title="dark ? 'Switch to light mode' : 'Switch to dark mode'"
        aria-label="Toggle theme"
    >
        {{-- Sun icon (light mode) --}}
        <svg x-show="!dark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
             style="color: var(--color-text-muted);">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
        </svg>
        {{-- Moon icon (dark mode) --}}
        <svg x-show="dark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
             style="color: var(--color-text-muted);"
             x-cloak>
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
        </svg>
    </button>
</div>
