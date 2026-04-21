<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-200 p-6 mb-6']) }}>
    <form method="GET" action="{{ $action }}" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{ $fields }}
        </div>
        
        <div class="md:col-span-4 flex justify-end gap-3 pt-2">
            <a href="{{ $action }}"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Reset
            </a>
            <button type="submit"
                    class="px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90 transition-colors">
                Apply Filters
            </button>
        </div>
    </form>
</div>