<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\SectionVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CmsSectionController extends Controller
{
    public function preview(Section $section, Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|array',
            'lang' => 'required|string|size:2',
        ]);

        $lang = $data['lang'];
        $content = $data['content'];
        $headline = $content[$lang]['headline'] ?? '';
        $description = $content[$lang]['description'] ?? '';

        $html = view('admin.cms.section-preview', compact('headline', 'description', 'lang'))->render();

        return response()->json(['success' => true, 'html' => $html]);
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || $admin->cannot('edit sections')) {
            abort(403, 'Unauthorized.');
        }

        $data = $request->validate([
            'location' => 'required|string|in:homepage,landing',
            'title' => 'required|array',
            'content' => 'required|array',
            'status' => 'required|string|in:draft,published,scheduled,archived',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer',
            'change_summary' => 'nullable|string|max:500',
        ]);

        $section->update($data);

        $section->saveVersion(
            'updated',
            $admin->id,
            $request->input('change_summary', 'Updated via admin')
        );

        return redirect()->back();
    }

    public function restoreVersion(Section $section, SectionVersion $version): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || $admin->cannot('edit sections')) {
            abort(403, 'Unauthorized.');
        }

        $section->restoreFromVersion($version);
        $section->saveVersion('restored', $admin->id, 'Restored from version #'.$version->id);

        return redirect()->back();
    }
}
