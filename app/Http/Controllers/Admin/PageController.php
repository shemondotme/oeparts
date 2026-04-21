<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    /**
     * Display a paginated list of pages with filters.
     */
    public function index(Request $request)
    {
        $query = Page::with(['featuredImage'])
            ->latest('updated_at');

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by title
        if ($request->filled('search')) {
            $query->where('title->en', 'like', '%' . $request->search . '%')
                ->orWhere('title->de', 'like', '%' . $request->search . '%')
                ->orWhere('title->lt', 'like', '%' . $request->search . '%');
        }

        $pages = $query->paginate(20)->withQueryString();

        return view('admin.cms.pages.index', [
            'pages' => $pages,
            'statuses' => ContentStatus::cases(),
        ]);
    }

    /**
     * Show the form for creating a new page.
     */
    public function create()
    {
        return view('admin.cms.pages.create', [
            'media' => MediaFile::latest()->limit(50)->get(),
            'statuses' => ContentStatus::cases(),
        ]);
    }

    /**
     * Store a newly created page in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:pages,slug'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string'],
            'featured_image_id' => ['nullable', 'exists:media_files,id'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'meta_title' => ['nullable', 'array'],
            'meta_title.*' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string', 'max:500'],
            'is_homepage' => ['boolean'],
            'is_header' => ['boolean'],
            'is_footer' => ['boolean'],
        ]);

        Page::create($validated);

        return redirect()->route('admin.cms.pages.index')
            ->with('success', __('Page created successfully.'));
    }

    /**
     * Display the specified page.
     */
    public function show(Page $page)
    {
        return view('admin.cms.pages.show', [
            'page' => $page->load(['featuredImage']),
        ]);
    }

    /**
     * Show the form for editing the specified page.
     */
    public function edit(Page $page)
    {
        return view('admin.cms.pages.edit', [
            'page' => $page,
            'media' => MediaFile::latest()->limit(50)->get(),
            'statuses' => ContentStatus::cases(),
        ]);
    }

    /**
     * Update the specified page in storage.
     */
    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('pages')->ignore($page->id)],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string'],
            'featured_image_id' => ['nullable', 'exists:media_files,id'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'meta_title' => ['nullable', 'array'],
            'meta_title.*' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string', 'max:500'],
            'is_homepage' => ['boolean'],
            'is_header' => ['boolean'],
            'is_footer' => ['boolean'],
        ]);

        $page->update($validated);

        return redirect()->route('admin.cms.pages.index')
            ->with('success', __('Page updated successfully.'));
    }

    /**
     * Remove the specified page from storage.
     */
    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.cms.pages.index')
            ->with('success', __('Page deleted successfully.'));
    }
}