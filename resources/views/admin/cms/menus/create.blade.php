@extends('layouts.admin')

@section('title', 'Create Menu')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.cms.menus.index') }}" class="text-gray-500 hover:text-gray-700">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Create Menu</h1>
                    <p class="text-gray-600 mt-1">Add a new navigation menu</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-2xl">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <form action="{{ route('admin.cms.menus.store') }}" method="POST">
                @csrf

                <div class="p-6 space-y-6">
                    {{-- Validation Errors --}}
                    @if($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <ul class="text-sm text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            Menu Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name"
                               value="{{ old('name') }}"
                               required maxlength="100"
                               placeholder="e.g., Header Navigation"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Location --}}
                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700 mb-1">
                            Location <span class="text-red-500">*</span>
                        </label>
                        <select id="location" name="location" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Select location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->value }}" {{ old('location') === $location->value ? 'selected' : '' }}>
                                    {{ ucfirst($location->value) }}
                                </option>
                            @endforeach
                        </select>
                        @error('location')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Active --}}
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                        <label for="is_active" class="text-sm text-gray-700">Menu is active</label>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <a href="{{ route('admin.cms.menus.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                        <x-heroicon-o-check class="w-4 h-4" />
                        Create Menu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
