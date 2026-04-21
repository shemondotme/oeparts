@extends('layouts.app')

@section('title', trans_field($post->meta_title) ?: trans_field($post->title))
@section('description', trans_field($post->meta_description) ?: Str::limit(strip_tags(trans_field($post->content)), 160))

@section('content')
{{-- Hero Section --}}
<section class="bg-navy py-12 lg:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        @if($post->category)
            <span class="inline-block px-3 py-1 bg-amber/20 text-amber text-sm font-medium rounded-full mb-4">
                {{ trans_field($post->category->name) }}
            </span>
        @endif
        <h1 class="text-3xl lg:text-4xl font-display font-bold text-white mb-4">
            {{ trans_field($post->title) }}
        </h1>
        <div class="flex items-center justify-center gap-4 text-white/80 text-sm">
            <span>{{ $post->author->name ?? trans('blog.anonymous') }}</span>
            <span>•</span>
            <time datetime="{{ $post->published_at }}">
                {{ \Carbon\Carbon::parse($post->published_at)->format('F d, Y') }}
            </time>
            @if($post->tags->count() > 0)
                <span>•</span>
                <div class="flex gap-2">
                    @foreach($post->tags->take(3) as $tag)
                        <span class="text-white/60">#{{ trans_field($tag->name) }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>

{{-- Featured Image --}}
@if($post->featuredImage)
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8">
        <img src="{{ $post->featuredImage->file_url }}" 
             alt="{{ trans_field($post->title) }}" 
             class="w-full rounded-xl shadow-lg border border-slate-200">
    </div>
@endif

{{-- Main Content --}}
<section class="py-12 lg:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <article class="prose prose-lg prose-navy max-w-none">
            {!! trans_field($post->content) !!}
        </article>

        {{-- Share Buttons --}}
        <div class="mt-8 pt-8 border-t border-slate-200">
            <h3 class="text-lg font-semibold text-navy mb-4">{{ trans('blog.share') }}</h3>
            <div class="flex gap-3">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('frontend.blog.show', ['lang' => $locale, 'slug' => $post->slug])) }}" 
                   target="_blank" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                    Facebook
                </a>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('frontend.blog.show', ['lang' => $locale, 'slug' => $post->slug])) }}&text={{ urlencode(trans_field($post->title)) }}" 
                   target="_blank" 
                   class="px-4 py-2 bg-sky-500 text-white rounded-lg hover:bg-sky-600 transition-colors text-sm font-medium">
                    Twitter
                </a>
                <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode(route('frontend.blog.show', ['lang' => $locale, 'slug' => $post->slug])) }}" 
                   target="_blank" 
                   class="px-4 py-2 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors text-sm font-medium">
                    LinkedIn
                </a>
            </div>
        </div>

        {{-- Back to Blog --}}
        <div class="mt-8">
            <a href="{{ route('frontend.blog.index', ['lang' => $locale]) }}" 
               class="inline-flex items-center text-amber font-medium hover:underline">
                <x-heroicon-o-arrow-left class="w-5 h-5 mr-2" />
                {{ trans('blog.back_to_blog') }}
            </a>
        </div>
    </div>
</section>

{{-- Related Posts --}}
@if($relatedPosts->count() > 0)
    <section class="py-12 lg:py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-display font-bold text-navy mb-8 text-center">
                {{ trans('blog.related_posts') }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($relatedPosts as $relatedPost)
                    <article class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                        @if($relatedPost->featuredImage)
                            <a href="{{ route('frontend.blog.show', ['lang' => $locale, 'slug' => $relatedPost->slug]) }}">
                                <img src="{{ $relatedPost->featuredImage->file_url }}" 
                                     alt="{{ trans_field($relatedPost->title) }}" 
                                     class="w-full h-40 object-cover">
                            </a>
                        @endif
                        <div class="p-4">
                            <h3 class="font-display font-bold text-navy mb-2 line-clamp-2">
                                <a href="{{ route('frontend.blog.show', ['lang' => $locale, 'slug' => $relatedPost->slug]) }}" 
                                   class="hover:text-amber transition-colors">
                                    {{ trans_field($relatedPost->title) }}
                                </a>
                            </h3>
                            <p class="text-sm text-slate-500">
                                {{ \Carbon\Carbon::parse($relatedPost->published_at)->format('M d, Y') }}
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
