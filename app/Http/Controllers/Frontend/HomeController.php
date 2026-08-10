<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\SectionRendererService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, string $lang)
    {
        // "Set as Homepage" on a Page (PageResource) used to have no effect
        // at all — the Section-based builder below was the only homepage,
        // regardless of this flag. Only one page can carry it (enforced in
        // PageResource), so this is a simple override, not a merge.
        $homepage = Page::with('featuredImage')
            ->where('is_homepage', true)
            ->where('status', ContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if ($homepage) {
            return view('frontend.page', ['page' => $homepage]);
        }

        $renderer = app(SectionRendererService::class);
        $sections  = $renderer->getSections('homepage');
        $sectionData = $renderer->buildSectionData($sections);

        return view('frontend.home', compact('sections', 'sectionData'));
    }
}
