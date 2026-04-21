@extends('layouts.admin')

@section('title', 'Create Car Model')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Car Model</h1>
                <p class="text-gray-600 mt-1">Add a new car model to your catalog.</p>
            </div>
            <a href="{{ route('admin.catalog.car-models.index') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to Car Models
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <form action="{{ route('admin.catalog.car-models.store') }}" method="POST">
            @csrf

            <div class="p-6 space-y-8">

                {{-- Details --}}
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-l-4 border-amber pl-3">Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

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

                    {{-- Model Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            Model Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name') }}"
                               required
                               placeholder="e.g. Golf VII, E90 3 Series, Audi A4 B8"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Year From --}}
                    <div>
                        <label for="year_from" class="block text-sm font-medium text-gray-700 mb-1">Year From</label>
                        <input type="number"
                               name="year_from"
                               id="year_from"
                               value="{{ old('year_from') }}"
                               min="1900"
                               max="2100"
                               placeholder="e.g. 2012"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                        @error('year_from')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Year To --}}
                    <div>
                        <label for="year_to" class="block text-sm font-medium text-gray-700 mb-1">
                            Year To
                            <span class="text-xs text-gray-400 ml-1">Leave empty if still in production</span>
                        </label>
                        <input type="number"
                               name="year_to"
                               id="year_to"
                               value="{{ old('year_to') }}"
                               min="1900"
                               max="2100"
                               placeholder="e.g. 2020"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                        @error('year_to')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Sort Order --}}
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">
                            Sort Order
                            <span class="text-xs text-gray-400 ml-1">Higher = shown first</span>
                        </label>
                        <input type="number"
                               name="sort_order"
                               id="sort_order"
                               value="{{ old('sort_order', 0) }}"
                               min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                        @error('sort_order')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-l-4 border-amber pl-3">Status</h2>
                    <div class="flex items-center">
                        <input type="checkbox"
                               name="is_active"
                               id="is_active"
                               value="1"
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="h-4 w-4 text-amber focus:ring-amber border-gray-300 rounded">
                        <label for="is_active" class="ml-2 text-sm text-gray-700">
                            Active — visible to customers
                        </label>
                    </div>
                </div>

            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                <a href="{{ route('admin.catalog.car-models.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-amber hover:bg-amber/90 transition-colors">
                    Create Car Model
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
