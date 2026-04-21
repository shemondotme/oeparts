@extends('layouts.admin')

@section('title', 'Manufacturer Management')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manufacturer Management</h1>
            <p class="text-gray-600 mt-1">Manage OEM manufacturers and brands</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.catalog.manufacturers.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90">
                <x-heroicon-o-plus class="w-4 h-4" />
                Add Manufacturer
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.catalog.manufacturers.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" id="name" name="name" value="{{ request('name') }}"
                       placeholder="e.g. Bosch, Valeo, Continental"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>

            <div>
                <label for="country_code" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <select id="country_code" name="country_code" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All Countries</option>
                    @foreach($countries as $code => $name)
                        <option value="{{ $code }}" {{ request('country_code') == $code ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="active_status" class="block text-sm font-medium text-gray-700 mb-1">Active</label>
                <select id="active_status" name="active_status" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All</option>
                    <option value="active" {{ request('active_status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('active_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div>
                <label for="oem_verified" class="block text-sm font-medium text-gray-700 mb-1">OEM Verified</label>
                <select id="oem_verified" name="oem_verified" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All</option>
                    <option value="verified" {{ request('oem_verified') == 'verified' ? 'selected' : '' }}>Verified</option>
                    <option value="not_verified" {{ request('oem_verified') == 'not_verified' ? 'selected' : '' }}>Not Verified</option>
                </select>
            </div>

            <div class="md:col-span-4 flex justify-end gap-3 mt-2">
                <a href="{{ route('admin.catalog.manufacturers.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Reset
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Logo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Country
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            OEM Verified
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Products
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($manufacturers as $manufacturer)
                        <tr class="cursor-pointer hover:bg-gray-50 transition-colors"
                            data-edit-url="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($manufacturer->logo)
                                    <img src="{{ $manufacturer->logo->file_url }}"
                                         alt="{{ trans_field($manufacturer->name) }}"
                                         class="h-8 w-8 object-contain rounded border border-gray-200 bg-white p-0.5">
                                @else
                                    <span class="text-gray-300">
                                        <x-heroicon-o-photo class="w-6 h-6" />
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ trans_field($manufacturer->name) }}
                                @if($manufacturer->slug)
                                    <div class="text-xs text-gray-500">{{ $manufacturer->slug }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $countries[$manufacturer->country_code] ?? $manufacturer->country_code }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($manufacturer->is_verified_oem)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Not Verified
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($manufacturer->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $manufacturer->products_count ?? $manufacturer->products()->count() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-row-click">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}"
                                       class="text-gray-600 hover:text-gray-900"
                                       title="Edit">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.catalog.manufacturers.destroy', $manufacturer) }}" method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this manufacturer?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-inbox class="w-12 h-12 mx-auto text-gray-400" />
                                <p class="mt-2 text-sm">No manufacturers found.</p>
                                <a href="{{ route('admin.catalog.manufacturers.create') }}"
                                   class="mt-4 inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <x-heroicon-o-plus class="w-4 h-4 mr-1" />
                                    Add your first manufacturer
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($manufacturers->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $manufacturers->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('tr[data-edit-url]').forEach(row => {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, form, .no-row-click')) return;
                window.location.href = this.dataset.editUrl;
            });
        });
    });
</script>
@endpush