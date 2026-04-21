<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\SectionRendererService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, string $lang)
    {
        $renderer = app(SectionRendererService::class);
        $sections  = $renderer->getSections('homepage');
        $sectionData = $renderer->buildSectionData($sections);

        return view('frontend.home', compact('sections', 'sectionData'));
    }
}
