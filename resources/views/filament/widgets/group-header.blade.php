{{--
    grid-column: 1 / -1 forces this header to span the full dashboard grid
    width regardless of column count. A custom-view widget like this one does
    NOT get Filament's `.fi-wi-widget` grid wrapper (which normally applies the
    column span from getColumnSpan()), so `columnSpan = 'full'` on the PHP class
    has no effect here and the header would otherwise render at one column.
--}}
<div class="flex items-center gap-3 pb-1 pt-3" style="grid-column: 1 / -1;">
    <h3
        class="shrink-0 text-sm font-semibold uppercase tracking-wide"
        style="color: var(--color-text-secondary);"
    >
        {{ $label }}
    </h3>
    <div class="h-px flex-1" style="background: var(--color-border-default);"></div>
</div>
