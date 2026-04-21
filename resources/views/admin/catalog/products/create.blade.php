@extends('layouts.admin')

@section('title', 'Create Product')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Product</h1>
                <p class="text-gray-600 mt-1">Add a new product to your catalog.</p>
            </div>
            <a href="{{ route('admin.catalog.products.index') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to Products
            </a>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <form action="{{ route('admin.catalog.products.store') }}" method="POST">
            @csrf

            <div class="p-6 space-y-8">

                {{-- Basic Information --}}
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-l-4 border-amber pl-3">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        {{-- OEM Number --}}
                        <div>
                            <label for="oem_number" class="block text-sm font-medium text-gray-700 mb-1">
                                OEM Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   name="oem_number"
                                   id="oem_number"
                                   value="{{ old('oem_number') }}"
                                   required
                                   inputmode="text"
                                   autocapitalize="characters"
                                   placeholder="e.g. 0 986 479 084"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            @error('oem_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Manufacturer --}}
                        <div>
                            <label for="manufacturer_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Manufacturer <span class="text-red-500">*</span>
                            </label>
                            <select name="manufacturer_id"
                                    id="manufacturer_id"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                                <option value="">Select Manufacturer</option>
                                @foreach($manufacturers as $manufacturer)
                                    <option value="{{ $manufacturer->id }}" {{ old('manufacturer_id') == $manufacturer->id ? 'selected' : '' }}>
                                        {{ trans_field($manufacturer->name) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('manufacturer_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Condition --}}
                        <div>
                            <label for="condition" class="block text-sm font-medium text-gray-700 mb-1">
                                Condition <span class="text-red-500">*</span>
                            </label>
                            <select name="condition"
                                    id="condition"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                                <option value="">Select Condition</option>
                                @foreach($conditions as $condition)
                                    <option value="{{ $condition->value }}" {{ old('condition') === $condition->value ? 'selected' : '' }}>
                                        {{ $condition->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('condition')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Price --}}
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700 mb-1">
                                Price (ex. VAT) <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500">€</span>
                                </div>
                                <input type="text"
                                       name="price"
                                       id="price"
                                       value="{{ old('price') }}"
                                       inputmode="decimal"
                                       required
                                       placeholder="0.00"
                                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            </div>
                            @error('price')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Delivery Time --}}
                        <div>
                            <label for="delivery_time" class="block text-sm font-medium text-gray-700 mb-1">
                                Delivery Time
                            </label>
                            <input type="text"
                                   name="delivery_time"
                                   id="delivery_time"
                                   value="{{ old('delivery_time') }}"
                                   placeholder="e.g. 3-5 days"
                                   maxlength="50"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            @error('delivery_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- MOQ --}}
                        <div>
                            <label for="moq" class="block text-sm font-medium text-gray-700 mb-1">
                                Minimum Order Quantity
                            </label>
                            <input type="number"
                                   name="moq"
                                   id="moq"
                                   value="{{ old('moq', 1) }}"
                                   min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            @error('moq')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- Multilingual Name & Description --}}
                <div x-data="{ activeTab: '{{ old('_active_lang', 'en') }}' }">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1 border-l-4 border-amber pl-3">Multilingual Content</h2>
                    <p class="text-sm text-gray-500 mb-4">English is required. Other languages are optional and fall back to English when missing.</p>

                    {{-- Language tabs --}}
                    <div class="flex gap-1 border-b border-gray-200 mb-5">
                        @php
                            $langs = ['en' => 'English', 'de' => 'Deutsch', 'lt' => 'Lietuvių', 'fr' => 'Français', 'es' => 'Español'];
                            $namePlaceholders = [
                                'en' => 'e.g. Brake Disc Front Vented, Alternator 120A, Water Pump Kit',
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
                        @foreach($langs as $code => $label)
                        <button type="button"
                                @click="activeTab = '{{ $code }}'"
                                :class="activeTab === '{{ $code }}'
                                    ? 'border-b-2 border-navy text-navy font-semibold'
                                    : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm transition-colors">
                            {{ $label }}@if($code === 'en')<span class="text-red-500 ml-0.5">*</span>@endif
                        </button>
                        @endforeach
                    </div>

                    @foreach(array_keys($langs) as $code)
                    <div x-show="activeTab === '{{ $code }}'" class="space-y-4">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Name
                                @if($code === 'en') <span class="text-red-500">*</span> @endif
                            </label>
                            <input type="text"
                                   name="name[{{ $code }}]"
                                   value="{{ old('name.' . $code) }}"
                                   {{ $code === 'en' ? 'required' : '' }}
                                   maxlength="255"
                                   placeholder="{{ $namePlaceholders[$code] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            @error('name.' . $code)
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description[{{ $code }}]"
                                      rows="4"
                                      placeholder="{{ $descPlaceholders[$code] ?? '' }}"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">{{ old('description.' . $code) }}</textarea>
                            @error('description.' . $code)
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                    @endforeach
                </div>

                {{-- Inventory & Status --}}
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-l-4 border-amber pl-3">Status</h2>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox"
                                   name="is_active"
                                   id="is_active"
                                   value="1"
                                   {{ old('is_active', true) ? 'checked' : '' }}
                                   class="h-4 w-4 text-amber focus:ring-amber border-gray-300 rounded">
                            <label for="is_active" class="ml-2 text-sm text-gray-700">
                                Product is active and visible to customers
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox"
                                   name="is_in_stock"
                                   id="is_in_stock"
                                   value="1"
                                   {{ old('is_in_stock', true) ? 'checked' : '' }}
                                   class="h-4 w-4 text-amber focus:ring-amber border-gray-300 rounded">
                            <label for="is_in_stock" class="ml-2 text-sm text-gray-700">
                                Product is in stock
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Compatible Car Models --}}
                <div x-data="{ search: '' }">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1 border-l-4 border-amber pl-3">Compatible Car Models</h2>
                    <p class="text-sm text-gray-500 mb-4">Select all car models this part fits. Leave empty if unknown.</p>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg overflow-hidden">
                        <div class="p-3 border-b border-gray-200">
                            <input type="text"
                                   x-model="search"
                                   placeholder="Search by make or model, e.g. BMW 3 Series..."
                                   class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber">
                        </div>
                        <div class="p-3 max-h-64 overflow-y-auto space-y-1.5">
                            @foreach($carModels as $carModel)
                            <div x-show="search === '' || '{{ strtolower(trans_field($carModel->manufacturer->name) . ' ' . $carModel->name) }}'.includes(search.toLowerCase())">
                                <label class="flex items-center gap-2 cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                                    <input type="checkbox"
                                           name="car_model_ids[]"
                                           value="{{ $carModel->id }}"
                                           class="h-4 w-4 text-amber focus:ring-amber border-gray-300 rounded">
                                    <span class="text-sm text-gray-700">
                                        {{ trans_field($carModel->manufacturer->name) }} {{ $carModel->name }}
                                        @if($carModel->year_from)
                                            <span class="text-gray-400">({{ $carModel->year_from }}–{{ $carModel->year_to ?? 'present' }})</span>
                                        @endif
                                    </span>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>

                {{-- Cross References --}}
                <div x-data="{
                    tags: [],
                    input: '',
                    addTag() {
                        const val = this.input.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
                        if (val && !this.tags.includes(val)) { this.tags.push(val); }
                        this.input = '';
                    },
                    removeTag(tag) { this.tags = this.tags.filter(t => t !== tag); }
                }">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1 border-l-4 border-amber pl-3">Cross References</h2>
                    <p class="text-sm text-gray-500 mb-4">Alternative OEM numbers that refer to the same part (e.g. aftermarket equivalents). Leave empty if none.</p>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-3">
                        {{-- Tag input row --}}
                        <div class="flex gap-2">
                            <input type="text"
                                   x-model="input"
                                   @keydown.enter.prevent="addTag()"
                                   inputmode="text"
                                   autocapitalize="characters"
                                   placeholder="e.g. 0242229799"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            <button type="button"
                                    @click="addTag()"
                                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                                Add
                            </button>
                        </div>

                        {{-- Chips --}}
                        <div class="flex flex-wrap gap-2 min-h-[2rem]">
                            <template x-for="tag in tags" :key="tag">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-navy/10 text-navy text-xs font-mono rounded-full">
                                    <span x-text="tag"></span>
                                    <button type="button" @click="removeTag(tag)"
                                            class="ml-1 text-navy/60 hover:text-navy font-bold leading-none">×</button>
                                    <input type="hidden" :name="'cross_references[]'" :value="tag">
                                </span>
                            </template>
                            <span x-show="tags.length === 0" class="text-xs text-gray-400 italic">No cross-references added yet</span>
                        </div>
                    </div>
                </div>

            {{-- Form Actions --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                <a href="{{ route('admin.catalog.products.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-amber hover:bg-amber/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                    Create Product
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
