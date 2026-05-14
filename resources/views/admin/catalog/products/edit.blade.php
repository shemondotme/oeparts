@extends('layouts.admin')

@section('title', 'Edit Product — ' . $product->oem_number)
@section('page_title', 'Edit Product')

@section('header_actions')
    <a href="{{ route('admin.catalog.products.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to Products
    </a>
@endsection

@section('content')
<div class="max-w-4xl space-y-6">

    <section class="bp-card overflow-hidden">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Catalog · Edit Product</p>
            <h2 class="mt-1 font-mono text-xl font-bold text-amber-ink tracking-wider">
                {{ $product->oem_number }}<span class="text-ink">.</span>
            </h2>
        </header>

        <form action="{{ route('admin.catalog.products.update', $product) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="divide-y divide-rule">

                {{-- Basic Information --}}
                <div class="p-5 space-y-4">
                    <p class="bp-spec text-ink-muted">§ Basic · Information</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>
                            <label for="oem_number" class="block bp-spec mb-2">§ OEM Number <span class="text-red-500">*</span></label>
                            <input type="text" name="oem_number" id="oem_number"
                                   value="{{ old('oem_number', $product->oem_number) }}"
                                   required inputmode="text" autocapitalize="characters"
                                   placeholder="e.g. 0 986 479 084"
                                   class="bp-input-mono w-full">
                            @error('oem_number')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="manufacturer_id" class="block bp-spec mb-2">§ Manufacturer <span class="text-red-500">*</span></label>
                            <select name="manufacturer_id" id="manufacturer_id" required class="bp-select">
                                <option value="">Select Manufacturer</option>
                                @foreach($manufacturers as $manufacturer)
                                    <option value="{{ $manufacturer->id }}" {{ old('manufacturer_id', $product->manufacturer_id) == $manufacturer->id ? 'selected' : '' }}>
                                        {{ trans_field($manufacturer->name) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('manufacturer_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="condition" class="block bp-spec mb-2">§ Condition <span class="text-red-500">*</span></label>
                            <select name="condition" id="condition" required class="bp-select">
                                <option value="">Select Condition</option>
                                @foreach($conditions as $condition)
                                    <option value="{{ $condition->value }}" {{ old('condition', $product->condition?->value) === $condition->value ? 'selected' : '' }}>
                                        {{ $condition->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('condition')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="price" class="block bp-spec mb-2">§ Unit Price (ex. VAT) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="font-mono text-xs text-ink-muted">€</span>
                                </div>
                                <input type="text" name="price" id="price"
                                       value="{{ old('price', $product->price) }}"
                                       inputmode="decimal" required placeholder="0.00"
                                       class="bp-input-mono w-full pl-8">
                            </div>
                            @error('price')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="delivery_time" class="block bp-spec mb-2">§ Delivery Time</label>
                            <input type="text" name="delivery_time" id="delivery_time"
                                   value="{{ old('delivery_time', $product->delivery_time) }}"
                                   placeholder="e.g. 3–5 business days" maxlength="50"
                                   class="bp-input w-full">
                            @error('delivery_time')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="moq" class="block bp-spec mb-2">§ Min. Order Qty (MOQ)</label>
                            <input type="number" name="moq" id="moq"
                                   value="{{ old('moq', $product->moq) }}" min="1"
                                   class="bp-input w-full">
                            @error('moq')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- Multilingual Content --}}
                <div class="p-5 space-y-4" x-data="{ activeTab: '{{ old('_active_lang', 'en') }}' }">
                    <div>
                        <p class="bp-spec text-ink-muted">§ Multilingual · Content</p>
                        <p class="font-mono text-xs text-ink-muted mt-1">English is required. Other languages fall back to English when missing.</p>
                    </div>

                    {{-- Language tabs --}}
                    @php
                        $langs = ['en' => 'English', 'de' => 'Deutsch', 'lt' => 'Lietuvių', 'fr' => 'Français', 'es' => 'Español'];
                        $namePlaceholders = [
                            'en' => 'e.g. Brake Disc Front Vented, Alternator 120A',
                            'de' => 'z. B. Bremsscheibe vorne belüftet, Lichtmaschine 120A',
                            'lt' => 'pvz. Priekinė stabdžių diskas, Generatorius 120A',
                            'fr' => 'ex. Disque de frein avant ventilé, Alternateur 120A',
                            'es' => 'ej. Disco de freno delantero ventilado, Alternador 120A',
                        ];
                        $descPlaceholders = [
                            'en' => 'Describe the part\'s application, key specifications, and compatible vehicles...',
                            'de' => 'Anwendung, Spezifikationen und kompatible Fahrzeuge beschreiben...',
                            'lt' => 'Aprašykite dalies paskirtį, specifikacijas ir suderinamus automobilius...',
                            'fr' => 'Décrivez l\'application, les spécifications et les véhicules compatibles...',
                            'es' => 'Describa la aplicación, especificaciones y vehículos compatibles...',
                        ];
                    @endphp

                    <div class="flex gap-0 border-b border-rule">
                        @foreach($langs as $code => $label)
                        <button type="button"
                                @click="activeTab = '{{ $code }}'"
                                :class="activeTab === '{{ $code }}'
                                    ? 'border-b-2 border-amber text-amber-ink font-bold'
                                    : 'text-ink-muted hover:text-ink'"
                                class="px-4 py-2 font-mono text-xs uppercase tracking-wider transition-colors -mb-px">
                            {{ $label }}@if($code === 'en')<span class="text-red-500 ml-0.5">*</span>@endif
                        </button>
                        @endforeach
                    </div>

                    @foreach(array_keys($langs) as $code)
                    <div x-show="activeTab === '{{ $code }}'" class="space-y-4">
                        <div>
                            <label class="block bp-spec mb-2">
                                § Name @if($code === 'en')<span class="text-red-500">*</span>@endif
                            </label>
                            <input type="text"
                                   name="name[{{ $code }}]"
                                   value="{{ old('name.' . $code, $product->name[$code] ?? '') }}"
                                   {{ $code === 'en' ? 'required' : '' }}
                                   maxlength="255"
                                   placeholder="{{ $namePlaceholders[$code] ?? '' }}"
                                   class="bp-input w-full">
                            @error('name.' . $code)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block bp-spec mb-2">§ Description</label>
                            <textarea name="description[{{ $code }}]" rows="4"
                                      placeholder="{{ $descPlaceholders[$code] ?? '' }}"
                                      class="bp-input w-full resize-y">{{ old('description.' . $code, $product->description[$code] ?? '') }}</textarea>
                            @error('description.' . $code)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Status --}}
                <div class="p-5 space-y-3">
                    <p class="bp-spec text-ink-muted">§ Status</p>
                    <label class="flex items-center gap-2 text-sm text-ink cursor-pointer">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               {{ old('is_active', $product->is_active) ? 'checked' : '' }}
                               class="rounded-none border-rule">
                        Active — visible to customers
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink cursor-pointer">
                        <input type="checkbox" name="is_in_stock" id="is_in_stock" value="1"
                               {{ old('is_in_stock', $product->is_in_stock) ? 'checked' : '' }}
                               class="rounded-none border-rule">
                        In Stock
                    </label>
                </div>

                {{-- Compatible Car Models --}}
                <div class="p-5 space-y-4" x-data="{ search: '' }">
                    <div>
                        <p class="bp-spec text-ink-muted">§ Compatible · Car Models</p>
                        <p class="font-mono text-xs text-ink-muted mt-1">Select all car models this part fits. Leave empty if unknown.</p>
                    </div>

                    @php $selectedIds = $product->carModels->pluck('id')->toArray(); @endphp

                    <div class="border border-rule overflow-hidden">
                        <div class="p-3 border-b border-rule bg-ivory-alt">
                            <input type="text"
                                   x-model="search"
                                   placeholder="Search by make or model, e.g. BMW 3 Series..."
                                   class="bp-input w-full text-sm">
                        </div>
                        <div class="bg-ivory-alt p-3 max-h-64 overflow-y-auto space-y-1">
                            @foreach($carModels as $carModel)
                            <div x-show="search === '' || '{{ strtolower(trans_field($carModel->manufacturer->name) . ' ' . $carModel->name) }}'.includes(search.toLowerCase())">
                                <label class="flex items-center gap-2 cursor-pointer hover:bg-paper px-2 py-1.5 transition-colors">
                                    <input type="checkbox"
                                           name="car_model_ids[]"
                                           value="{{ $carModel->id }}"
                                           {{ in_array($carModel->id, $selectedIds) ? 'checked' : '' }}
                                           class="rounded-none border-rule flex-shrink-0">
                                    <span class="text-sm text-ink">
                                        <span class="font-bold">{{ trans_field($carModel->manufacturer->name) }}</span>
                                        {{ $carModel->name }}
                                        @if($carModel->year_from)
                                            <span class="font-mono text-xs text-ink-muted tabular-nums">({{ $carModel->year_from }}–{{ $carModel->year_to ?? 'present' }})</span>
                                        @endif
                                    </span>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Cross References --}}
                <div class="p-5 space-y-4" x-data="{
                    tags: {{ json_encode($product->crossReferences->pluck('cross_oem_number')->toArray()) }},
                    input: '',
                    addTag() {
                        const val = this.input.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
                        if (val && !this.tags.includes(val)) { this.tags.push(val); }
                        this.input = '';
                    },
                    removeTag(tag) { this.tags = this.tags.filter(t => t !== tag); }
                }">
                    <div>
                        <p class="bp-spec text-ink-muted">§ Cross · References</p>
                        <p class="font-mono text-xs text-ink-muted mt-1">Alternative OEM numbers referring to the same part. Leave empty if none.</p>
                    </div>

                    <div class="border border-rule bg-ivory-alt p-4 space-y-3">
                        <div class="flex gap-2">
                            <input type="text"
                                   x-model="input"
                                   @keydown.enter.prevent="addTag()"
                                   inputmode="text" autocapitalize="characters"
                                   placeholder="e.g. 0242229799"
                                   class="bp-input-mono flex-1">
                            <button type="button" @click="addTag()" class="bp-btn-ghost">Add</button>
                        </div>
                        <div class="flex flex-wrap gap-2 min-h-[2rem]">
                            <template x-for="tag in tags" :key="tag">
                                <span class="inline-flex items-center gap-1 border border-rule bg-paper px-2 py-0.5 font-mono text-[11px] text-amber-ink tracking-wider">
                                    <span x-text="tag"></span>
                                    <button type="button" @click="removeTag(tag)"
                                            class="text-ink-muted hover:text-ink font-bold leading-none ml-1">×</button>
                                    <input type="hidden" :name="'cross_references[]'" :value="tag">
                                </span>
                            </template>
                            <span x-show="tags.length === 0" class="font-mono text-xs text-ink-muted italic">No cross-references added yet</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="px-5 py-4 bg-ivory-alt border-t border-rule flex items-center justify-between">
                <button type="button"
                        onclick="if(confirm('Delete this product? This cannot be undone.')) { document.getElementById('delete-form').submit(); }"
                        class="bp-btn-ghost text-red-600 hover:text-red-700 gap-1">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete
                </button>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.catalog.products.index') }}" class="bp-btn-ghost">Cancel</a>
                    <button type="submit" class="bp-btn-primary">Update Product</button>
                </div>
            </div>
        </form>

        <form id="delete-form" action="{{ route('admin.catalog.products.destroy', $product) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </section>

</div>
@endsection
