@extends('layouts.admin')

@section('title', 'Subscriber: ' . $subscriber->email)

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.newsletter.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $subscriber->email }}</h1>
                    @if($subscriber->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Unsubscribed</span>
                    @endif
                </div>
                <p class="text-gray-500 text-sm mt-1">Newsletter Subscriber</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <form action="{{ route('admin.cms.newsletter.toggle-status', $subscriber) }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    @if($subscriber->is_active)
                        <x-heroicon-o-pause-circle class="w-4 h-4" />
                        Unsubscribe
                    @else
                        <x-heroicon-o-play-circle class="w-4 h-4" />
                        Reactivate
                    @endif
                </button>
            </form>
            <form action="{{ route('admin.cms.newsletter.destroy', $subscriber) }}" method="POST"
                  onsubmit="return confirm('Delete this subscriber?');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="max-w-lg">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Subscriber Details</h2>
            <dl class="space-y-4">
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="text-sm text-gray-900">{{ $subscriber->email }}</dd>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <dt class="text-sm font-medium text-gray-500">Language</dt>
                    <dd>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                            {{ strtoupper($subscriber->lang) }}
                        </span>
                    </dd>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd>
                        @if($subscriber->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Unsubscribed</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <dt class="text-sm font-medium text-gray-500">Subscribed At</dt>
                    <dd class="text-sm text-gray-900">
                        {{ $subscriber->subscribed_at ? $subscriber->subscribed_at->format('M d, Y H:i') : '—' }}
                    </dd>
                </div>
                @if($subscriber->unsubscribed_at)
                    <div class="flex items-center justify-between py-3 border-b border-gray-50">
                        <dt class="text-sm font-medium text-gray-500">Unsubscribed At</dt>
                        <dd class="text-sm text-gray-900">{{ $subscriber->unsubscribed_at->format('M d, Y H:i') }}</dd>
                    </div>
                @endif
                @if($subscriber->ip_address)
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                        <dd class="text-sm font-mono text-gray-900">{{ $subscriber->ip_address }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection
