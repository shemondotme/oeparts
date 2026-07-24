<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Category;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * Display blog listing page.
     */
    public function index(Request $request, string $lang)
    {
        $query = BlogPost::with(['author', 'category', 'featuredImage'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title->en', 'LIKE', "%{$search}%")
                  ->orWhere('title->de', 'LIKE', "%{$search}%")
                  ->orWhere('title->lt', 'LIKE', "%{$search}%")
                  ->orWhere('title->fr', 'LIKE', "%{$search}%")
                  ->orWhere('title->es', 'LIKE', "%{$search}%")
                  ->orWhere('content->en', 'LIKE', "%{$search}%")
                  ->orWhere('content->de', 'LIKE', "%{$search}%")
                  ->orWhere('content->lt', 'LIKE', "%{$search}%")
                  ->orWhere('content->fr', 'LIKE', "%{$search}%")
                  ->orWhere('content->es', 'LIKE', "%{$search}%");
            });
        }

        // "Featured" is just the newest published post with an image — when no
        // filter is active that's also list item 001, so exclude it from the
        // main list below to avoid showing the same post twice on the page.
        $featuredPost = BlogPost::with('featuredImage')
            ->published()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('featured_image_id')
            ->orderBy('published_at', 'desc')
            ->first();

        if ($featuredPost && ! $request->anyFilled(['category', 'tag', 'search'])) {
            $query->where('id', '!=', $featuredPost->id);
        }

        $posts = $query->orderBy('published_at', 'desc')
            ->paginate(settings('general.pagination_per_page', 10))
            ->withQueryString();

        // withCount avoids loading each category's full blogPosts collection just
        // to render the count badge in the sidebar (was an N+1 + heavy rows).
        // Both scoped to published posts only — otherwise a category/tag whose
        // only posts are drafts shows an inflated count and a dead-end filter link.
        $categories = Category::whereHas('blogPosts', fn ($q) => $q->published())
            ->withCount(['blogPosts as blog_posts_count' => fn ($q) => $q->published()])
            ->get();
        $tags = BlogTag::whereHas('posts', fn ($q) => $q->published())->get();

        return view('frontend.blog.index', compact('posts', 'categories', 'tags', 'featuredPost'));
    }

    /**
     * Display blog post detail page.
     */
    public function show(Request $request, string $lang, string $slug)
    {
        $post = BlogPost::with(['author', 'category', 'tags', 'featuredImage'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        // Get related posts (same category or tags)
        $relatedPosts = BlogPost::with('featuredImage')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('id', '!=', $post->id)
            ->where(function ($q) use ($post) {
                $q->where('category_id', $post->category_id)
                  ->orWhereHas('tags', function ($tagQ) use ($post) {
                      $tagQ->whereIn('blog_tags.id', $post->tags->pluck('id'));
                  });
            })
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->get();

        return view('frontend.blog.show', compact('post', 'relatedPosts'));
    }
}
