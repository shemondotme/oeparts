{{--
  Admin brand wordmark: a hexagonal bolt/nut mark (amber accent, Industrial
  Blueprint) + "OeParts" in the display font. currentColor + dark: classes make
  it adapt to light and dark sidebars. Replaces the plain brandName text.
--}}
<div class="flex items-center gap-2">
    <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7 shrink-0" style="color: #F59E0B;" aria-hidden="true">
        <path d="M12 2.5l8.23 4.75v9.5L12 21.5 3.77 16.75v-9.5L12 2.5z"
              stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
        <circle cx="12" cy="12" r="3.1" stroke="currentColor" stroke-width="1.6" />
    </svg>

    <span class="text-lg font-bold tracking-tight text-gray-950 dark:text-white"
          style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
        OeParts
    </span>
</div>
