<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function show(Request $request, string $lang, string $slug)
    {
        // Eager-load the featured image the view renders (avoids a lazy follow-up query).
        $page = Page::with('featuredImage')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('frontend.page', compact('page'));
    }
}
