@extends('layouts.admin')

@section('title', 'Edit Manufacturer')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Manufacturer</h1>
                <p class="text-gray-600 mt-1">{{ trans_field($manufacturer->name) }}</p>
            </div>
            <a href="{{ route('admin.catalog.manufacturers.index') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to Manufacturers
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <form action="{{ route('admin.catalog.manufacturers.update', $manufacturer) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="p-6 space-y-8">

                {{-- Brand Name --}}
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-l-4 border-amber pl-3">Brand Name</h2>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', trans_field($manufacturer->name, 'en')) }}"
                               required
                               maxlength="255"
                               placeholder="e.g. Bosch"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Details --}}
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-l-4 border-amber pl-3">Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        {{-- Country of Origin --}}
                        <div>
                            <label for="country_code" class="block text-sm font-medium text-gray-700 mb-1">Country of Origin</label>
                            <select name="country_code"
                                    id="country_code"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                                <option value="">Select Country</option>
                                @foreach($countries as $code => $name)
                                    <option value="{{ $code }}" {{ old('country_code', $manufacturer->country_code) == $code ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Slug --}}
                        <div>
                            <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">
                                Slug
                                <span class="text-xs text-gray-400 ml-1">Used in CSV imports — change with care</span>
                            </label>
                            <input type="text"
                                   name="slug"
                                   id="slug"
                                   value="{{ old('slug', $manufacturer->slug) }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            @error('slug')
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
                                   value="{{ old('sort_order', $manufacturer->sort_order) }}"
                                   min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber focus:border-amber transition-colors">
                            @error('sort_order')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Logo Upload --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>

                            @if($manufacturer->logo)
                            <div class="mb-3 flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                <img src="{{ $manufacturer->logo->file_url }}"
                                     alt="{{ trans_field($manufacturer->name) }}"
                                     class="h-10 w-10 object-contain rounded border border-gray-200 bg-white p-0.5">
                                <span class="text-sm text-gray-600 truncate">{{ $manufacturer->logo->file_name }}</span>
                                <label class="ml-auto flex items-center gap-1.5 text-sm text-red-600 cursor-pointer whitespace-nowrap">
                                    <input type="checkbox" name="remove_logo" value="1"
                                           class="h-4 w-4 text-red-500 border-gray-300 rounded">
                                    Remove logo
                                </label>
                            </div>
                            @endif

                            <input type="file"
                                   name="logo"
                                   id="logo"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 border border-gray-300 rounded-lg cursor-pointer">
                            <p class="mt-1 text-xs text-gray-400">
                                {{ $manufacturer->logo ? 'Upload a new logo to replace the current one.' : 'Upload a logo.' }}
                                PNG, JPG or WebP. Max 2 MB.
                            </p>
                            @error('logo')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 border-l-4 border-amber pl-3">Status</h2>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox"
                                   name="is_active"
                                   id="is_active"
                                   value="1"
                                   {{ old('is_active', $manufacturer->is_active) ? 'checked' : '' }}
                                   class="h-4 w-4 text-amber focus:ring-amber border-gray-300 rounded">
                            <label for="is_active" class="ml-2 text-sm text-gray-700">
                                Active — visible to customers
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox"
                                   name="is_verified_oem"
                                   id="is_verified_oem"
                                   value="1"
                                   {{ old('is_verified_oem', $manufacturer->is_verified_oem) ? 'checked' : '' }}
                                   class="h-4 w-4 text-amber focus:ring-amber border-gray-300 rounded">
                            <label for="is_verified_oem" class="ml-2 text-sm text-gray-700">
                                OEM Verified — official / licenced manufacturer
                            </label>
                        </div>
                    </div>
                </div>

            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                <button type="button"
                        onclick="if(confirm('Delete this manufacturer? This cannot be undone.')) { document.getElementById('delete-form').submit(); }"
                        class="px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50 transition-colors">
                    <x-heroicon-o-trash class="w-4 h-4 mr-1 inline" />
                    Delete
                </button>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.catalog.manufacturers.index') }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-amber hover:bg-amber/90 transition-colors">
                        Update Manufacturer
                    </button>
                </div>
            </div>
        </form>

        <form id="delete-form" action="{{ route('admin.catalog.manufacturers.destroy', $manufacturer) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
@endsection
