@extends('layouts.admin')

@section('title', 'Car Model — ' . $carModel->name)
@section('page_title', 'Car Model Details')

@section('header_actions')
    <a href="{{ route('admin.catalog.car-models.edit', $carModel) }}" class="bp-btn-outline gap-1">
        <x-heroicon-o-pencil-square class="w-4 h-4" />
        Edit
    </a>
    <a href="{{ route('admin.catalog.car-models.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header strip --}}
    <div class="bp-card p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="bp-spec text-ink-muted">§ Car Model</p>
            <h2 class="font-display text-2xl font-bold text-ink tracking-[-0.02em]">
                {{ $carModel->name }}<span class="text-amber">.</span>
            </h2>
            @if($carModel->slug)
                <p class="font-mono text-xs text-ink-muted mt-0.5">{{ $carModel->slug }}</p>
            @endif
        </div>
        @if($carModel->is_active)
            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
        @else
            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left: Info + Products --}}
        <div class="lg:col-span-2 space-y-6">

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Basic · Information</p>
                </header>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Model Name</p>
                        <p class="text-sm font-bold text-ink">{{ $carModel->name }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Slug</p>
                        <p class="font-mono text-sm text-ink">{{ $carModel->slug }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Manufacturer</p>
                        <a href="{{ route('admin.catalog.manufacturers.show', $carModel->manufacturer) }}"
                           class="text-sm text-amber-ink hover:underline font-bold">
                            {{ trans_field($carModel->manufacturer->name) }}
                        </a>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Year Range</p>
                        <p class="font-mono text-sm tabular-nums text-ink">
                            @if($carModel->year_from && $carModel->year_to)
                                {{ $carModel->year_from }} – {{ $carModel->year_to }}
                            @elseif($carModel->year_from)
                                {{ $carModel->year_from }} – present
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Sort Order</p>
                        <p class="font-mono text-sm text-ink">{{ $carModel->sort_order }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Created</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $carModel->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                </div>
            </section>

            {{-- Compatible Products --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header flex items-center justify-between gap-4">
                    <p class="bp-spec text-ink-muted">§ Compatible · Products</p>
                    <a href="{{ route('admin.catalog.products.index', ['car_model_id' => $carModel->id]) }}"
                       class="font-mono text-xs text-amber-ink hover:underline">
                        View all {{ $carModel->products->count() }}
                    </a>
                </header>
                @if($carModel->products->count() > 0)
                    <div class="divide-y divide-rule">
                        @foreach($carModel->products->take(8) as $product)
                            <a href="{{ route('admin.catalog.products.edit', $product) }}"
                               class="flex items-center justify-between px-5 py-3 hover:bg-ivory-alt transition-colors group">
                                <div>
                                    <span class="font-mono text-sm text-amber-ink tracking-wider group-hover:underline">{{ $product->oem_number }}</span>
                                    @if($product->name && ($product->name['en'] ?? null))
                                        <span class="ml-2 text-xs text-ink-muted">{{ $product->name['en'] }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($product->is_in_stock)
                                        <span class="font-mono text-[10px] font-bold text-emerald-600">IN STOCK</span>
                                    @else
                                        <span class="font-mono text-[10px] font-bold text-red-600">OUT</span>
                                    @endif
                                    <span class="font-mono text-xs tabular-nums text-ink">{{ format_money($product->price) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    @if($carModel->products->count() > 8)
                        <div class="px-5 py-3 border-t border-rule text-center">
                            <a href="{{ route('admin.catalog.products.index', ['car_model_id' => $carModel->id]) }}"
                               class="font-mono text-xs text-amber-ink hover:underline">
                                View {{ $carModel->products->count() - 8 }} more products
                            </a>
                        </div>
                    @endif
                @else
                    <div class="px-5 py-10 text-center">
                        <x-heroicon-o-inbox class="w-8 h-8 mx-auto text-ink/20 mb-2" />
                        <p class="text-sm text-ink-muted">No products associated yet.</p>
                    </div>
                @endif
            </section>

        </div>

        {{-- Right: Stats + Actions + Meta --}}
        <div class="space-y-6">

            <div class="grid grid-cols-3 gap-3 lg:grid-cols-1 lg:gap-4">
                <div class="bp-card p-4 text-center">
                    <p class="font-mono text-2xl font-bold tabular-nums text-ink">{{ $carModel->products->count() }}</p>
                    <p class="text-xs text-ink-muted mt-1">Total Products</p>
                </div>
                <div class="bp-card p-4 text-center">
                    <p class="font-mono text-2xl font-bold tabular-nums text-emerald-600">{{ $carModel->products->where('is_active', true)->count() }}</p>
                    <p class="text-xs text-ink-muted mt-1">Active</p>
                </div>
                <div class="bp-card p-4 text-center">
                    <p class="font-mono text-2xl font-bold tabular-nums text-blue-700">{{ $carModel->products->where('is_in_stock', true)->count() }}</p>
                    <p class="text-xs text-ink-muted mt-1">In Stock</p>
                </div>
            </div>

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Quick · Actions</p>
                </header>
                <div class="p-5 space-y-2">
                    <a href="{{ route('admin.catalog.car-models.edit', $carModel) }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                        Edit Car Model
                    </a>
                    <a href="{{ route('admin.catalog.products.index', ['car_model_id' => $carModel->id]) }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-cube class="w-4 h-4" />
                        View All Products
                    </a>
                    <button type="button"
                            onclick="navigator.clipboard.writeText('{{ $carModel->slug }}').then(() => { this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy Slug', 2000); })"
                            class="bp-btn-ghost w-full justify-center gap-1">
                        <x-heroicon-o-clipboard-document class="w-4 h-4" />
                        Copy Slug
                    </button>
                    <button type="button"
                            onclick="if(confirm('Delete this car model?')) { document.getElementById('delete-form').submit(); }"
                            class="bp-btn-ghost text-red-600 hover:text-red-700 w-full justify-center gap-1">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete Car Model
                    </button>
                </div>
            </section>

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Metadata</p>
                </header>
                <div class="p-5 space-y-3">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Created</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $carModel->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <div class="border-t border-rule pt-3">
                        <p class="bp-spec text-ink-muted mb-1">§ Updated</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $carModel->updated_at->format('Y-m-d H:i') }}</p>
                    </div>
                </div>
            </section>

        </div>
    </div>

</div>

<form id="delete-form" action="{{ route('admin.catalog.car-models.destroy', $carModel) }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection
