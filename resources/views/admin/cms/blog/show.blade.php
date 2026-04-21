@extends('layouts.admin')

@section('title', trans_field($post->title))

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.blog.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ trans_field($post->title) }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($post->status->value === 'published') bg-green-100 text-green-800
                        @else bg-yellow-100 text-yellow-800
                        @endif">
                        {{ ucfirst($post->status->value) }}
                    </span>
                </div>
                <p class="text-gray-500 text-sm font-mono mt-1">{{ $post->slug }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.blog.edit', $post) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit
            </a>
            <form action="{{ route('admin.cms.blog.destroy', $post) }}" method="POST"
                  onsubmit="return confirm('Delete this post?');" class="inline">
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            {{-- Content preview --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ lang: 'en' }">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Content</h2>
                    <div class="flex gap-1">
                        @foreach(['en','de','lt','fr','es'] as $lang)
                            <button type="button"
                                    @click="lang = '{{ $lang }}'"
                                    :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-2.5 py-1 text-xs font-semibold rounded transition-colors">
                                {{ strtoupper($lang) }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="p-6">
                    @foreach(['en','de','lt','fr','es'] as $lang)
                        <div x-show="lang === '{{ $lang }}'">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $post->title[$lang] ?? '' }}</h3>
                            @if(!empty($post->excerpt[$lang]))
                                <p class="text-sm text-gray-500 italic mb-4">{{ $post->excerpt[$lang] }}</p>
                            @endif
                            <div class="text-sm text-gray-700 prose max-w-none whitespace-pre-wrap">
                                {{ $post->content[$lang] ?? '' }}
                            </div>
                            @if(empty($post->title[$lang]))
                                <p class="text-sm text-gray-400 italic">No content for {{ strtoupper($lang) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Details</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Category</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $post->category ? trans_field($post->category->name) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Author</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $post->author?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Tags</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse($post->tags as $tag)
                                <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded">{{ $tag->name }}</span>
                            @empty
                                <span class="text-sm text-gray-400">None</span>
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Published</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $post->published_at ? $post->published_at->format('M d, Y H:i') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $post->created_at->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Updated</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $post->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">SEO (EN)</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Meta Title</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $post->meta_title['en'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Meta Description</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $post->meta_description['en'] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
