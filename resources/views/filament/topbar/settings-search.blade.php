{{--
  Ctrl+K settings command palette. Alpine-only (no Livewire), same
  debounce/AbortController/keyboard-nav mechanics as
  resources/views/components/search/oem-input.blade.php, restyled with the
  admin panel's own CSS-variable design tokens (matching
  filament/components/cluster-search.blade.php) rather than the
  storefront's Industrial Blueprint tokens that source file uses.

  Trigger: click the topbar button, or press Ctrl+K / Cmd+K anywhere in
  the admin panel (confirmed zero collision — this panel's own Filament
  global search has no keybinding configured, see
  Filament\Panel\Concerns\HasGlobalSearch's empty-array default).
--}}
@php
    $endpoint = url('/admin/settings-search');
    $canAccessSettings = auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
@endphp

@if ($canAccessSettings)
<div x-data="{
        open: false,
        query: '',
        results: [],
        loading: false,
        highlightedIndex: -1,
        debounceTimer: null,
        abortController: null,
        requestSeq: 0,
        lastFocusedElement: null,
        endpoint: @js($endpoint),

        init() {
            window.addEventListener('keydown', (e) => {
                const isMod = e.ctrlKey || e.metaKey;
                if (isMod && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.openPalette();
                } else if (e.key === 'Escape' && this.open) {
                    this.close();
                }
            });
        },

        openPalette() {
            // Remember whatever had focus (the topbar button, or wherever
            // the keyboard shortcut was pressed from) so close() can put
            // focus back — otherwise a keyboard user loses their place
            // every time they dismiss the palette.
            this.lastFocusedElement = document.activeElement;
            this.open = true;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        close() {
            this.open = false;
            this.query = '';
            this.results = [];
            this.highlightedIndex = -1;
            this.abortController?.abort();
            this.$nextTick(() => this.lastFocusedElement?.focus?.());
        },

        onInput() {
            this.highlightedIndex = -1;
            clearTimeout(this.debounceTimer);

            const term = this.query.trim();
            if (term.length < 2) {
                this.abortController?.abort();
                this.loading = false;
                this.results = [];
                return;
            }

            this.debounceTimer = setTimeout(() => this.fetchResults(term), 250);
        },

        async fetchResults(term) {
            this.abortController?.abort();
            const controller = new AbortController();
            this.abortController = controller;
            const seq = ++this.requestSeq;
            this.loading = true;

            try {
                const response = await fetch(this.endpoint + '?q=' + encodeURIComponent(term), {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok || seq !== this.requestSeq) return;
                const data = await response.json();
                this.results = data.results ?? [];
            } catch (e) {
                if (e.name !== 'AbortError') this.results = [];
            } finally {
                if (seq === this.requestSeq) this.loading = false;
            }
        },

        selectResult(item) {
            if (!item) return;
            window.location.href = item.url;
        },

        onKeydown(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.results.length - 1);
                this.scrollHighlightedIntoView();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.highlightedIndex = Math.max(this.highlightedIndex - 1, -1);
                this.scrollHighlightedIntoView();
            } else if (e.key === 'Enter' && this.highlightedIndex >= 0) {
                e.preventDefault();
                this.selectResult(this.results[this.highlightedIndex]);
            } else if (e.key === 'Escape') {
                this.close();
            }
        },

        scrollHighlightedIntoView() {
            this.$nextTick(() => {
                this.$refs['result-' + this.highlightedIndex]?.scrollIntoView({ block: 'nearest' });
            });
        },
    }"
    x-cloak
>
    {{-- Topbar trigger --}}
    <button
        type="button"
        @click="openPalette()"
        class="op-focus-ring inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors duration-150"
        style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-muted);"
        aria-label="Search settings (Ctrl+K)"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <span class="hidden sm:inline">Settings</span>
        <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">Ctrl K</kbd>
    </button>

    {{-- Modal overlay --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-0 z-[1000] flex items-start justify-center pt-[10vh] px-4"
        style="background: rgba(0,0,0,0.5);"
        @click.self="close()"
    >
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            @click.outside="close()"
            class="w-full max-w-xl rounded-xl shadow-2xl overflow-hidden"
            style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);"
            role="dialog"
            aria-modal="true"
            aria-label="Search settings"
        >
            {{-- Search input --}}
            <div class="flex items-center gap-3 px-4 py-3" style="border-bottom: 1px solid var(--color-border-subtle);">
                <svg class="w-5 h-5 shrink-0" style="color: var(--color-text-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input
                    type="text"
                    x-ref="input"
                    x-model="query"
                    @input="onInput"
                    @keydown="onKeydown"
                    placeholder="Search settings… (e.g. SMTP, VAT, GTM)"
                    aria-label="Search settings"
                    role="combobox"
                    :aria-expanded="results.length > 0 ? 'true' : 'false'"
                    aria-autocomplete="list"
                    aria-controls="settings-search-listbox"
                    :aria-activedescendant="highlightedIndex >= 0 ? 'settings-search-option-' + highlightedIndex : null"
                    autocomplete="off"
                    class="flex-1 bg-transparent text-sm border-0 focus:outline-none focus:ring-0 min-w-0"
                    style="color: var(--color-text-primary);"
                />
                <kbd class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-mono" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle); color: var(--color-text-muted);">Esc</kbd>
            </div>

            {{-- Screen-reader-only announcement of result-count/loading changes —
                 the visible list below is a role="listbox", but its content
                 changing doesn't get spoken on its own without this. --}}
            <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="
                loading
                    ? 'Searching…'
                    : (query.trim().length >= 2
                        ? results.length + (results.length === 1 ? ' result found' : ' results found')
                        : '')
            "></div>

            {{-- Results --}}
            <div
                id="settings-search-listbox"
                role="listbox"
                class="max-h-96 overflow-y-auto py-2"
            >
                <template x-if="query.trim().length >= 2 && !loading && results.length === 0">
                    <p class="px-4 py-6 text-sm text-center" style="color: var(--color-text-muted);">No settings match your search.</p>
                </template>

                <template x-if="query.trim().length > 0 && query.trim().length < 2">
                    <p class="px-4 py-6 text-sm text-center" style="color: var(--color-text-muted);">Keep typing… (2+ characters)</p>
                </template>

                <template x-for="(item, index) in results" :key="item.url + '::' + item.label">
                    <a
                        :href="item.url"
                        :id="'settings-search-option-' + index"
                        :x-ref="'result-' + index"
                        role="option"
                        :aria-selected="highlightedIndex === index"
                        @mouseenter="highlightedIndex = index"
                        @click="selectResult(item)"
                        class="flex items-center justify-between gap-3 px-4 py-2.5 no-underline transition-colors duration-100"
                        :style="highlightedIndex === index ? 'background: var(--color-bg-inset);' : ''"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-medium truncate" style="color: var(--color-text-primary);" x-text="item.label"></span>
                            <span class="block text-xs truncate" style="color: var(--color-text-muted);" x-text="item.section + ' › ' + item.page + (item.tab ? ' › ' + item.tab : '')"></span>
                        </span>
                        <svg class="w-4 h-4 shrink-0" style="color: var(--warning-500);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </template>
            </div>

            <div class="flex items-center justify-between px-4 py-2 text-[11px]" style="border-top: 1px solid var(--color-border-subtle); color: var(--color-text-muted);">
                <span>↑↓ navigate · ↵ open · Esc close</span>
                <span x-show="loading">Searching…</span>
            </div>
        </div>
    </div>
</div>
@endif
