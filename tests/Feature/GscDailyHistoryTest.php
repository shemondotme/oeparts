<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoHealthDashboard;
use App\Models\Admin;
use App\Models\GscDailyStat;
use App\Models\Setting;
use App\Services\GoogleSearchConsoleService;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Daily Search Console totals are stored (one row per date, refreshed in
 * place) so the dashboard can answer "up or down since last week?".
 */
class GscDailyHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function configureGsc(): void
    {
        foreach (['gsc_client_id' => 'cid', 'gsc_client_secret' => 'secret', 'gsc_refresh_token' => 'refresh', 'gsc_property_url' => 'https://example.org/'] as $key => $value) {
            Setting::updateOrCreate(['group' => 'seo', 'key' => $key], ['value' => $value, 'type' => 'string', 'is_encrypted' => false]);
        }
        app(SettingsService::class)->forget('seo');
    }

    private function fakeApi(array $rows): void
    {
        // A fresh client each time: when fake() is called twice, the FIRST
        // matching stub keeps answering, so a second scenario would never be seen.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'searchconsole.googleapis.com/*' => Http::response(['rows' => $rows]),
        ]);
    }

    #[Test]
    public function the_snapshot_stores_one_row_per_day(): void
    {
        $this->configureGsc();
        $this->fakeApi([
            ['keys' => ['2026-11-01'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.0353, 'position' => 8.4],
            ['keys' => ['2026-11-02'], 'clicks' => 20, 'impressions' => 400, 'ctr' => 0.05, 'position' => 7.9],
        ]);

        $result = app(GoogleSearchConsoleService::class)->snapshotDaily(7);

        $this->assertSame(['stored' => 2], $result);
        $this->assertDatabaseHas('gsc_daily_stats', ['stat_date' => '2026-11-01 00:00:00', 'clicks' => 12, 'impressions' => 340]);
        $this->assertSame(2, GscDailyStat::count());
    }

    #[Test]
    public function running_it_again_refreshes_the_same_days_instead_of_duplicating_them(): void
    {
        $this->configureGsc();
        $this->fakeApi([['keys' => ['2026-11-01'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.03, 'position' => 8.4]]);
        app(GoogleSearchConsoleService::class)->snapshotDaily(7);

        // Google revises recent days as data settles.
        $this->fakeApi([['keys' => ['2026-11-01'], 'clicks' => 15, 'impressions' => 360, 'ctr' => 0.04, 'position' => 8.1]]);
        app(GoogleSearchConsoleService::class)->snapshotDaily(7);

        $this->assertSame(1, GscDailyStat::count());
        $this->assertSame(15, GscDailyStat::first()->clicks);
    }

    #[Test]
    public function an_api_failure_stores_nothing_and_the_command_still_succeeds(): void
    {
        $this->configureGsc();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'searchconsole.googleapis.com/*' => Http::response('nope', 500),
        ]);

        $this->assertSame(0, Artisan::call('gsc:snapshot'));
        $this->assertSame(0, GscDailyStat::count());
        $this->assertStringContainsString('skipped', Artisan::output());
    }

    #[Test]
    public function the_command_does_nothing_when_search_console_is_not_configured(): void
    {
        Http::fake();

        $this->assertSame(0, Artisan::call('gsc:snapshot'));
        $this->assertStringContainsString('not configured', Artisan::output());
        Http::assertNothingSent();
    }

    #[Test]
    public function the_dashboard_compares_the_last_14_days_with_the_14_before(): void
    {
        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        for ($i = 0; $i < 28; $i++) {
            GscDailyStat::create([
                'stat_date' => now()->subDays($i + 3)->toDateString(),
                'clicks' => $i < 14 ? 20 : 10,      // last 14 days: 280 clicks, the 14 before: 140
                'impressions' => $i < 14 ? 200 : 400,
                'ctr' => 0.1, 'position' => 8.0, 'recorded_at' => now(),
            ]);
        }

        $history = Livewire::test(SeoHealthDashboard::class)->instance()->gscHistory();

        $this->assertTrue($history['hasData']);
        $this->assertSame(280, $history['recent']['clicks']);
        $this->assertSame(140, $history['previous']['clicks']);
        $this->assertSame(100, $history['clicksChange']);        // +100 %
        $this->assertSame(-50, $history['impressionsChange']);   // -50 %
        $this->assertCount(14, $history['rows']);
    }

    #[Test]
    public function with_less_than_28_days_of_data_no_change_is_claimed(): void
    {
        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        for ($i = 0; $i < 10; $i++) {
            GscDailyStat::create(['stat_date' => now()->subDays($i + 3)->toDateString(), 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 9, 'recorded_at' => now()]);
        }

        $history = Livewire::test(SeoHealthDashboard::class)->instance()->gscHistory();

        $this->assertTrue($history['hasData']);
        $this->assertNull($history['previous']);
        $this->assertNull($history['clicksChange']);
    }

    #[Test]
    public function the_dashboard_has_no_history_block_content_before_anything_is_stored(): void
    {
        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $this->assertFalse(Livewire::test(SeoHealthDashboard::class)->instance()->gscHistory()['hasData']);
    }
}
