@extends('layouts.admin')

@section('title', 'Product — ' . $product->oem_number)
@section('page_title', 'Product Details')

@section('header_actions')
    <a href="{{ route('admin.catalog.products.edit', $product) }}" class="bp-btn-outline gap-1">
        <x-heroicon-o-pencil-square class="w-4 h-4" />
        Edit
    </a>
    <a href="{{ route('admin.catalog.products.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header strip --}}
    <div class="bp-card p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="bp-spec text-ink-muted">§ Product</p>
            <h2 class="font-mono text-2xl font-bold text-amber-ink tracking-wider">
                {{ $product->oem_number }}
            </h2>
            @if($product->name && ($product->name['en'] ?? null))
                <p class="text-sm text-ink mt-0.5">{{ $product->name['en'] }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @php
                $conditionClasses = [
                    'new'            => 'border-green-600/30 bg-green-50 text-green-700',
                    'used_grade_a'   => 'border-blue-600/30 bg-blue-50 text-blue-700',
                    'used_grade_b'   => 'border-amber/30 bg-amber-50 text-amber-700',
                    'used_grade_c'   => 'border-rule bg-ivory-alt text-ink-muted',
                    'remanufactured' => 'border-purple-600/30 bg-purple-50 text-purple-700',
                    'aftermarket'    => 'border-red-600/30 bg-red-50 text-red-700',
                    'new_old_stock'  => 'border-teal-600/30 bg-teal-50 text-teal-700',
                ];
                $condClass = $conditionClasses[$product->condition?->value ?? ''] ?? 'border-rule bg-ivory-alt text-ink-muted';
            @endphp
            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider {{ $condClass }}">
                {{ $product->condition?->label() ?? '—' }}
            </span>
            @if($product->is_active)
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
            @else
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
            @endif
            @if($product->is_in_stock)
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-emerald-600/30 bg-emerald-50 text-emerald-700">In Stock</span>
            @else
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-red-600/30 bg-red-50 text-red-700">Out of Stock</span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left: Details --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Basic Information --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Basic · Information</p>
                </header>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ OEM Number</p>
                        <p class="font-mono text-sm font-bold text-amber-ink tracking-wider">{{ $product->oem_number }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Normalized OEM</p>
                        <p class="font-mono text-sm text-ink">{{ $product->normalized_oem }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Manufacturer</p>
                        <a href="{{ route('admin.catalog.manufacturers.show', $product->manufacturer) }}"
                           class="text-sm text-amber-ink hover:underline font-bold">
                            {{ trans_field($product->manufacturer->name) }}
                        </a>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Condition</p>
                        <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider {{ $condClass }}">
                            {{ $product->condition?->label() ?? '—' }}
                        </span>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Price (ex. VAT)</p>
                        <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_money($product->price) }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Delivery Time</p>
                        <p class="text-sm text-ink">{{ $product->delivery_time ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Min. Order Qty</p>
                        <p class="font-mono text-sm tabular-nums text-ink">{{ $product->moq }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Created</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $product->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                </div>
            </section>

            {{-- Multilingual Content --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Multilingual · Content</p>
                </header>
                <div class="p-5 space-y-4">
                    <div>
                        <p class="bp-spec text-ink-muted mb-2">§ Name (JSON)</p>
                        <div class="bg-ivory-alt border border-rule p-3">
                            <pre class="font-mono text-xs text-ink whitespace-pre-wrap">{{ json_encode($product->name, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                    @if($product->description)
                    <div>
                        <p class="bp-spec text-ink-muted mb-2">§ Description (JSON)</p>
                        <div class="bg-ivory-alt border border-rule p-3">
                            <pre class="font-mono text-xs text-ink whitespace-pre-wrap">{{ json_encode($product->description, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                    @endif
                </div>
            </section>

            {{-- Compatible Car Models --}}
            @if($product->carModels->count() > 0)
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header flex items-center justify-between gap-4">
                    <p class="bp-spec text-ink-muted">§ Compatible · Car Models</p>
                    <span class="font-mono text-xs text-ink-muted">{{ $product->carModels->count() }} models</span>
                </header>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($product->carModels as $carModel)
                    <div class="flex items-center justify-between p-3 bg-ivory-alt border border-rule">
                        <div>
                            <p class="text-sm font-bold text-ink">{{ trans_field($carModel->manufacturer->name) }} {{ $carModel->name }}</p>
                            @if($carModel->year_from)
                                <p class="font-mono text-xs text-ink-muted tabular-nums mt-0.5">{{ $carModel->year_from }}–{{ $carModel->year_to ?? 'present' }}</p>
                            @endif
                        </div>
                        <a href="{{ route('admin.catalog.car-models.show', $carModel) }}"
                           class="text-amber-ink hover:text-ink ml-2 flex-shrink-0">
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        </a>
                    </div>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- Inventory History --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Inventory · History</p>
                </header>
                @if($product->inventoryLogs->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="bp-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Change Type</th>
                                    <th>Old Status</th>
                                    <th>New Status</th>
                                    <th>Admin</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($product->inventoryLogs as $log)
                                @php
                                    $typeVal = $log->change_type->value ?? 'system';
                                    $typeBadge = match($typeVal) {
                                        'csv_import'  => 'border-blue-600/30 bg-blue-50 text-blue-700',
                                        'manual'      => 'border-amber/30 bg-amber-50 text-amber-700',
                                        'bulk_update' => 'border-purple-600/30 bg-purple-50 text-purple-700',
                                        default       => 'border-rule bg-ivory-alt text-ink-muted',
                                    };
                                    $typeLabel = ucwords(str_replace('_', ' ', $typeVal));
                                @endphp
                                <tr>
                                    <td>
                                        <p class="font-mono text-xs tabular-nums text-ink whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i') }}
                                        </p>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider {{ $typeBadge }}">
                                            {{ $typeLabel }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->old_status)
                                            <span class="font-mono text-[10px] font-bold text-emerald-600">IN STOCK</span>
                                        @else
                                            <span class="font-mono text-[10px] font-bold text-red-600">OUT</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->new_status)
                                            <span class="font-mono text-[10px] font-bold text-emerald-600">IN STOCK</span>
                                        @else
                                            <span class="font-mono text-[10px] font-bold text-red-600">OUT</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-ink">{{ $log->admin?->name ?? '—' }}</td>
                                    <td class="text-xs text-ink-muted">{{ $log->note ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-10 text-center">
                        <x-heroicon-o-clock class="w-8 h-8 mx-auto text-ink/20 mb-2" />
                        <p class="text-sm text-ink-muted">No inventory changes recorded yet.</p>
                    </div>
                @endif
            </section>

        </div>

        {{-- Right: Actions + Meta + Cross Refs --}}
        <div class="space-y-6">

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Quick · Actions</p>
                </header>
                <div class="p-5 space-y-2">
                    <a href="{{ route('admin.catalog.products.edit', $product) }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                        Edit Product
                    </a>
                    <a href="{{ route('admin.catalog.products.index') }}"
                       class="bp-btn-ghost w-full justify-center gap-1">
                        <x-heroicon-o-arrow-left class="w-4 h-4" />
                        Back to Products
                    </a>
                    <button type="button"
                            onclick="navigator.clipboard.writeText('{{ $product->oem_number }}').then(() => { this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy OEM', 2000); })"
                            class="bp-btn-ghost w-full justify-center gap-1">
                        <x-heroicon-o-clipboard-document class="w-4 h-4" />
                        Copy OEM
                    </button>
                    <button type="button"
                            onclick="if(confirm('Delete this product?')) { document.getElementById('delete-form').submit(); }"
                            class="bp-btn-ghost text-red-600 hover:text-red-700 w-full justify-center gap-1">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        Delete Product
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
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $product->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <div class="border-t border-rule pt-3">
                        <p class="bp-spec text-ink-muted mb-1">§ Updated</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $product->updated_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @if($product->deleted_at)
                    <div class="border-t border-rule pt-3">
                        <p class="bp-spec text-red-600 mb-1">§ Deleted</p>
                        <p class="font-mono text-xs tabular-nums text-red-600">{{ $product->deleted_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                </div>
            </section>

            @if($product->crossReferences->count() > 0)
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Cross · References</p>
                </header>
                <div class="p-5">
                    <div class="flex flex-wrap gap-2">
                        @foreach($product->crossReferences as $crossRef)
                            <span class="inline-flex items-center border border-rule bg-ivory-alt px-2 py-0.5 font-mono text-[11px] text-amber-ink tracking-wider">
                                {{ $crossRef->cross_oem_number }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </section>
            @endif

        </div>
    </div>

</div>

<form id="delete-form" action="{{ route('admin.catalog.products.destroy', $product) }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection
