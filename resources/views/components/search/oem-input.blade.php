{{-- Shared OEM search input (Industrial Blueprint).
     The single search-entry control used by every search surface — the console
     landing, the results "re-submit" bar, and the zero-results "re-submit" bar.
     Normalizes input to uppercase alphanumeric and redirects to /{lang}/parts/{oem}.

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
@endphp

<form x-data="{
          q: @js($value),
          submit() {
              const oem = this.q.trim().replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
              if (oem.length >= {{ $minChars }}) {
                  window.location.href = '{{ url('/'.$lang.'/parts') }}/' + oem;
              }
          }
      }"
      @submit.prevent="submit"
      class="flex flex-col sm:flex-row items-stretch bg-paper border-x border-b border-ink"
      role="search">
    @csrf
    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
    <div class="flex-1 flex items-center gap-4 {{ $fieldPad }} min-w-0">
        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-ink shrink-0" aria-hidden="true" />
        <input type="text"
               x-model="q"
               @if($autofocus) x-init="$nextTick(() => $el.focus())" @endif
               placeholder="{{ ui_copy('search_mini_search_placeholder', 'search.mini_search_placeholder') }}"
               aria-label="{{ ui_copy('search_mini_search_aria', 'search.mini_search_aria') }}"
               autocomplete="off"
               autocapitalize="characters"
               inputmode="text"
               class="flex-1 bg-transparent font-mono uppercase tracking-wider {{ $inputSize }} font-medium text-ink
                      placeholder:normal-case placeholder:tracking-normal placeholder:font-sans placeholder:text-ink-muted/60
                      border-0 focus:outline-none focus:ring-0 min-w-0 py-1">
        <button type="button"
                x-show="q.length > 0"
                x-cloak
                @click="q = ''"
                class="text-ink-muted hover:text-red-700 shrink-0"
                aria-label="{{ __('Clear') }}">
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
</form>
