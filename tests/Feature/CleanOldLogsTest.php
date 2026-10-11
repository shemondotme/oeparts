<?php

namespace Tests\Feature;

use App\Enums\InventoryChangeType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\InventoryLog;
use App\Models\Product;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * logs:clean — the scheduled retention job for the log tables (04:00 daily).
 */
class CleanOldLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function backdated(string $table, string $column, int $daysAgo, array $row): int
    {
        return DB::table($table)->insertGetId($row + [$column => now()->subDays($daysAgo)]);
    }

    #[Test]
    public function it_deletes_old_rows_from_every_log_table_and_keeps_recent_ones(): void
    {
        $this->backdated('search_logs', 'created_at', 200, ['search_query' => 'a', 'normalized_query' => 'a', 'result_count' => 1, 'lang' => 'en', 'ip_address' => '1.1.1.1']);
        $keepSearch = $this->backdated('search_logs', 'created_at', 1, ['search_query' => 'b', 'normalized_query' => 'b', 'result_count' => 1, 'lang' => 'en', 'ip_address' => '1.1.1.1']);

        $this->backdated('failed_search_logs', 'created_at', 200, ['search_query' => 'a', 'normalized_query' => 'a', 'lang' => 'en', 'ip_address' => '1.1.1.1']);
        $this->backdated('login_logs', 'created_at', 200, ['user_type' => 'admin', 'email' => 'o@x.com', 'status' => 'success', 'ip_address' => '1.1.1.1']);

        $admin = Admin::factory()->create();
        $this->backdated('activity_logs', 'created_at', 200, ['admin_id' => $admin->id, 'action' => 'old', 'ip_address' => '1.1.1.1']);
        $this->backdated('cron_logs', 'ran_at', 200, ['job_name' => 'x', 'status' => 'success', 'duration_ms' => 1]);

        $this->backdated('email_logs', 'sent_at', 200, ['to_email' => 'o@x.com', 'subject' => 's', 'template_type' => 'welcome', 'status' => 'success']);
        $keepEmail = $this->backdated('email_logs', 'sent_at', 1, ['to_email' => 'n@x.com', 'subject' => 's', 'template_type' => 'welcome', 'status' => 'success']);

        $this->artisan('logs:clean')->assertSuccessful();

        $this->assertDatabaseCount('search_logs', 1);
        $this->assertDatabaseHas('search_logs', ['id' => $keepSearch]);
        $this->assertDatabaseCount('failed_search_logs', 0);
        $this->assertDatabaseCount('login_logs', 0);
        $this->assertSame(0, ActivityLog::where('action', 'old')->count());
        $this->assertDatabaseCount('cron_logs', 0);
        $this->assertDatabaseCount('email_logs', 1);
        $this->assertDatabaseHas('email_logs', ['id' => $keepEmail]);
    }

    #[Test]
    public function email_logs_are_aged_by_sent_at_even_though_created_at_is_always_null(): void
    {
        // Regression: the app never sets email_logs.created_at, and the old command
        // filtered on it, so this table was never pruned at all.
        $id = DB::table('email_logs')->insertGetId([
            'to_email' => 'old@x.com', 'subject' => 's', 'template_type' => 'welcome',
            'status' => 'success', 'sent_at' => now()->subDays(365), 'created_at' => null,
        ]);

        $this->artisan('logs:clean')->assertSuccessful();

        $this->assertDatabaseMissing('email_logs', ['id' => $id]);
    }

    #[Test]
    public function it_deletes_in_chunks_without_losing_rows(): void
    {
        $rows = [];
        for ($i = 0; $i < 5003; $i++) { // one full 5000 chunk + a remainder
            $rows[] = ['job_name' => 'j'.$i, 'status' => 'success', 'duration_ms' => 1, 'ran_at' => now()->subDays(400)];
        }
        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('cron_logs')->insert($batch);
        }
        $keep = $this->backdated('cron_logs', 'ran_at', 0, ['job_name' => 'recent', 'status' => 'success', 'duration_ms' => 1]);

        $this->artisan('logs:clean')->assertSuccessful();

        $this->assertDatabaseCount('cron_logs', 1);
        $this->assertDatabaseHas('cron_logs', ['id' => $keep]);
    }

    #[Test]
    public function a_retention_below_one_day_is_refused_unless_forced(): void
    {
        // A few minutes old: --days=0 sets the cutoff to "now", and a row stamped in the
        // same second would not be strictly older than it.
        $id = DB::table('cron_logs')->insertGetId(['job_name' => 'today', 'status' => 'success', 'duration_ms' => 1, 'ran_at' => now()->subMinutes(5)]);

        $this->artisan('logs:clean --days=0')->assertFailed();
        $this->artisan('logs:clean --days=-5')->assertFailed();
        $this->assertDatabaseHas('cron_logs', ['id' => $id]);

        $this->artisan('logs:clean --days=0 --force')->assertSuccessful();
        $this->assertDatabaseMissing('cron_logs', ['id' => $id]);
    }

    #[Test]
    public function inventory_logs_now_get_a_created_at_when_written(): void
    {
        $product = Product::factory()->create();

        $log = InventoryLog::create([
            'product_id' => $product->id, 'change_type' => InventoryChangeType::CsvImport,
            'old_status' => false, 'new_status' => true,
        ]);

        $this->assertNotNull($log->fresh()->created_at);
    }

    #[Test]
    public function the_backfill_migration_stamps_null_inventory_logs_with_now_never_a_past_date(): void
    {
        $product = Product::factory()->create();
        DB::table('inventory_logs')->insert([
            'product_id' => $product->id, 'change_type' => 'csv_import',
            'old_status' => 0, 'new_status' => 1, 'created_at' => null,
        ]);
        $existing = now()->subDays(30);
        DB::table('inventory_logs')->insert([
            'product_id' => $product->id, 'change_type' => 'manual',
            'old_status' => 0, 'new_status' => 1, 'created_at' => $existing,
        ]);

        $migration = require database_path('migrations/2026_10_11_000001_backfill_inventory_logs_created_at.php');
        $migration->up();

        $this->assertSame(0, DB::table('inventory_logs')->whereNull('created_at')->count());
        $stamped = DB::table('inventory_logs')->where('change_type', 'csv_import')->value('created_at');
        $this->assertTrue(Carbon::parse($stamped)->greaterThanOrEqualTo(now()->subMinute()));
        // Rows that already had a real date are untouched.
        $this->assertSame(
            $existing->format('Y-m-d'),
            Carbon::parse(DB::table('inventory_logs')->where('change_type', 'manual')->value('created_at'))->format('Y-m-d')
        );
    }
}
