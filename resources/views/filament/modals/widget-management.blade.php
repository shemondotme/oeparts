<div
    x-data="widgetManager({{ Js::from($widgets) }})"
    x-init="init()"
    class="flex flex-col gap-0"
>
    {{-- Search + Counter bar --}}
    <div class="flex items-center gap-2 mb-4 flex-shrink-0">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
            <input
                x-model="search"
                type="text"
                placeholder="Search widgets..."
                class="w-full pl-8 pr-7 py-2 text-xs border border-gray-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:outline-none focus:border-amber-400 dark:focus:border-amber-400 focus:ring-1 focus:ring-amber-400/40 transition"
            />
            <template x-if="search.length > 0">
                <button
                    type="button"
                    @click="search = ''"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </template>
        </div>

        {{-- Live counter --}}
        <div class="flex-shrink-0 flex items-center gap-1.5 px-2.5 py-2 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">
            <span
                class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-600 text-white text-[10px] font-bold leading-none tabular-nums"
                x-text="enabledCount"
            ></span>
            <span>active</span>
            <span class="text-gray-300 dark:text-gray-600">/</span>
            <span x-text="widgets.length" class="tabular-nums"></span>
        </div>
    </div>

    {{-- Widget grid --}}
    <div class="grid grid-cols-2 gap-2 overflow-y-auto max-h-64 pr-1" style="max-height:260px;overflow-y:auto">
        <template x-for="widget in filteredWidgets" :key="widget.id">
            <div
                @click="toggleWidget(widget.id)"
                class="flex items-center gap-2.5 px-3 py-2.5 border rounded-md cursor-pointer select-none transition-colors duration-100"
                :class="isEnabled(widget.id)
                    ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-400 dark:border-amber-400'
                    : 'bg-white dark:bg-slate-800/60 border-gray-200 dark:border-slate-700 hover:border-amber-400 dark:hover:border-amber-600/50'"
            >
                {{-- Small icon --}}
                <div
                    class="flex-shrink-0 flex items-center justify-center w-7 h-7 rounded transition-colors overflow-hidden"
                    :class="isEnabled(widget.id)
                        ? 'bg-amber-100 dark:bg-amber-800/40 text-amber-600 dark:text-amber-400'
                        : 'bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-gray-500'"
                >
                    <span x-html="widget.icon" class="flex items-center justify-center w-5 h-5"></span>
                </div>

                {{-- Label + description --}}
                <div class="flex-1 min-w-0">
                    <p
                        class="text-xs font-medium leading-tight truncate transition-colors"
                        :class="isEnabled(widget.id) ? 'text-amber-800 dark:text-amber-200' : 'text-gray-800 dark:text-gray-200'"
                        x-text="widget.label"
                    ></p>
                    <p
                        class="text-[10px] leading-tight mt-0.5 truncate text-gray-400 dark:text-gray-500"
                        x-text="widget.description"
                    ></p>
                </div>

                {{-- Toggle indicator --}}
                <div class="flex-shrink-0">
                    <svg x-show="isEnabled(widget.id)" class="w-4 h-4 text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                    </svg>
                    <svg x-show="!isEnabled(widget.id)" class="w-4 h-4 text-gray-300 dark:text-slate-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </template>
    </div>

    {{-- No results --}}
    <div x-show="filteredWidgets.length === 0" class="py-12 text-center">
        <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
        </svg>
        <p class="text-sm font-medium text-gray-400 dark:text-gray-500">No widgets match "<span x-text="search" class="text-gray-600 dark:text-gray-300"></span>"</p>
        <button type="button" @click="search = ''" class="mt-2 text-xs text-amber-600 dark:text-amber-400 hover:underline">Clear search</button>
    </div>
</div>

<script>
window.widgetManager = function widgetManager(widgets) {
    return {
        widgets: widgets,
        search: '',
        selectedWidgets: new Set(widgets.filter(function(w) { return w.enabled; }).map(function(w) { return w.id; })),

        init() {
            this.$wire.set('widgetSelections', Array.from(this.selectedWidgets));
        },

        get filteredWidgets() {
            var q = this.search.toLowerCase().trim();
            if (!q) return this.widgets;
            return this.widgets.filter(function(w) {
                return w.label.toLowerCase().includes(q) || w.description.toLowerCase().includes(q);
            });
        },

        get enabledCount() {
            return this.selectedWidgets.size;
        },

        isEnabled(id) {
            return this.selectedWidgets.has(id);
        },

        toggleWidget(id) {
            if (this.selectedWidgets.has(id)) {
                this.selectedWidgets.delete(id);
            } else {
                this.selectedWidgets.add(id);
            }
            // Reassign to trigger Alpine reactivity on Set mutation
            this.selectedWidgets = new Set(this.selectedWidgets);
            this.$wire.set('widgetSelections', Array.from(this.selectedWidgets));
        },
    };
}
</script>
</script>
