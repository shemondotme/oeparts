{{-- Shared OEM search input (Industrial Blueprint).
     The single search-entry control used by every search surface — the console
     landing, the results "re-submit" bar, and the zero-results "re-submit" bar.
     Normalizes input to uppercase alphanumeric and redirects to /{lang}/parts/{oem}.

     Also drives a live autocomplete dropdown against the already-built, cached
     GET /{lang}/search/autocomplete endpoint (SearchService::autocomplete()) —
     debounced, request-aborting, keyboard-navigable.

     Props:
       value     (string) — initial query value (results/zero-results prefill the OEM)
       autofocus (bool)   — focus the field on mount (console landing only)
       size      (string) — 'default' | 'lg' (console uses the larger scale)

     Render the section label/meta row in the caller; this is the <form> only, so
     it slots under a `border-b border-ink` label bar to complete the bordered box.
--}}
@props([
    'value'     => '',
    'autofocus' => false,
    'size'      => 'default',
])

@php
    $lang = app()->getLocale();
    $minChars = (int) settings('search.min_chars', 3);
    $isLg = $size === 'lg';
    $fieldPad = $isLg ? 'px-5 sm:px-7 py-5' : 'px-5 sm:px-7 py-4';
    $inputSize = $isLg ? 'text-xl sm:text-2xl' : 'text-lg sm:text-xl';
    $btnPad = $isLg ? 'px-6 sm:px-10 py-5' : 'px-6 sm:px-8 py-4';
    $autocompleteUrl = url('/'.$lang.'/search/autocomplete');
@endphp

