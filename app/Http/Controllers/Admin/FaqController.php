<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaqController extends Controller
{
    /**
     * Display a paginated list of FAQs with filters.
     */
    public function index(Request $request)
    {
        $query = Faq::orderBy('sort_order');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Search by question
        if ($request->filled('search')) {
            $query->where('question->en', 'like', '%' . $request->search . '%')
                ->orWhere('question->de', 'like', '%' . $request->search . '%')
                ->orWhere('question->lt', 'like', '%' . $request->search . '%');
        }

        $faqs = $query->paginate(30)->withQueryString();

        // Get distinct categories for filter
        $categories = Faq::distinct('category')->pluck('category');

        return view('admin.cms.faqs.index', [
            'faqs' => $faqs,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new FAQ.
     */
    public function create()
    {
        $categories = Faq::distinct('category')->pluck('category');

        return view('admin.cms.faqs.create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created FAQ in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'array'],
            'question.*' => ['nullable', 'string', 'max:255'],
            'answer' => ['required', 'array'],
            'answer.*' => ['nullable', 'string'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        Faq::create($validated);

        return redirect()->route('admin.cms.faqs.index')
            ->with('success', __('FAQ created successfully.'));
    }

    /**
     * Display the specified FAQ.
     */
    public function show(Faq $faq)
    {
        return view('admin.cms.faqs.show', [
            'faq' => $faq,
        ]);
    }

    /**
     * Show the form for editing the specified FAQ.
     */
    public function edit(Faq $faq)
    {
        $categories = Faq::distinct('category')->pluck('category');

        return view('admin.cms.faqs.edit', [
            'faq' => $faq,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified FAQ in storage.
     */
    public function update(Request $request, Faq $faq)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'array'],
            'question.*' => ['nullable', 'string', 'max:255'],
            'answer' => ['required', 'array'],
            'answer.*' => ['nullable', 'string'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $faq->update($validated);

        return redirect()->route('admin.cms.faqs.index')
            ->with('success', __('FAQ updated successfully.'));
    }

    /**
     * Remove the specified FAQ from storage.
     */
    public function destroy(Faq $faq)
    {
        $faq->delete();

        return redirect()->route('admin.cms.faqs.index')
            ->with('success', __('FAQ deleted successfully.'));
    }

    /**
     * Reorder FAQs via AJAX.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:faqs,id'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->order as $position => $id) {
                Faq::where('id', $id)->update(['sort_order' => $position]);
            }
        });

        return response()->json(['success' => true]);
    }
}