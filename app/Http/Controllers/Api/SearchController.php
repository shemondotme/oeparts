<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Autocomplete search results.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $lang = (string) $request->query('lang', 'en');
        if (! in_array($lang, ['en', 'de', 'lt', 'fr', 'es'], true)) {
            $lang = 'en';
        }
        $minChars = (int) settings('search.min_chars', 3);
        $maxResults = (int) settings('search.autocomplete_count', 5);

        // Require minimum characters
        if (strlen($query) < $minChars) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        // Rate limit autocomplete requests (30 per minute per IP)
        if (!RateLimiter::attempt("search:autocomplete:{$request->ip()}", 30, function () {
            return true;
        }, 60)) {
            throw new TooManyRequestsHttpException(60, 'Too many search requests. Please slow down.');
        }

        $results = $this->searchService->autocomplete($query, $lang, $maxResults);

        return response()->json([
            'success' => true,
            'suggestions' => $results,
        ]);
    }
}
