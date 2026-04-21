<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestimonialController extends Controller
{
    /**
     * Display a paginated list of testimonials with filters.
     */
    public function index(Request $request)
    {
        $query = Testimonial::latest();

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by approved status
        if ($request->filled('is_approved') && $request->is_approved !== 'all') {
            $query->where('is_approved', $request->is_approved === 'approved');
        }

        // Search by author name
        if ($request->filled('search')) {
            $query->where('author_name', 'like', '%' . $request->search . '%')
                ->orWhere('author_title', 'like', '%' . $request->search . '%');
        }

        $testimonials = $query->paginate(20)->withQueryString();

        return view('admin.cms.testimonials.index', [
            'testimonials' => $testimonials,
        ]);
    }

    /**
     * Show the form for creating a new testimonial.
     */
    public function create()
    {
        return view('admin.cms.testimonials.create');
    }

    /**
     * Store a newly created testimonial in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'author_name' => ['required', 'string', 'max:100'],
            'author_title' => ['nullable', 'string', 'max:100'],
            'author_company' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string', 'max:1000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'is_approved' => ['boolean'],
            'featured' => ['boolean'],
        ]);

        Testimonial::create($validated);

        return redirect()->route('admin.cms.testimonials.index')
            ->with('success', __('Testimonial created successfully.'));
    }

    /**
     * Display the specified testimonial.
     */
    public function show(Testimonial $testimonial)
    {
        return view('admin.cms.testimonials.show', [
            'testimonial' => $testimonial,
        ]);
    }

    /**
     * Show the form for editing the specified testimonial.
     */
    public function edit(Testimonial $testimonial)
    {
        return view('admin.cms.testimonials.edit', [
            'testimonial' => $testimonial,
        ]);
    }

    /**
     * Update the specified testimonial in storage.
     */
    public function update(Request $request, Testimonial $testimonial)
    {
        $validated = $request->validate([
            'author_name' => ['required', 'string', 'max:100'],
            'author_title' => ['nullable', 'string', 'max:100'],
            'author_company' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string', 'max:1000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'is_approved' => ['boolean'],
            'featured' => ['boolean'],
        ]);

        $testimonial->update($validated);

        return redirect()->route('admin.cms.testimonials.index')
            ->with('success', __('Testimonial updated successfully.'));
    }

    /**
     * Remove the specified testimonial from storage.
     */
    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return redirect()->route('admin.cms.testimonials.index')
            ->with('success', __('Testimonial deleted successfully.'));
    }

    /**
     * Toggle approval status.
     */
    public function toggleApproval(Testimonial $testimonial)
    {
        $testimonial->update([
            'is_approved' => !$testimonial->is_approved,
        ]);

        return redirect()->back()
            ->with('success', __('Testimonial approval status updated.'));
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Testimonial $testimonial)
    {
        $testimonial->update([
            'featured' => !$testimonial->featured,
        ]);

        return redirect()->back()
            ->with('success', __('Testimonial featured status updated.'));
    }
}