@extends('layouts.admin')

@section('title', 'Product Catalog')
@section('page_title', 'Product Catalog')

@section('header_actions')
    <a href="{{ route('admin.catalog.products.import') }}" class="bp-btn-outline">
        <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
        Import CSV
    </a>
    <a href="{{ route('admin.catalog.products.create') }}" class="bp-btn-primary">
        <x-heroicon-o-plus class="w-4 h-4" />
        Add Product
    </a>
@endsection

@section('content')
<div class="space-y-6">
    <section class="bp-card">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Catalog · Product Registry</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Product Catalog<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($products->total()) }} records
            </p>
        </header>

        <form method="GET" action="{{ route('admin.catalog.products.index') }}" class="border-b border-rule bg-paper p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label for="oem" class="block bp-spec mb-2">§ Search OEM number</label>
                    <input
                        id="oem"
                        name="oem"
                        type="text"
                        inputmode="text"
                        autocapitalize="characters"
                        value="{{ request('oem') }}"
                        class="bp-input-mono"
                        placeholder="e.g. 03L 906 018"
                    >
                </div>

                <div class="xl:col-span-2">
                    <label for="manufacturer_id" class="block bp-spec mb-2">§ Manufacturer</label>
                    <select id="manufacturer_id" name="manufacturer_id" class="bp-select">
                        <option value="">All manufacturers</option>
                        @foreach($manufacturers as $manufacturer)
                            <option value="{{ $manufacturer->id }}" @selected((string) request('manufacturer_id') === (string) $manufacturer->id)>
                                {{ trans_field($manufacturer->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="condition" class="block bp-spec mb-2">§ Condition</label>
                    <select id="condition" name="condition" class="bp-select">
                        <option value="all">All conditions</option>
                        @foreach($conditions as $condition)
                            <option value="{{ $condition->value }}" @selected(request('condition') === $condition->value)>
                                {{ $condition->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="stock_status" class="block bp-spec mb-2">§ Stock</label>
                    <select id="stock_status" name="stock_status" class="bp-select">
                        <option value="">All stock states</option>
                        <option value="in_stock" @selected(request('stock_status') === 'in_stock')>In stock</option>
                        <option value="out_of_stock" @selected(request('stock_status') === 'out_of_stock')>Out of stock</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.catalog.products.index') }}" class="bp-btn-outline">Reset</a>
                <button type="submit" class="bp-btn-primary">Apply Filters</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th>OEM</th>
                        <th>Product</th>
                        <th>Manufacturer</th>
                        <th>Condition</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td class="font-mono tabular-nums">
                                <a href="{{ route('admin.catalog.products.show', $product) }}" class="text-ink hover:text-amber-ink hover:underline">
                                    {{ $product->oem_number }}
                                </a>
                                <div class="mt-1 text-xs text-ink-muted">
                                    {{ $product->normalized_oem }}
                                </div>
                            </td>
                            <td>
                                <div class="font-medium">{{ trans_field($product->name) }}</div>
                                @if($product->delivery_time)
                                    <div class="mt-1 font-mono text-xs text-ink-muted">{{ $product->delivery_time }}</div>
                                @endif
                            </td>
                            <td>{{ $product->manufacturer ? trans_field($product->manufacturer->name) : '—' }}</td>
                            <td>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-xs font-mono font-medium uppercase tracking-wider"
                                      style="background-color: {{ $product->condition->badgeBg() }}; color: {{ $product->condition->badgeText() }};">
                                    {{ $product->condition->label() }}
                                </span>
                            </td>
                            <td class="font-mono tabular-nums">{{ format_money($product->price) }}</td>
                            <td>
                                <span class="font-mono text-xs {{ $product->is_in_stock ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $product->is_in_stock ? 'IN STOCK' : 'OUT' }}
                                </span>
                            </td>
                            <td>
                                <span class="font-mono text-xs {{ $product->is_active ? 'text-emerald-600' : 'text-ink-muted' }}">
                                    {{ $product->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.catalog.products.edit', $product) }}" class="bp-btn-ghost" title="Edit product">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.catalog.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Delete this product?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bp-btn-ghost text-red-600 hover:text-red-700" title="Delete product">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center">
                                <x-heroicon-o-cube-transparent class="mx-auto h-10 w-10 text-ink-muted" />
                                <p class="mt-3 font-display text-lg font-bold text-ink">No products found</p>
                                <p class="mt-1 text-sm text-ink-muted">Add the first OEM part or adjust the filters.</p>
                                <a href="{{ route('admin.catalog.products.create') }}" class="bp-btn-primary mt-5">
                                    <x-heroicon-o-plus class="w-4 h-4" />
                                    Add First Product
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="border-t border-rule bg-ivory-alt px-5 py-4">
                {{ $products->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
