<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Category;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlogController extends Controller
{
    /**
     * Display a paginated list of blog posts with filters.
     */
    public function index(Request $request)
    {
        $query = BlogPost::with(['category', 'author', 'tags'])
            ->latest('published_at');

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by author
        if ($request->filled('author_id')) {
            $query->where('author_id', $request->author_id);
        }

        // Search by title
        if ($request->filled('search')) {
            $query->where('title->en', 'like', '%' . $request->search . '%')
                ->orWhere('title->de', 'like', '%' . $request->search . '%')
                ->orWhere('title->lt', 'like', '%' . $request->search . '%');
        }

        $posts = $query->paginate(20)->withQueryString();
        $categories = Category::where('type', 'blog')->get();
        $authors = \App\Models\Admin::all();

        return view('admin.cms.blog.index', [
            'posts' => $posts,
            'categories' => $categories,
            'authors' => $authors,
            'statuses' => ContentStatus::cases(),
        ]);
    }

    /**
     * Show the form for creating a new blog post.
     */
    public function create()
    {
        return view('admin.cms.blog.create', [
            'categories' => Category::where('type', 'blog')->get(),
            'tags' => BlogTag::all(),
            'media' => MediaFile::latest()->limit(50)->get(),
            'statuses' => ContentStatus::cases(),
        ]);
    }

    /**
     * Store a newly created blog post in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:blog_posts,slug'],
            'excerpt' => ['required', 'array'],
            'excerpt.*' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string'],
            'featured_image_id' => ['nullable', 'exists:media_files,id'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'meta_title' => ['nullable', 'array'],
            'meta_title.*' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:blog_tags,id'],
        ]);

        $validated['author_id'] = auth('admin')->id();

        DB::transaction(function () use ($validated) {
            $post = BlogPost::create($validated);
            if (isset($validated['tags'])) {
                $post->tags()->sync($validated['tags']);
            }
        });

        return redirect()->route('admin.cms.blog.index')
            ->with('success', __('Blog post created successfully.'));
    }

    /**
     * Display the specified blog post.
     */
    public function show(BlogPost $blog)
    {
        return view('admin.cms.blog.show', [
            'post' => $blog->load(['category', 'author', 'tags', 'featuredImage']),
        ]);
    }

    /**
     * Show the form for editing the specified blog post.
     */
    public function edit(BlogPost $blog)
    {
        return view('admin.cms.blog.edit', [
            'post' => $blog,
            'categories' => Category::where('type', 'blog')->get(),
            'tags' => BlogTag::all(),
            'media' => MediaFile::latest()->limit(50)->get(),
            'statuses' => ContentStatus::cases(),
        ]);
    }

    /**
     * Update the specified blog post in storage.
     */
    public function update(Request $request, BlogPost $blog)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('blog_posts')->ignore($blog->id)],
            'excerpt' => ['required', 'array'],
            'excerpt.*' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string'],
            'featured_image_id' => ['nullable', 'exists:media_files,id'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'meta_title' => ['nullable', 'array'],
            'meta_title.*' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:blog_tags,id'],
        ]);

        DB::transaction(function () use ($blog, $validated) {
            $blog->update($validated);
            $blog->tags()->sync($validated['tags'] ?? []);
        });

        return redirect()->route('admin.cms.blog.index')
            ->with('success', __('Blog post updated successfully.'));
    }

    /**
     * Remove the specified blog post from storage.
     */
    public function destroy(BlogPost $blog)
    {
        $blog->delete();

        return redirect()->route('admin.cms.blog.index')
            ->with('success', __('Blog post deleted successfully.'));
    }
}