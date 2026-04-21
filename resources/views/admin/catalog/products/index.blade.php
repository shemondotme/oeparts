@extends('layouts.admin')

@section('title', 'Product Management')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Product Management</h1>
            <p class="text-gray-600 mt-1">Manage your catalog of OEM auto parts</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.catalog.bulk-update.logs') }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <x-heroicon-o-clock class="w-4 h-4" />
                Bulk Logs
            </a>
            <a href="{{ route('admin.catalog.bulk-update.index', ['entity' => 'products']) }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
                Bulk Update
            </a>
            <a href="{{ route('admin.catalog.products.import') }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                Import CSV
            </a>
            <a href="{{ route('admin.catalog.products.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90">
                <x-heroicon-o-plus class="w-4 h-4" />
                Add Product
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.catalog.products.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="oem" class="block text-sm font-medium text-gray-700 mb-1">OEM Number</label>
                <input type="text" id="oem" name="oem" value="{{ request('oem') }}"
                       placeholder="e.g. 0 986 479 084"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>

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
                <label for="condition" class="block text-sm font-medium text-gray-700 mb-1">Condition</label>
                <select id="condition" name="condition" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="all" {{ request('condition', 'all') === 'all' ? 'selected' : '' }}>All Conditions</option>
                    @foreach($conditions as $condition)
                        <option value="{{ $condition->value }}" {{ request('condition') === $condition->value ? 'selected' : '' }}>
                            {{ $condition->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="stock_status" class="block text-sm font-medium text-gray-700 mb-1">Stock</label>
                    <select id="stock_status" name="stock_status" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All</option>
                        <option value="in_stock" {{ request('stock_status') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                        <option value="out_of_stock" {{ request('stock_status') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                    </select>
                </div>
                <div>
                    <label for="active_status" class="block text-sm font-medium text-gray-700 mb-1">Active</label>
                    <select id="active_status" name="active_status" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All</option>
                        <option value="active" {{ request('active_status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('active_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="md:col-span-4 flex justify-end gap-3 mt-2">
                <a href="{{ route('admin.catalog.products.index') }}"
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

    {{-- Bulk Actions --}}
    <div id="bulk-actions" class="hidden bg-white rounded-xl border border-gray-200 p-4 mb-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span id="selected-count" class="text-sm font-medium text-gray-700">0 items selected</span>
                <button type="button" id="bulk-delete" class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 border border-red-300 rounded-lg hover:bg-red-50">
                    Delete Selected
                </button>
                <button type="button" id="bulk-activate" class="px-3 py-1.5 text-sm font-medium text-green-600 hover:text-green-800 border border-green-300 rounded-lg hover:bg-green-50">
                    Activate
                </button>
                <button type="button" id="bulk-deactivate" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Deactivate
                </button>
            </div>
            <button type="button" id="clear-selection" class="text-sm text-gray-500 hover:text-gray-700">
                Clear selection
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300 text-navy focus:ring-navy">
                        </th>
                        <x-admin.sortable-header sortBy="oem_number">
                            OEM Number
                        </x-admin.sortable-header>
                        <x-admin.sortable-header sortBy="manufacturer_id">
                            Manufacturer
                        </x-admin.sortable-header>
                        <x-admin.sortable-header sortBy="name">
                            Name
                        </x-admin.sortable-header>
                        <x-admin.sortable-header sortBy="condition">
                            Condition
                        </x-admin.sortable-header>
                        <x-admin.sortable-header sortBy="price">
                            Price
                        </x-admin.sortable-header>
                        <x-admin.sortable-header sortBy="is_in_stock">
                            Stock
                        </x-admin.sortable-header>
                        <x-admin.sortable-header sortBy="is_active">
                            Status
                        </x-admin.sortable-header>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($products as $product)
                        <tr class="cursor-pointer hover:bg-gray-50 transition-colors"
                            data-edit-url="{{ route('admin.catalog.products.edit', $product) }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" name="selected_ids[]" value="{{ $product->id }}" class="rounded border-gray-300 text-navy focus:ring-navy">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                {{ $product->oem_number }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $product->manufacturer ? trans_field($product->manufacturer->name) : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                {{ $product->name[app()->getLocale()] ?? $product->name['en'] ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($product->condition)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          style="background:{{ $product->condition->badgeBg() }};color:{{ $product->condition->badgeText() }}">
                                        {{ $product->condition->label() }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 no-row-click price-cell"
                                data-inline-url="{{ route('admin.catalog.products.inline-update', $product) }}"
                                data-price="{{ $product->price }}"
                                title="Double-click to edit price">
                                <span class="price-display cursor-text select-none hover:bg-amber/10 rounded px-1 py-0.5 transition-colors">{{ format_money($product->price) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm no-row-click">
                                <button type="button"
                                        class="stock-toggle inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors {{ $product->is_in_stock ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}"
                                        data-product-id="{{ $product->id }}"
                                        data-inline-url="{{ route('admin.catalog.products.inline-update', $product) }}"
                                        data-in-stock="{{ $product->is_in_stock ? '1' : '0' }}"
                                        title="Click to toggle stock status">
                                    {{ $product->is_in_stock ? 'In Stock' : 'Out of Stock' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($product->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-row-click">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.catalog.products.show', $product) }}"
                                       class="text-gray-400 hover:text-gray-700"
                                       title="View">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                    <a href="{{ route('admin.catalog.products.edit', $product) }}"
                                       class="text-gray-600 hover:text-gray-900"
                                       title="Edit">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.catalog.products.destroy', $product) }}" method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this product?');">
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
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-inbox class="w-12 h-12 mx-auto text-gray-400" />
                                <p class="mt-2 text-sm">No products found.</p>
                                <a href="{{ route('admin.catalog.products.create') }}"
                                   class="mt-4 inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <x-heroicon-o-plus class="w-4 h-4 mr-1" />
                                    Add your first product
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $products->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Row click for quick edit
        document.querySelectorAll('tr[data-edit-url]').forEach(row => {
            row.addEventListener('click', function(e) {
                // Don't navigate if user clicked on a button, link, or action cell
                if (e.target.closest('a, button, form, .no-row-click')) {
                    return;
                }
                window.location.href = this.dataset.editUrl;
            });
        });

        // Sortable headers (placeholder - would need backend support)
        document.querySelectorAll('th[data-sort-by]').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sortBy;
                const currentUrl = new URL(window.location.href);
                const currentSort = currentUrl.searchParams.get('sort');
                const currentDir = currentUrl.searchParams.get('dir');
                
                let newDir = 'asc';
                if (currentSort === sortBy && currentDir === 'asc') {
                    newDir = 'desc';
                }
                
                currentUrl.searchParams.set('sort', sortBy);
                currentUrl.searchParams.set('dir', newDir);
                window.location.href = currentUrl.toString();
            });
        });

        // Bulk actions
        const selectAllCheckbox = document.getElementById('select-all');
        const itemCheckboxes = document.querySelectorAll('input[name="selected_ids[]"]');
        const bulkActions = document.getElementById('bulk-actions');
        const selectedCount = document.getElementById('selected-count');
        const clearSelectionBtn = document.getElementById('clear-selection');
        const bulkDeleteBtn = document.getElementById('bulk-delete');
        const bulkActivateBtn = document.getElementById('bulk-activate');
        const bulkDeactivateBtn = document.getElementById('bulk-deactivate');

        function updateBulkActions() {
            const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            const count = checked.length;
            
            selectedCount.textContent = `${count} item${count !== 1 ? 's' : ''} selected`;
            
            if (count > 0) {
                bulkActions.classList.remove('hidden');
            } else {
                bulkActions.classList.add('hidden');
            }
            
            // Update select-all state
            if (count === itemCheckboxes.length) {
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

        // Select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                updateBulkActions();
            });
        }

        // Individual checkboxes
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });

        // Clear selection
        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', function() {
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateBulkActions();
            });
        }

        // Bulk delete
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
                if (checked.length === 0) return;
                
                if (confirm(`Are you sure you want to delete ${checked.length} selected item(s)?`)) {
                    // Create a form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("admin.catalog.products.bulk-destroy") }}';
                    
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = '{{ csrf_token() }}';
                    form.appendChild(csrf);
                    
                    const method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'DELETE';
                    form.appendChild(method);
                    
                    checked.forEach(checkbox => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = checkbox.value;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Bulk activate
        if (bulkActivateBtn) {
            bulkActivateBtn.addEventListener('click', function() {
                const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
                if (checked.length === 0) return;
                
                if (confirm(`Are you sure you want to activate ${checked.length} selected item(s)?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("admin.catalog.products.bulk-activate") }}';
                    
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = '{{ csrf_token() }}';
                    form.appendChild(csrf);
                    
                    checked.forEach(checkbox => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = checkbox.value;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Bulk deactivate
        if (bulkDeactivateBtn) {
            bulkDeactivateBtn.addEventListener('click', function() {
                const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
                if (checked.length === 0) return;
                
                if (confirm(`Are you sure you want to deactivate ${checked.length} selected item(s)?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("admin.catalog.products.bulk-deactivate") }}';
                    
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = '{{ csrf_token() }}';
                    form.appendChild(csrf);
                    
                    checked.forEach(checkbox => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = checkbox.value;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Initial update
        updateBulkActions();

        // Stock toggle
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        document.querySelectorAll('.stock-toggle').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const url = this.dataset.inlineUrl;
                const isInStock = this.dataset.inStock === '1';
                const newValue = isInStock ? 0 : 1;

                // Optimistic update
                if (newValue) {
                    this.classList.remove('bg-red-100', 'text-red-800');
                    this.classList.add('bg-green-100', 'text-green-800');
                    this.textContent = 'In Stock';
                } else {
                    this.classList.remove('bg-green-100', 'text-green-800');
                    this.classList.add('bg-red-100', 'text-red-800');
                    this.textContent = 'Out of Stock';
                }
                this.dataset.inStock = newValue ? '1' : '0';

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ field: 'is_in_stock', value: newValue }),
                }).then(res => {
                    if (!res.ok) throw new Error('Failed');
                }).catch(() => {
                    // Revert on error
                    if (isInStock) {
                        this.classList.remove('bg-red-100', 'text-red-800');
                        this.classList.add('bg-green-100', 'text-green-800');
                        this.textContent = 'In Stock';
                    } else {
                        this.classList.remove('bg-green-100', 'text-green-800');
                        this.classList.add('bg-red-100', 'text-red-800');
                        this.textContent = 'Out of Stock';
                    }
                    this.dataset.inStock = isInStock ? '1' : '0';
                });
            });
        });

        // Price inline edit
        document.querySelectorAll('.price-cell').forEach(cell => {
            cell.addEventListener('dblclick', function(e) {
                e.stopPropagation();
                if (this.querySelector('input.price-input')) return; // already editing

                const url = this.dataset.inlineUrl;
                const originalPrice = this.dataset.price;
                const displaySpan = this.querySelector('.price-display');
                const originalDisplay = displaySpan.textContent;

                const input = document.createElement('input');
                input.type = 'text';
                input.inputMode = 'decimal';
                input.value = originalPrice;
                input.className = 'price-input w-24 px-2 py-0.5 border border-amber rounded text-sm font-medium font-mono focus:outline-none focus:ring-2 focus:ring-amber/50';

                displaySpan.replaceWith(input);
                input.focus();
                input.select();

                const save = () => {
                    const newValue = input.value.trim().replace(',', '.');
                    if (newValue === originalPrice || newValue === '') {
                        revert();
                        return;
                    }
                    if (isNaN(parseFloat(newValue))) {
                        revert();
                        return;
                    }

                    // Show loading state
                    input.disabled = true;
                    input.classList.add('opacity-50');

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ field: 'price', value: newValue }),
                    }).then(res => {
                        if (!res.ok) throw new Error('Failed');
                        return res.json();
                    }).then(data => {
                        const newSpan = document.createElement('span');
                        newSpan.className = 'price-display cursor-text select-none hover:bg-amber/10 rounded px-1 py-0.5 transition-colors';
                        // Use the returned formatted price if available, otherwise raw value
                        newSpan.textContent = data.data?.formatted_price ?? newValue;
                        input.replaceWith(newSpan);
                        cell.dataset.price = data.data?.price ?? newValue;
                    }).catch(() => {
                        revert();
                    });
                };

                const revert = () => {
                    const newSpan = document.createElement('span');
                    newSpan.className = 'price-display cursor-text select-none hover:bg-amber/10 rounded px-1 py-0.5 transition-colors';
                    newSpan.textContent = originalDisplay;
                    input.replaceWith(newSpan);
                };

                input.addEventListener('blur', save);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        input.removeEventListener('blur', save);
                        save();
                    } else if (e.key === 'Escape') {
                        input.removeEventListener('blur', save);
                        revert();
                    }
                });
            });
        });
    });
</script>
@endpush