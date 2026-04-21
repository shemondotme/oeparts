@extends('layouts.admin')

@section('title', 'Car Model — ' . $carModel->name)

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $carModel->name }}</h1>
                <p class="text-gray-500 mt-1 font-mono text-sm">{{ $carModel->slug }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.catalog.car-models.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    Back to Car Models
                </a>
                <a href="{{ route('admin.catalog.car-models.edit', $carModel) }}"
                   class="inline-flex items-center px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-navy transition-colors">
                    <x-heroicon-o-pencil-square class="w-4 h-4 mr-2" />
                    Edit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Left Column --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Basic Information --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Basic Information</h2>
                    @if($carModel->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                    @endif
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Model Name</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ $carModel->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Slug</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-700">{{ $carModel->slug }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Manufacturer</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('admin.catalog.manufacturers.show', $carModel->manufacturer) }}"
                                   class="text-amber-text hover:underline font-medium">
                                    {{ trans_field($carModel->manufacturer->name) }}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Year Range</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($carModel->year_from && $carModel->year_to)
                                    {{ $carModel->year_from }} – {{ $carModel->year_to }}
                                @elseif($carModel->year_from)
                                    {{ $carModel->year_from }} – present
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Sort Order</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $carModel->sort_order }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $carModel->created_at->format('d M Y, H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Compatible Products --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Compatible Products</h2>
                    <a href="{{ route('admin.catalog.products.index', ['car_model_id' => $carModel->id]) }}"
                       class="text-sm text-amber-text hover:underline font-medium">
                        View all {{ $carModel->products->count() }}
                    </a>
                </div>
                @if($carModel->products->count() > 0)
                    <div class="divide-y divide-gray-100">
                        @foreach($carModel->products->take(8) as $product)
                            <a href="{{ route('admin.catalog.products.edit', $product) }}"
                               class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors group">
                                <div>
                                    <span class="font-mono text-sm text-gray-900 group-hover:text-navy">{{ $product->oem_number }}</span>
                                    @if($product->name && ($product->name['en'] ?? null))
                                        <span class="ml-2 text-sm text-gray-500">{{ $product->name['en'] }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($product->is_in_stock)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">In Stock</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Out of Stock</span>
                                    @endif
                                    @if(!$product->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                                    @endif
                                    <span class="text-sm font-medium text-gray-700">{{ format_money($product->price) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    @if($carModel->products->count() > 8)
                        <div class="px-6 py-3 border-t border-gray-100 text-center">
                            <a href="{{ route('admin.catalog.products.index', ['car_model_id' => $carModel->id]) }}"
                               class="text-sm text-amber-text hover:underline">
                                View {{ $carModel->products->count() - 8 }} more products
                            </a>
                        </div>
                    @endif
                @else
                    <div class="px-6 py-10 text-center text-gray-500">
                        <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                        <p class="text-sm">No products associated with this car model yet.</p>
                    </div>
                @endif
            </div>

        </div>

        {{-- Right Column --}}
        <div class="space-y-6">

            {{-- Statistics --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Statistics</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total Products</span>
                        <span class="text-sm font-semibold text-gray-900">{{ $carModel->products->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Active Products</span>
                        <span class="text-sm font-semibold text-green-700">{{ $carModel->products->where('is_active', true)->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">In Stock</span>
                        <span class="text-sm font-semibold text-blue-700">{{ $carModel->products->where('is_in_stock', true)->count() }}</span>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Quick Actions</h2>
                </div>
                <div class="p-6 space-y-3">
                    <a href="{{ route('admin.catalog.car-models.edit', $carModel) }}"
                       class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <x-heroicon-o-pencil-square class="w-4 h-4 mr-2" />
                        Edit Car Model
                    </a>
                    <a href="{{ route('admin.catalog.products.index', ['car_model_id' => $carModel->id]) }}"
                       class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <x-heroicon-o-cube class="w-4 h-4 mr-2" />
                        View All Products
                    </a>
                    <button type="button"
                            id="copy-slug-btn"
                            onclick="navigator.clipboard.writeText('{{ $carModel->slug }}').then(() => { this.textContent = 'Copied!'; setTimeout(() => { this.innerHTML = '<svg xmlns=\'http://www.w3.org/2000/svg\' class=\'w-4 h-4 mr-2 inline\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184\'/></svg> Copy Slug'; }, 2000); })"
                            class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <x-heroicon-o-clipboard-document class="w-4 h-4 mr-2" />
                        Copy Slug
                    </button>
                    <button type="button"
                            onclick="if(confirm('Delete this car model? This action cannot be undone.')) { document.getElementById('delete-form').submit(); }"
                            class="w-full flex items-center justify-center px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50 transition-colors">
                        <x-heroicon-o-trash class="w-4 h-4 mr-2" />
                        Delete Car Model
                    </button>
                </div>
            </div>

            {{-- Metadata --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Metadata</h2>
                </div>
                <div class="p-6">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $carModel->created_at->format('d M Y, H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $carModel->updated_at->format('d M Y, H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Delete Form --}}
<form id="delete-form" action="{{ route('admin.catalog.car-models.destroy', $carModel) }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection
