<?php

namespace Tests\Feature;

use App\Models\FailedSearchLog;
use App\Models\SearchLog;
use App\Filament\Resources\FailedSearchLogResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SearchLog/FailedSearchLog set $timestamps = false with no created_at ever
 * written explicitly, so every logged row silently persisted a NULL
 * created_at — breaking the "popular OEMs" zero-results suggestion query,
 * the admin log's default sort, and the "failed searches today" nav badge,
 * all of which filter/sort on created_at.
 */
class SearchLogTimestampTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_log_stamps_created_at_on_creation(): void
    {
        $log = SearchLog::create([
            'search_query' => 'ABC123',
            'normalized_query' => 'ABC123',
            'result_count' => 1,
            'lang' => 'en',
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertNotNull($log->fresh()->created_at);
    }

    #[Test]
    public function failed_search_log_stamps_created_at_on_creation(): void
    {
        $log = FailedSearchLog::create([
            'search_query' => 'NOTFOUND',
            'normalized_query' => 'NOTFOUND',
            'lang' => 'en',
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertNotNull($log->fresh()->created_at);
    }

    #[Test]
    public function failed_searches_today_navigation_badge_now_counts_todays_rows(): void
    {
        FailedSearchLog::create([
            'search_query' => 'NOTFOUND', 'normalized_query' => 'NOTFOUND',
            'lang' => 'en', 'ip_address' => '127.0.0.1',
        ]);

        $this->assertSame('1', FailedSearchLogResource::getNavigationBadge());
    }
}
