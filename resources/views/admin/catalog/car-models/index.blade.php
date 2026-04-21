@extends('layouts.admin')

@section('title', 'Car Model Management')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Car Model Management</h1>
            <p class="text-gray-600 mt-1">Manage car models and their associations</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.catalog.car-models.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90">
                <x-heroicon-o-plus class="w-4 h-4" />
                Add Car Model
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.catalog.car-models.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="manufacturer_id" class="block text-sm font-medium text-gray-700 mb-1">Manufacturer</label>
                <select id="manufacturer_id" name="manufacturer_id" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All Manufacturers</option>
                    @foreach($manufacturers as $manufacturer)
                        <option value="{{ $manufacturer->id }}" {{ request('manufacturer_id') == $manufacturer->id ? 'selected' : '' }}>
                            {{ trans_field($manufacturer->name) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Model Name</label>
                <input type="text" id="name" name="name" value="{{ request('name') }}"
                       placeholder="e.g. Golf VII, 3 Series, Audi A4"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="year_from" class="block text-sm font-medium text-gray-700 mb-1">Year From</label>
                    <input type="number" id="year_from" name="year_from" value="{{ request('year_from') }}"
                           placeholder="1990"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label for="year_to" class="block text-sm font-medium text-gray-700 mb-1">Year To</label>
                    <input type="number" id="year_to" name="year_to" value="{{ request('year_to') }}"
                           placeholder="2025"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <div>
                <label for="active_status" class="block text-sm font-medium text-gray-700 mb-1">Active</label>
                <select id="active_status" name="active_status" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All</option>
                    <option value="active" {{ request('active_status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('active_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="md:col-span-4 flex justify-end gap-3 mt-2">
                <a href="{{ route('admin.catalog.car-models.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Reset
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    {{-- Bulk actions bar --}}
    <div id="bulk-actions-bar" class="hidden items-center gap-4 bg-navy/5 border border-navy/20 rounded-xl px-6 py-3 mb-4">
        <span id="selected-count" class="text-sm font-medium text-gray-700">0 items selected</span>
        <div class="flex items-center gap-2">
            <button id="bulk-activate-btn" type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-800 rounded-lg text-sm font-medium hover:bg-green-200 transition-colors">
                <x-heroicon-o-check-circle class="w-4 h-4" />
                Activate
            </button>
            <button id="bulk-deactivate-btn" type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                <x-heroicon-o-x-circle class="w-4 h-4" />
                Deactivate
            </button>
        </div>
        <button id="clear-selection" type="button" class="ml-auto text-sm text-gray-500 hover:text-gray-700">
            Clear selection
        </button>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 w-12">
                            <input type="checkbox" id="select-all-models"
                                   class="rounded border-gray-300 text-navy focus:ring-navy"
                                   title="Select all">
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Model
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Manufacturer
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Years
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Products
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($carModels as $carModel)
                        <tr class="cursor-pointer hover:bg-gray-50 transition-colors"
                            data-edit-url="{{ route('admin.catalog.car-models.edit', $carModel) }}">
                            <td class="px-6 py-4 whitespace-nowrap no-row-click">
                                <input type="checkbox" name="selected_ids[]"
                                       value="{{ $carModel->id }}"
                                       class="model-checkbox rounded border-gray-300 text-navy focus:ring-navy">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $carModel->name }}
                                @if($carModel->slug)
                                    <div class="text-xs text-gray-500">{{ $carModel->slug }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $carModel->manufacturer ? trans_field($carModel->manufacturer->name) : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($carModel->year_from)
                                    {{ $carModel->year_from }}–{{ $carModel->year_to ?? 'present' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($carModel->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $carModel->products_count ?? $carModel->products()->count() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-row-click">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.catalog.car-models.edit', $carModel) }}"
                                       class="text-gray-600 hover:text-gray-900"
                                       title="Edit">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.catalog.car-models.destroy', $carModel) }}" method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this car model?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-inbox class="w-12 h-12 mx-auto text-gray-400" />
                                <p class="mt-2 text-sm">No car models found.</p>
                                <a href="{{ route('admin.catalog.car-models.create') }}"
                                   class="mt-4 inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <x-heroicon-o-plus class="w-4 h-4 mr-1" />
                                    Add your first car model
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($carModels->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $carModels->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Row click navigation
        document.querySelectorAll('tr[data-edit-url]').forEach(row => {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, form, .no-row-click')) return;
                window.location.href = this.dataset.editUrl;
            });
        });

        // Bulk actions
        const selectAllCheckbox = document.getElementById('select-all-models');
        const itemCheckboxes = document.querySelectorAll('.model-checkbox');
        const bulkActionsBar = document.getElementById('bulk-actions-bar');
        const selectedCount = document.getElementById('selected-count');
        const clearSelectionBtn = document.getElementById('clear-selection');
        const bulkActivateBtn = document.getElementById('bulk-activate-btn');
        const bulkDeactivateBtn = document.getElementById('bulk-deactivate-btn');

        function updateBulkActions() {
            const checked = document.querySelectorAll('.model-checkbox:checked');
            const count = checked.length;

            selectedCount.textContent = `${count} item${count !== 1 ? 's' : ''} selected`;

            if (count > 0) {
                bulkActionsBar.classList.remove('hidden');
                bulkActionsBar.classList.add('flex');
            } else {
                bulkActionsBar.classList.add('hidden');
                bulkActionsBar.classList.remove('flex');
            }

            if (count === itemCheckboxes.length && count > 0) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else if (count > 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                itemCheckboxes.forEach(cb => { cb.checked = this.checked; });
                updateBulkActions();
            });
        }

        itemCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });

        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', function () {
                itemCheckboxes.forEach(cb => { cb.checked = false; });
                updateBulkActions();
            });
        }

        function submitBulkForm(action) {
            const checked = document.querySelectorAll('.model-checkbox:checked');
            if (checked.length === 0) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }

        if (bulkActivateBtn) {
            bulkActivateBtn.addEventListener('click', function () {
                const count = document.querySelectorAll('.model-checkbox:checked').length;
                if (confirm(`Activate ${count} selected car model${count !== 1 ? 's' : ''}?`)) {
                    submitBulkForm('{{ route("admin.catalog.car-models.bulk-activate") }}');
                }
            });
        }

        if (bulkDeactivateBtn) {
            bulkDeactivateBtn.addEventListener('click', function () {
                const count = document.querySelectorAll('.model-checkbox:checked').length;
                if (confirm(`Deactivate ${count} selected car model${count !== 1 ? 's' : ''}?`)) {
                    submitBulkForm('{{ route("admin.catalog.car-models.bulk-deactivate") }}');
                }
            });
        }

        updateBulkActions();
    });
</script>
@endpush