<form x-data="{
          q: @js($value),
          minChars: {{ $minChars }},
          autocompleteUrl: @js($autocompleteUrl),
          stockInLabel: @js(ui_copy('search_stock_in', 'search.stock_in')),
          stockOutLabel: @js(ui_copy('search_stock_out', 'search.stock_out')),
          noResultsLabel: @js(ui_copy('search_no_results', 'search.no_results')),
          suggestions: [],
          showDropdown: false,
          loading: false,
          highlightedIndex: -1,
          debounceTimer: null,
          abortController: null,
          requestSeq: 0,

          submit() {
              const oem = this.q.trim().replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
              if (oem.length >= this.minChars) {
                  window.location.href = '{{ url('/'.$lang.'/parts') }}/' + oem;
              }
          },

          onInput() {
              this.highlightedIndex = -1;
              clearTimeout(this.debounceTimer);

              const term = this.q.trim();
              if (term.length < this.minChars) {
                  this.abortController?.abort();
                  this.loading = false;
                  this.suggestions = [];
                  this.showDropdown = false;
                  return;
              }

              this.debounceTimer = setTimeout(() => this.fetchSuggestions(term), 250);
          },

          async fetchSuggestions(term) {
              this.abortController?.abort();
              const controller = new AbortController();
              this.abortController = controller;
              const seq = ++this.requestSeq;
              this.loading = true;

              try {
                  const response = await fetch(this.autocompleteUrl + '?q=' + encodeURIComponent(term), {
                      signal: controller.signal,
                      headers: { 'Accept': 'application/json' },
                  });
                  if (!response.ok || seq !== this.requestSeq) return;
                  this.suggestions = await response.json();
                  this.showDropdown = true;
              } catch (e) {
                  if (e.name !== 'AbortError') this.suggestions = [];
              } finally {
                  if (seq === this.requestSeq) this.loading = false;
              }
          },

          selectSuggestion(item) {
              this.q = item.normalized_oem;
              this.showDropdown = false;
              this.suggestions = [];
              this.submit();
          },

          onKeydown(e) {
              if (!this.showDropdown || this.suggestions.length === 0) return;

              if (e.key === 'ArrowDown') {
                  e.preventDefault();
                  this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.suggestions.length - 1);
              } else if (e.key === 'ArrowUp') {
                  e.preventDefault();
                  this.highlightedIndex = Math.max(this.highlightedIndex - 1, -1);
              } else if (e.key === 'Enter' && this.highlightedIndex >= 0) {
                  e.preventDefault();
                  this.selectSuggestion(this.suggestions[this.highlightedIndex]);
              } else if (e.key === 'Escape') {
                  this.showDropdown = false;
              }
          }
      }"
      @submit.prevent="submit"
      @click.outside="showDropdown = false"
      class="relative flex flex-col sm:flex-row items-stretch bg-paper border-x border-b border-ink"
      role="search">
    @csrf
    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
    <div class="flex-1 flex items-center gap-4 {{ $fieldPad }} min-w-0">
        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-ink shrink-0" aria-hidden="true" />
        <input type="text"
               x-model="q"
               @input="onInput"
               @keydown="onKeydown"
               @focus="if (suggestions.length > 0) showDropdown = true"
               @if($autofocus) x-init="$nextTick(() => $el.focus())" @endif
               placeholder="{{ ui_copy('search_mini_search_placeholder', 'search.mini_search_placeholder') }}"
               aria-label="{{ ui_copy('search_mini_search_aria', 'search.mini_search_aria') }}"
               autocomplete="off"
               autocapitalize="characters"
               inputmode="text"
               role="combobox"
               :aria-expanded="showDropdown ? 'true' : 'false'"
               aria-autocomplete="list"
               aria-controls="oem-autocomplete-listbox"
               :aria-activedescendant="highlightedIndex >= 0 ? 'oem-autocomplete-option-' + highlightedIndex : null"
               class="flex-1 bg-transparent font-mono uppercase tracking-wider {{ $inputSize }} font-medium text-ink
                      placeholder:normal-case placeholder:tracking-normal placeholder:font-sans placeholder:text-ink-muted/60
                      border-0 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-ink rounded-sm min-w-0 py-1">
        <button type="button"
                x-show="q.length > 0"
                x-cloak
                @click="q = ''; suggestions = []; showDropdown = false"
                class="text-ink-muted hover:text-red-700 shrink-0"
                aria-label="{{ ui_copy('search_oem_input_clear_aria', 'search.oem_input_clear_aria') }}">
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
    </div>
    <button type="submit"
            class="group shrink-0 inline-flex items-center justify-center gap-3 {{ $btnPad }}
                   bg-ink text-ivory font-sans text-[12px] font-bold uppercase tracking-[0.22em]
                   border-t sm:border-t-0 sm:border-l border-ink
                   hover:bg-amber hover:text-ink transition-colors duration-150">
        {{ ui_copy('search_mini_search_button', 'search.mini_search_button') }}
        <x-heroicon-s-arrow-long-right class="w-4 h-4 transform transition-transform group-hover:translate-x-1" aria-hidden="true" />
    </button>

    {{-- Autocomplete dropdown --}}
    <div x-show="showDropdown && suggestions.length > 0"
         x-cloak
         x-transition.opacity.duration.100ms
         id="oem-autocomplete-listbox"
         role="listbox"
         class="absolute top-full left-0 right-0 z-40 bg-paper border-x border-b border-ink shadow-lg max-h-96 overflow-y-auto divide-y divide-ink/10">
        <template x-for="(item, index) in suggestions" :key="item.id">
            <button type="button"
                    :id="'oem-autocomplete-option-' + index"
                    role="option"
                    :aria-selected="highlightedIndex === index"
                    @click="selectSuggestion(item)"
                    @mouseenter="highlightedIndex = index"
                    :class="highlightedIndex === index ? 'bg-ink/5' : ''"
                    class="w-full flex items-center justify-between gap-4 px-5 sm:px-7 py-3 text-left transition-colors duration-100">
                <span class="min-w-0">
                    <span class="block font-mono text-sm font-bold uppercase tracking-wider text-ink truncate" x-text="item.oem"></span>
                    <span class="block font-sans text-xs text-ink-muted truncate" x-text="item.manufacturer || ''"></span>
                </span>
                <span class="shrink-0 flex flex-col items-end gap-1">
                    <span class="font-mono text-sm font-bold text-ink" x-text="item.price_formatted || item.price"></span>
                    <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.18em]"
                          :class="item.in_stock ? 'text-ink' : 'text-ink-muted'">
                        <span class="w-1.5 h-1.5" :class="item.in_stock ? 'bg-amber' : 'border border-rule-strong'"></span>
                        <span x-text="item.in_stock ? stockInLabel : stockOutLabel"></span>
                    </span>
                </span>
            </button>
        </template>
    </div>

    <div x-show="showDropdown && !loading && suggestions.length === 0 && q.trim().length >= minChars"
         x-cloak
         class="absolute top-full left-0 right-0 z-40 bg-paper border-x border-b border-ink shadow-lg px-5 sm:px-7 py-4 font-sans text-sm text-ink-muted">
        <span x-text="noResultsLabel"></span>
    </div>
</form>
