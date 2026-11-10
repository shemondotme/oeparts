<?php

namespace App\Http\Middleware;

use App\Models\AiCrawlerHit;
use App\Services\AiCrawlerDetector;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts visits by known AI crawlers (one counter per bot per day) for the "AI Crawlers" page. Runs AFTER the
 * response is sent, never touches the response, and any failure is swallowed — counting must not cost a page view.
 *
 * Pages served from the CDN's own cache never reach this server and so are not counted.
 */
class RecordAiCrawlerVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                return;
            }

            if ($request->is('admin/*') || $request->is('api/*') || $request->is('livewire/*')) {
                return;
            }

            $bot = AiCrawlerDetector::detect($request->userAgent());
            if ($bot === null) {
                return;
            }

            if (! filter_var(settings('seo.ai_crawler_tracking', true), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            $date = now()->toDateString();
            $touched = AiCrawlerHit::query()->whereDate('hit_date', $date)->where('bot', $bot)->update([
                'hits' => \Illuminate\Support\Facades\DB::raw('hits + 1'),
                'last_seen_at' => now(),
            ]);

            if ($touched === 0) {
                try {
                    AiCrawlerHit::create(['hit_date' => $date, 'bot' => $bot, 'hits' => 1, 'last_seen_at' => now()]);
                } catch (QueryException) {
                    // Two first visits of the day at the same instant: the other one created the row.
                    AiCrawlerHit::query()->whereDate('hit_date', $date)->where('bot', $bot)->update([
                        'hits' => \Illuminate\Support\Facades\DB::raw('hits + 1'),
                        'last_seen_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable) {
            // Never let counting break a request.
        }
    }
}
