<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SectionLocation;
use App\Enums\SectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\SectionVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $query = Section::latest('sort_order');

        if ($request->filled('location')) {
            $query->where('location', $request->location);
        }
        if ($request->filled('type')) {
            $query->where('type', 'like', '%' . $request->type . '%');
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'active');
        }

        $sections = $query->paginate(20)->withQueryString();

        return view('admin.cms.sections.index', [
            'sections'  => $sections,
            'locations' => SectionLocation::cases(),
            'statuses'  => SectionStatus::cases(),
        ]);
    }

    public function create()
    {
        return view('admin.cms.sections.create', [
            'locations' => SectionLocation::cases(),
            'statuses'  => SectionStatus::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'       => ['required', 'string', 'max:100'],
            'location'   => ['required', Rule::enum(SectionLocation::class)],
            'title'      => ['required', 'array'],
            'title.*'    => ['nullable', 'string', 'max:255'],
            'content'    => ['required', 'array'],
            'status'     => ['required', Rule::enum(SectionStatus::class)],
            'publish_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        if (($validated['status'] ?? '') === SectionStatus::Published->value) {
            $validated['published_by'] = auth('admin')->id();
        }

        $section = Section::create($validated);
        $section->saveVersion('created', auth('admin')->id(), 'Initial creation');

        return redirect()->route('admin.cms.sections.index')
            ->with('success', 'Section created successfully.');
    }

    public function show(Section $section)
    {
        return view('admin.cms.sections.show', [
            'section'  => $section,
            'versions' => $section->versions()->limit(20)->get(),
        ]);
    }

    public function edit(Section $section)
    {
        return view('admin.cms.sections.edit', [
            'section'   => $section,
            'locations' => SectionLocation::cases(),
            'statuses'  => SectionStatus::cases(),
            'versions'  => $section->versions()->limit(10)->get(),
        ]);
    }

    public function update(Request $request, Section $section)
    {
        $validated = $request->validate([
            'location'   => ['required', Rule::enum(SectionLocation::class)],
            'title'      => ['required', 'array'],
            'title.*'    => ['nullable', 'string', 'max:255'],
            'content'    => ['required', 'array'],
            'status'     => ['required', Rule::enum(SectionStatus::class)],
            'publish_at' => ['nullable', 'date_format:Y-m-d H:i'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $validated['updated_by'] = auth('admin')->id();

        if (($validated['status'] ?? '') === SectionStatus::Published->value && !$section->published_by) {
            $validated['published_by'] = auth('admin')->id();
        }

        // Save version BEFORE updating
        $section->saveVersion('updated', auth('admin')->id(), $request->input('change_summary'));

        $section->update($validated);

        return redirect()->route('admin.cms.sections.edit', $section)
            ->with('success', 'Section updated successfully.');
    }

    public function destroy(Section $section)
    {
        $section->delete();

        return redirect()->route('admin.cms.sections.index')
            ->with('success', 'Section deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:sections,id'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->order as $position => $id) {
                Section::where('id', $id)->update(['sort_order' => $position]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Restore a section to a specific version.
     */
    public function restoreVersion(Section $section, SectionVersion $version)
    {
        abort_unless($version->section_id === $section->id, 403);

        // Save current state before restoring
        $section->saveVersion('updated', auth('admin')->id(), 'Auto-saved before restore');

        $section->restoreFromVersion($version);
        $section->saveVersion('restored', auth('admin')->id(), "Restored to version #{$version->id}");

        return redirect()->route('admin.cms.sections.edit', $section)
            ->with('success', "Section restored to version #{$version->id}.");
    }

    /**
     * Live preview of section HTML (AJAX).
     */
    public function preview(Request $request, Section $section)
    {
        $content = $request->input('content', []);
        $lang    = $request->input('lang', app()->getLocale());

        try {
            $html = view('admin.cms.sections.preview-fragment', [
                'section' => $section,
                'content' => $content[$lang] ?? [],
                'lang'    => $lang,
            ])->render();

            return response()->json([
                'success' => true,
                'html'    => $html,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
