@extends('layouts.app')

@section('title', trans('blog.title', [], $locale ?? 'en'))
@section('description', trans('blog.description', [], $locale ?? 'en'))

@section('content')
{{-- Hero Section --}}
<section class="bg-navy py-12 lg:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-3xl lg:text-4xl font-display font-bold text-white mb-4">
                {{ trans('blog.title', [], $locale ?? 'en') }}
            </h1>
            <p class="text-lg text-white/80 max-w-2xl mx-auto">
                {{ trans('blog.description', [], $locale ?? 'en') }}
            </p>
        </div>
    </div>
</section>

{{-- Main Content --}}
<section class="py-12 lg:py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Blog Posts --}}
            <div class="lg:col-span-2">
                @if($posts->count() > 0)
                    <div class="space-y-8">
                        @foreach($posts as $post)
                            <article class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                                @if($post->featured_image_id && $post->featuredImage)
                                    <a href="{{ route('frontend.blog.show', ['lang' => $locale ?? 'en', 'slug' => $post->slug]) }}">
                                        <img src="{{ $post->featuredImage->file_url }}" 
                                             alt="{{ trans_field($post->title) }}" 
                                             class="w-full h-48 object-cover">
                                    </a>
                                @endif
                                <div class="p-6">
                                    <div class="flex items-center gap-4 text-sm text-slate-500 mb-3">
                                        @if($post->category)
                                            <span class="text-amber font-medium">{{ trans_field($post->category->name) }}</span>
                                        @endif
                                        <span>•</span>
                                        <time datetime="{{ $post->published_at }}">
                                            {{ \Carbon\Carbon::parse($post->published_at)->format('M d, Y') }}
                                        </time>
                                    </div>
                                    <h2 class="text-xl font-display font-bold text-navy mb-2">
                                        <a href="{{ route('frontend.blog.show', ['lang' => $locale ?? 'en', 'slug' => $post->slug]) }}" 
                                           class="hover:text-amber transition-colors">
                                            {{ trans_field($post->title) }}
                                        </a>
                                    </h2>
                                    <p class="text-slate-600 mb-4 line-clamp-3">
                                        {{ trans_field($post->excerpt) }}
                                    </p>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-navy flex items-center justify-center text-white font-medium text-sm">
                                                {{ substr($post->author->name ?? 'A', 0, 1) }}
                                            </div>
                                            <span class="text-sm text-slate-600">{{ $post->author->name ?? trans('blog.anonymous') }}</span>
                                        </div>
                                        <a href="{{ route('frontend.blog.show', ['lang' => $locale ?? 'en', 'slug' => $post->slug]) }}" 
                                           class="text-amber font-medium text-sm hover:underline">
                                            {{ trans('blog.read_more') }} →
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $posts->links('components.ui.pagination') }}
                    </div>
                @else
                    <div class="text-center py-12">
                        <p class="text-slate-500">{{ trans('blog.no_posts') }}</p>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-6">
                {{-- Featured Post --}}
                @if($featuredPost)
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        @if($featuredPost->featuredImage)
                            <img src="{{ $featuredPost->featuredImage->file_url }}" 
                                 alt="{{ trans_field($featuredPost->title) }}" 
                                 class="w-full h-40 object-cover">
                        @endif
                        <div class="p-4">
                            <span class="text-xs font-semibold text-amber uppercase tracking-wider">
                                {{ trans('blog.featured') }}
                            </span>
                            <h3 class="font-display font-bold text-navy mt-2 mb-2">
                                <a href="{{ route('frontend.blog.show', ['lang' => $locale ?? 'en', 'slug' => $featuredPost->slug]) }}" 
                                   class="hover:text-amber transition-colors">
                                    {{ trans_field($featuredPost->title) }}
                                </a>
                            </h3>
                        </div>
                    </div>
                @endif

                {{-- Categories --}}
                @if($categories->count() > 0)
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h3 class="font-display font-bold text-navy mb-4">{{ trans('blog.categories') }}</h3>
                        <ul class="space-y-2">
                            @foreach($categories as $category)
                                <li>
                                    <a href="{{ route('frontend.blog.index', ['lang' => $locale ?? 'en', 'category' => $category->slug]) }}" 
                                       class="flex items-center justify-between text-slate-600 hover:text-amber transition-colors">
                                        <span>{{ trans_field($category->name) }}</span>
                                        <span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full">
                                            {{ $category->blogPosts->count() }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Tags --}}
                @if($tags->count() > 0)
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <h3 class="font-display font-bold text-navy mb-4">{{ trans('blog.tags') }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($tags as $tag)
                                <a href="{{ route('frontend.blog.index', ['lang' => $locale ?? 'en', 'tag' => $tag->slug]) }}" 
                                   class="px-3 py-1 bg-slate-100 text-slate-600 text-sm rounded-full hover:bg-amber hover:text-white transition-colors">
                                    {{ trans_field($tag->name) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Search --}}
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h3 class="font-display font-bold text-navy mb-4">{{ trans('blog.search') }}</h3>
                    <form action="{{ route('frontend.blog.index', ['lang' => $locale ?? 'en']) }}" method="GET">
                        <div class="flex gap-2">
                            <input type="text" 
                                   name="search" 
                                   placeholder="{{ trans('blog.search_placeholder') }}" 
                                   value="{{ request('search') }}"
                                   class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber focus:border-transparent">
                            <button type="submit" class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-navy/90 transition-colors">
                                <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                            </button>
                        </div>
                    </form>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
