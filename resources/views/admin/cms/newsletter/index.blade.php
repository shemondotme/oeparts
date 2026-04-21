@extends('layouts.admin')

@section('title', 'Newsletter Subscribers')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Newsletter Subscribers</h1>
            <p class="text-gray-600 mt-1">Manage email subscribers and export lists</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.newsletter.export') }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Export Active
            </a>
            <a href="{{ route('admin.cms.newsletter.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-plus class="w-4 h-4" />
                Add Subscriber
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Total</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $subscribers->total() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Active</p>
            <p class="text-2xl font-bold text-green-600 mt-1">
                {{ \App\Models\NewsletterSubscriber::where('is_active', true)->count() }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">Unsubscribed</p>
            <p class="text-2xl font-bold text-gray-400 mt-1">
                {{ \App\Models\NewsletterSubscriber::where('is_active', false)->count() }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-500">This Month</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">
                {{ \App\Models\NewsletterSubscriber::whereMonth('subscribed_at', now()->month)->count() }}
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.cms.newsletter.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Email</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                       placeholder="subscriber@example.com"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="is_active" name="is_active" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="all" {{ request('is_active', 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="active" {{ request('is_active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('is_active') === 'inactive' ? 'selected' : '' }}>Unsubscribed</option>
                </select>
            </div>
            <div>
                <label for="lang" class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                <select id="lang" name="lang" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All Languages</option>
                    @foreach(['en','de','lt','fr','es'] as $lang)
                        <option value="{{ $lang }}" {{ request('lang') === $lang ? 'selected' : '' }}>
                            {{ strtoupper($lang) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-3">
                <a href="{{ route('admin.cms.newsletter.index') }}"
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Language</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscribed</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($subscribers as $subscriber)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $subscriber->email }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ strtoupper($subscriber->lang) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($subscriber->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Unsubscribed</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $subscriber->subscribed_at ? $subscriber->subscribed_at->format('M d, Y') : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.cms.newsletter.show', $subscriber) }}"
                                       class="text-gray-500 hover:text-gray-900">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.cms.newsletter.toggle-status', $subscriber) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-amber-500 hover:text-amber-700"
                                                title="{{ $subscriber->is_active ? 'Unsubscribe' : 'Reactivate' }}">
                                            @if($subscriber->is_active)
                                                <x-heroicon-o-pause-circle class="w-4 h-4" />
                                            @else
                                                <x-heroicon-o-play-circle class="w-4 h-4" />
                                            @endif
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.cms.newsletter.destroy', $subscriber) }}" method="POST"
                                          class="inline" onsubmit="return confirm('Delete this subscriber?');">
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
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-envelope class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-lg font-medium text-gray-900">No subscribers</p>
                                <p class="text-gray-600 mt-1">No newsletter subscribers match the current filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($subscribers->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $subscribers->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
