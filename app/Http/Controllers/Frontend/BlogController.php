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
        $query = BlogPost::with(['author', 'category', 'tags'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by tag
        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        // Search
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

        $posts = $query->orderBy('published_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $categories = Category::whereHas('blogPosts')->get();
        $tags = BlogTag::whereHas('posts')->get();
        $featuredPost = BlogPost::where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('featured_image_id')
            ->orderBy('published_at', 'desc')
            ->first();

        return view('frontend.blog.index', compact('posts', 'categories', 'tags', 'featuredPost'));
    }

    /**
     * Display blog post detail page.
     */
    public function show(Request $request, string $lang, string $slug)
    {
        $post = BlogPost::with(['author', 'category', 'tags'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        // Get related posts (same category or tags)
        $relatedPosts = BlogPost::with(['author', 'category'])
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
