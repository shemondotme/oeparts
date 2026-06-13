@php
    $admin = auth('admin')->user();
    $onCanvas = array_column($this->getCanvasItems(), 'id');

    $widgets = [];
    foreach (\App\Services\WidgetPreferenceService::WIDGETS as $id => $config) {
        if (!$admin || !$admin->hasAnyRole($config['roles'])) {
            continue;
        }

        $widgets[] = [
            'id' => $id,
            'label' => $config['label'],
            'enabled' => in_array($id, $onCanvas, true),
            'description' => $this->getWidgetDescription($id),
            'icon' => $this->getWidgetIconSvg($id),
        ];
    }
@endphp

<div x-data="widgetManager(@json($widgets))" class="space-y-4">
    <!-- Search Input -->
    <div class="relative">
        <input
            x-model="search"
            type="text"
            placeholder="Search widgets..."
            class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-slate-700 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
        <svg v-if="search" @click="search = ''" class="absolute right-3 top-2.5 w-5 h-5 text-gray-400 cursor-pointer hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </div>

    <!-- Widget Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto pr-2">
        <template x-for="widget in filteredWidgets" :key="widget.id">
            <div
                @click="toggleWidget(widget.id)"
                class="relative p-4 border rounded-lg cursor-pointer transition-all bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-600 hover:shadow-md"
                :class="isEnabled(widget.id) ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''"
            >
                <!-- Checkbox -->
                <div class="absolute top-3 right-3">
                    <input
                        type="checkbox"
                        :checked="isEnabled(widget.id)"
                        class="w-5 h-5 text-amber-500 rounded focus:ring-2 focus:ring-amber-400 cursor-pointer accent-amber-500"
                        @click.stop="toggleWidget(widget.id)"
                    />
                </div>

                <!-- Widget Icon -->
                <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/40 flex-shrink-0">
                    <span v-html="widget.icon"></span>
                </div>

                <!-- Widget Info -->
                <div class="pr-8">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 text-sm">
                        <span x-text="widget.label"></span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 mt-1">
                        <span x-text="widget.description"></span>
                    </p>
                </div>

                <!-- Enabled Badge -->
                <div v-show="isEnabled(widget.id)" class="mt-3 inline-block">
                    <span class="text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-1 rounded">
                        Enabled
                    </span>
                </div>
            </div>
        </template>
    </div>

    <!-- No Results Message -->
    <div v-show="filteredWidgets.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
        <p class="text-sm">No widgets found matching your search.</p>
    </div>

    <!-- Hidden Form Field -->
    <input
        type="hidden"
        name="widgets"
        x-bind:value="JSON.stringify(getFormData())"
    />
</div>

<script>
function widgetManager(widgets) {
    return {
        widgets: widgets,
        search: '',
        selectedWidgets: new Set(widgets.filter(w => w.enabled).map(w => w.id)),

        get filteredWidgets() {
            if (!this.search.trim()) return this.widgets;

            const query = this.search.toLowerCase();
            return this.widgets.filter(widget =>
                widget.label.toLowerCase().includes(query) ||
                widget.description.toLowerCase().includes(query)
            );
        },

        isEnabled(widgetId) {
            return this.selectedWidgets.has(widgetId);
        },

        toggleWidget(widgetId) {
            if (this.selectedWidgets.has(widgetId)) {
                this.selectedWidgets.delete(widgetId);
            } else {
                this.selectedWidgets.add(widgetId);
            }
        },

        getFormData() {
            return this.widgets.map(widget => ({
                id: widget.id,
                label: widget.label,
                enabled: this.selectedWidgets.has(widget.id),
            }));
        },
    };
}
</script>
