<?php

namespace App\Http\Controllers\Api;

use App\Services\SectionRendererService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends BaseApiController
{
    public function __construct(
        private SectionRendererService $sectionRenderer
    ) {}

    /**
     * Get homepage sections as JSON.
     */
    public function homepage(Request $request, string $lang): JsonResponse
    {
        $sections = $this->sectionRenderer->getSections('homepage');

        return response()->json([
            'success' => true,
            'sections' => $sections,
        ]);
    }

    /**
     * Get landing page sections as JSON.
     */
    public function landing(Request $request, string $lang): JsonResponse
    {
        $sections = $this->sectionRenderer->getSections('landing');

        return response()->json([
            'success' => true,
            'sections' => $sections,
        ]);
    }
}
