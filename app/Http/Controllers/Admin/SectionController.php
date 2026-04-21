<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SectionLocation;
use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    /**
     * Display a paginated list of sections with filters.
     */
    public function index(Request $request)
    {
        $query = Section::latest('sort_order');

        // Filter by location
        if ($request->filled('location')) {
            $query->where('location', $request->location);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', 'like', '%' . $request->type . '%');
        }

        // Filter by active status
        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'active');
        }

        $sections = $query->paginate(20)->withQueryString();

        return view('admin.cms.sections.index', [
            'sections' => $sections,
            'locations' => SectionLocation::cases(),
        ]);
    }

    /**
     * Show the form for creating a new section.
     */
    public function create()
    {
        return view('admin.cms.sections.create', [
            'locations' => SectionLocation::cases(),
        ]);
    }

    /**
     * Store a newly created section in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'location' => ['required', Rule::enum(SectionLocation::class)],
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        // 'content' is handled by the 'array' cast on the Section model — no manual encoding needed
        // 'title' is cast to 'array' via Section model cast — no manual encoding needed

        Section::create($validated);

        return redirect()->route('admin.cms.sections.index')
            ->with('success', __('Section created successfully.'));
    }

    /**
     * Display the specified section.
     */
    public function show(Section $section)
    {
        return view('admin.cms.sections.show', [
            'section' => $section,
        ]);
    }

    /**
     * Show the form for editing the specified section.
     */
    public function edit(Section $section)
    {
        return view('admin.cms.sections.edit', [
            'section' => $section,
            'locations' => SectionLocation::cases(),
        ]);
    }

    /**
     * Update the specified section in storage.
     */
    public function update(Request $request, Section $section)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'location' => ['required', Rule::enum(SectionLocation::class)],
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        // 'content' is handled by the 'array' cast on the Section model — no manual encoding needed
        // 'title' is cast to 'array' via Section model cast — no manual encoding needed

        $section->update($validated);

        return redirect()->route('admin.cms.sections.index')
            ->with('success', __('Section updated successfully.'));
    }

    /**
     * Remove the specified section from storage.
     */
    public function destroy(Section $section)
    {
        $section->delete();

        return redirect()->route('admin.cms.sections.index')
            ->with('success', __('Section deleted successfully.'));
    }

    /**
     * Reorder sections via AJAX.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:sections,id'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->order as $position => $id) {
                Section::where('id', $id)->update(['sort_order' => $position]);
            }
        });

        return response()->json(['success' => true]);
    }
}