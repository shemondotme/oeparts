<div
    x-data="widgetManager(@json($widgets))"
    x-init="init()"
    class="pb-2"
>
    {{-- Search + Counter bar --}}
    <div class="flex items-center gap-3 mb-5">
        <div class="relative flex-1">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <input
                x-model="search"
                type="text"
                placeholder="Search widgets..."
                class="w-full pl-9 pr-9 py-2 text-sm border rounded-lg bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-100 border-gray-200 dark:border-slate-700 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500/60 focus:border-amber-400 transition"
            />
            <template x-if="search.length > 0">
                <button
                    type="button"
                    @click="search = ''"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </template>
        </div>

        {{-- Live counter --}}
        <div class="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-xs font-medium text-gray-600 dark:text-gray-400">
            <span
                class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white text-[10px] font-bold"
                x-text="enabledCount"
            ></span>
            <span>active</span>
            <span class="text-gray-300 dark:text-gray-600">/</span>
            <span x-text="widgets.length"></span>
        </div>
    </div>

    {{-- Widget grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 overflow-y-auto max-h-[420px] pr-1 scrollbar-thin">
        <template x-for="widget in filteredWidgets" :key="widget.id">
            <div
                @click="toggleWidget(widget.id)"
                class="relative flex flex-col p-3 rounded-xl border cursor-pointer select-none transition-all duration-150"
                :class="isEnabled(widget.id)
                    ? 'bg-amber-50/60 dark:bg-amber-950/25 border-amber-400 dark:border-amber-500 ring-1 ring-amber-400/40 shadow-sm'
                    : 'bg-white dark:bg-slate-800/60 border-gray-200 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-600/60 hover:shadow-sm'"
            >
                {{-- Checkbox --}}
                <div class="absolute top-2.5 right-2.5">
                    <div
                        class="w-5 h-5 rounded flex items-center justify-center border-2 transition-all"
                        :class="isEnabled(widget.id)
                            ? 'bg-amber-500 border-amber-500'
                            : 'bg-white dark:bg-slate-700 border-gray-300 dark:border-slate-500'"
                    >
                        <svg
                            x-show="isEnabled(widget.id)"
                            class="w-3 h-3 text-white"
                            fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="3"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </div>
                </div>

                {{-- Icon --}}
                <div
                    class="flex items-center justify-center w-9 h-9 rounded-lg mb-2.5 flex-shrink-0 transition-colors"
                    :class="isEnabled(widget.id)
                        ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400'
                        : 'bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-gray-500'"
                >
                    <span x-html="widget.icon"></span>
                </div>

                {{-- Label + description --}}
                <div class="flex-1 pr-5">
                    <p
                        class="text-xs font-semibold leading-snug transition-colors"
                        :class="isEnabled(widget.id) ? 'text-amber-900 dark:text-amber-200' : 'text-gray-800 dark:text-gray-200'"
                        x-text="widget.label"
                    ></p>
                    <p
                        class="text-[10px] leading-snug mt-0.5 line-clamp-2 text-gray-400 dark:text-gray-500"
                        x-text="widget.description"
                    ></p>
                </div>

                {{-- Active pill --}}
                <div class="mt-2" x-show="isEnabled(widget.id)">
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 rounded-full">
                        <svg class="w-2 h-2 fill-amber-500" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                        Active
                    </span>
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
function widgetManager(widgets) {
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
