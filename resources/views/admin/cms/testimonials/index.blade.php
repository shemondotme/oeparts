@extends('layouts.admin')

@section('title', 'Testimonials')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Testimonials</h1>
            <p class="text-gray-600 mt-1">Manage customer reviews and testimonials</p>
        </div>
        <a href="{{ route('admin.cms.testimonials.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
            <x-heroicon-o-plus class="w-4 h-4" />
            Add Testimonial
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.cms.testimonials.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                       placeholder="Author name..."
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label for="rating" class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                <select id="rating" name="rating" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All Ratings</option>
                    @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ request('rating') == $i ? 'selected' : '' }}>
                            {{ $i }} Star{{ $i > 1 ? 's' : '' }}
                        </option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="is_approved" class="block text-sm font-medium text-gray-700 mb-1">Approval</label>
                <select id="is_approved" name="is_approved" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="all" {{ request('is_approved', 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="approved" {{ request('is_approved') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="pending" {{ request('is_approved') === 'pending' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <a href="{{ route('admin.cms.testimonials.index') }}"
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Featured</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($testimonials as $testimonial)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $testimonial->author_name }}</div>
                                @if($testimonial->author_title || $testimonial->author_company)
                                    <div class="text-xs text-gray-500">
                                        {{ $testimonial->author_title }}
                                        @if($testimonial->author_title && $testimonial->author_company) — @endif
                                        {{ $testimonial->author_company }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700 line-clamp-2 max-w-xs">
                                    {{ trans_field($testimonial->content) }}
                                </p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= $testimonial->rating)
                                            <x-heroicon-s-star class="w-4 h-4 text-amber-400" />
                                        @else
                                            <x-heroicon-o-star class="w-4 h-4 text-gray-300" />
                                        @endif
                                    @endfor
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form action="{{ route('admin.cms.testimonials.toggle-approval', $testimonial) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit">
                                        @if($testimonial->is_approved)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 cursor-pointer hover:bg-green-200">
                                                Approved
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 cursor-pointer hover:bg-yellow-200">
                                                Pending
                                            </span>
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form action="{{ route('admin.cms.testimonials.toggle-featured', $testimonial) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit">
                                        @if($testimonial->featured)
                                            <x-heroicon-s-star class="w-5 h-5 text-amber-400 hover:text-amber-500" />
                                        @else
                                            <x-heroicon-o-star class="w-5 h-5 text-gray-300 hover:text-amber-400" />
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.cms.testimonials.show', $testimonial) }}"
                                       class="text-gray-500 hover:text-gray-900">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                    <a href="{{ route('admin.cms.testimonials.edit', $testimonial) }}"
                                       class="text-[#0B3A68] hover:text-blue-900">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.cms.testimonials.destroy', $testimonial) }}" method="POST"
                                          class="inline" onsubmit="return confirm('Delete this testimonial?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-chat-bubble-left-ellipsis class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-lg font-medium text-gray-900">No testimonials yet</p>
                                <p class="text-gray-600 mt-1">Add your first customer testimonial.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($testimonials->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $testimonials->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
