@extends('layouts.admin')

@section('title', 'Pages')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pages</h1>
            <p class="text-gray-600 mt-1">Manage CMS pages and landing content</p>
        </div>
        <a href="{{ route('admin.cms.pages.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
            <x-heroicon-o-plus class="w-4 h-4" />
            Create Page
        </a>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.cms.pages.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                       placeholder="Search by title..."
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ ucfirst($status->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-3">
                <a href="{{ route('admin.cms.pages.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Reset
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                    Filter
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flags</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($pages as $page)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ trans_field($page->title) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-mono text-gray-600">{{ $page->slug }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($page->status->value === 'published') bg-green-100 text-green-800
                                    @else bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ ucfirst($page->status->value) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    @if($page->is_homepage)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700" title="Homepage">H</span>
                                    @endif
                                    @if($page->is_header)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700" title="In Header">Nav</span>
                                    @endif
                                    @if($page->is_footer)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700" title="In Footer">Ft</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $page->updated_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.cms.pages.show', $page) }}"
                                       class="text-gray-600 hover:text-gray-900" title="View">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                    <a href="{{ route('admin.cms.pages.edit', $page) }}"
                                       class="text-[#0B3A68] hover:text-blue-900" title="Edit">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.cms.pages.destroy', $page) }}" method="POST"
                                          class="inline" onsubmit="return confirm('Delete this page?');">
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
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-document class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-lg font-medium text-gray-900">No pages found</p>
                                <p class="text-gray-600 mt-1">Create your first page to get started.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pages->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $pages->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
