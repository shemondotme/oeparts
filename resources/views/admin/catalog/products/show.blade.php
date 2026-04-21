@extends('layouts.admin')

@section('title', 'Product Details')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Product Details</h1>
                <p class="text-gray-600 mt-1">View complete information for {{ $product->oem_number }}.</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.catalog.products.edit', $product) }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber">
                    <x-heroicon-o-pencil class="w-4 h-4 mr-2" />
                    Edit
                </a>
                <a href="{{ route('admin.catalog.products.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    Back to Products
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left Column: Product Details --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Basic Information Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Basic Information</h2>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">OEM Number</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $product->oem_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Normalized OEM</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $product->normalized_oem }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Manufacturer</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('admin.catalog.manufacturers.show', $product->manufacturer) }}"
                                   class="text-amber hover:text-amber/80">
                                    {{ trans_field($product->manufacturer->name) }}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Condition</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $product->condition->color() }}">
                                    {{ $product->condition->label() }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Price</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-medium">{{ format_price($product->price) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Delivery Time</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $product->delivery_time ?? 'Not specified' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Multilingual Content Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Multilingual Content</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Name</h3>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <pre class="text-sm text-gray-800 whitespace-pre-wrap">{{ json_encode($product->name, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                        @if($product->description)
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Description</h3>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <pre class="text-sm text-gray-800 whitespace-pre-wrap">{{ json_encode($product->description, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Inventory & Status Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Inventory & Status</h2>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Minimum Order Quantity</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $product->moq }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center">
                            <div class="h-3 w-3 rounded-full {{ $product->is_active ? 'bg-green-500' : 'bg-red-500' }} mr-2"></div>
                            <span class="text-sm {{ $product->is_active ? 'text-green-700' : 'text-red-700' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center">
                            <div class="h-3 w-3 rounded-full {{ $product->is_in_stock ? 'bg-green-500' : 'bg-red-500' }} mr-2"></div>
                            <span class="text-sm {{ $product->is_in_stock ? 'text-green-700' : 'text-red-700' }}">
                                {{ $product->is_in_stock ? 'In Stock' : 'Out of Stock' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Compatible Car Models Card --}}
            @if($product->carModels->count() > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Compatible Car Models</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($product->carModels as $carModel)
                        <div class="flex items-center p-3 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ trans_field($carModel->manufacturer->name) }} {{ $carModel->name }}</p>
                                @if($carModel->year_from)
                                <p class="text-xs text-gray-500">{{ $carModel->year_from }}–{{ $carModel->year_to ?? 'present' }}</p>
                            @endif
                            </div>
                            <a href="{{ route('admin.catalog.car-models.show', $carModel) }}"
                               class="ml-2 text-amber hover:text-amber/80">
                                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Inventory History --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Inventory History</h2>
                </div>
                @if($product->inventoryLogs->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Note</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($product->inventoryLogs as $log)
                                @php
                                    $typeColors = [
                                        'csv_import'  => 'bg-blue-100 text-blue-700',
                                        'manual'      => 'bg-amber-100 text-amber-700',
                                        'bulk_update' => 'bg-purple-100 text-purple-700',
                                        'system'      => 'bg-gray-100 text-gray-600',
                                    ];
                                    $typeVal   = $log->change_type->value ?? 'system';
                                    $typeColor = $typeColors[$typeVal] ?? 'bg-gray-100 text-gray-600';
                                    $typeLabel = ucwords(str_replace('_', ' ', $typeVal));
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeColor }}">
                                            {{ $typeLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                                        @if($log->old_status)
                                            <span class="text-green-700 font-medium">✓ In Stock</span>
                                        @else
                                            <span class="text-red-600 font-medium">✗ Out of Stock</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                                        @if($log->new_status)
                                            <span class="text-green-700 font-medium">✓ In Stock</span>
                                        @else
                                            <span class="text-red-600 font-medium">✗ Out of Stock</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">
                                        {{ $log->admin?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        {{ $log->note ?? '—' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">
                        No inventory changes recorded yet.
                    </div>
                @endif
            </div>

        </div>

        {{-- Right Column: Stats & Actions --}}
        <div class="space-y-8">
            {{-- Status Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <a href="{{ route('admin.catalog.products.edit', $product) }}" 
                           class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                            <x-heroicon-o-pencil class="w-4 h-4 mr-2" />
                            Edit Product
                        </a>
                        <button type="button" 
                                onclick="if(confirm('Are you sure you want to delete this product? This action cannot be undone.')) { document.getElementById('delete-form').submit(); }"
                                class="w-full flex items-center justify-center px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red transition-colors">
                            <x-heroicon-o-trash class="w-4 h-4 mr-2" />
                            Delete Product
                        </button>
                        <a href="{{ route('admin.catalog.products.index') }}" 
                           class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                            <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                            Back to List
                        </a>
                    </div>
                </div>
            </div>

            {{-- Metadata Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Metadata</h2>
                </div>
                <div class="p-6">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $product->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $product->updated_at->format('M d, Y H:i') }}</dd>
                        </div>
                        @if($product->deleted_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Deleted</dt>
                            <dd class="mt-1 text-sm text-red-600">{{ $product->deleted_at->format('M d, Y H:i') }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Cross References Card --}}
            @if($product->crossReferences->count() > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Cross References</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-2">
                        @foreach($product->crossReferences as $crossRef)
                        <div class="flex items-center justify-between p-2 bg-gray-50 border border-gray-200 rounded">
                            <span class="text-sm font-mono text-gray-900">{{ $crossRef->cross_oem_number }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Delete Form --}}
<form id="delete-form" action="{{ route('admin.catalog.products.destroy', $product) }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection