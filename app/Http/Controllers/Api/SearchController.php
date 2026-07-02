<?php

namespace App\Http\Controllers\Api;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class SearchController extends ApiController
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
        $rawSupportedLanguages = settings('search.supported_languages', ['en', 'de', 'lt', 'fr', 'es']);
        $supportedLanguages = is_string($rawSupportedLanguages)
            ? (json_decode($rawSupportedLanguages, true) ?: ['en', 'de', 'lt', 'fr', 'es'])
            : (array) $rawSupportedLanguages;
        if (! in_array($lang, $supportedLanguages, true)) {
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
        $maxSearches = (int) settings('search.rate_limit_per_minute', 30);
        if (!RateLimiter::attempt("search:autocomplete:{$request->ip()}", $maxSearches, function () {
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
