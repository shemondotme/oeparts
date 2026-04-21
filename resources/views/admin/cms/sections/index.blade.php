@extends('layouts.admin')

@section('title', 'Sections')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sections</h1>
            <p class="text-gray-600 mt-1">Manage homepage sections and content blocks</p>
        </div>
        <a href="{{ route('admin.cms.sections.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
            <x-heroicon-o-plus class="w-4 h-4" />
            New Section
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('admin.cms.sections.index') }}" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Location</label>
                <select name="location" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All Locations</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->value }}" {{ request('location') === $loc->value ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $loc->value)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
                <input type="text" name="type" value="{{ request('type') }}" placeholder="e.g. hero, banner"
                       class="rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="is_active" class="rounded-lg border-gray-300 text-sm">
                    <option value="all">All</option>
                    <option value="active" {{ request('is_active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('is_active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.cms.sections.index') }}"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">Reset</a>
                <button type="submit"
                        class="px-3 py-2 bg-[#0B3A68] text-white rounded-lg text-sm hover:bg-blue-900">Filter</button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sections as $section)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-700">
                                {{ $section->type }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $section->location->value }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                {{ trans_field($section->title) ?: '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($section->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $section->sort_order }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.cms.sections.show', $section) }}"
                                       class="text-gray-500 hover:text-gray-900" title="View">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                    <a href="{{ route('admin.cms.sections.edit', $section) }}"
                                       class="text-[#0B3A68] hover:text-blue-900" title="Edit">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.cms.sections.destroy', $section) }}" method="POST"
                                          class="inline" onsubmit="return confirm('Delete this section?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600" title="Delete">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <x-heroicon-o-squares-plus class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-sm font-medium text-gray-900">No sections found</p>
                                <p class="text-xs text-gray-500 mt-1">Create your first content section.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sections->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $sections->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
