<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Live incident (2026-08-11): several migrations added during the ~100k-scale
 * performance pass (see CHANGELOG 1.0.16-1.0.18) weren't idempotent (rule
 * #42, already the established convention for every other migration in this
 * app — see UpdateSystemSchemaTest). A database restore triggered by ANY failed
 * update's rollback can leave a migration's row reverted while the schema
 * change it made (a new table, column, or index) survives untouched, since
 * the restore only replays tables present in its snapshot — a bare re-run
 * then fails "already exists" and blocks every future update attempt. Two
 * of these (admin_sessions, the OEM FULLTEXT index) actually did this live
 * on the one production install. This sweeps every migration from that
 * batch, confirming a second up() is a safe no-op — not just the two that
 * already blew up.
 */
class PerformancePassMigrationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATIONS = [
        '2026_08_10_000001_add_unique_index_to_newsletter_campaign_recipients.php',
        '2026_08_10_000002_create_admin_sessions_table.php',
        '2026_08_10_000003_add_guest_email_to_carts_table.php',
        '2026_08_10_000006_add_manufacturer_and_car_model_to_failed_search_logs_table.php',
        '2026_08_10_000007_add_rating_range_check_to_testimonials_table.php',
        '2026_08_10_000008_add_scale_performance_indexes_to_products_table.php',
    ];

    #[Test]
    public function every_migration_in_this_batch_is_idempotent(): void
    {
        // RefreshDatabase already ran every migration once. Re-running up()
        // for each must not throw.
        foreach (self::MIGRATIONS as $file) {
            $migration = require database_path('migrations/'.$file);
            $migration->up();
        }

        $this->assertTrue(true, 'no migration threw on a second up()');
    }

    #[Test]
    public function admin_sessions_table_survives_a_repeated_up(): void
    {
        $migration = require database_path('migrations/2026_08_10_000002_create_admin_sessions_table.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('admin_sessions'));
        $this->assertTrue(Schema::hasColumns('admin_sessions', ['id', 'admin_id', 'session_id', 'created_at']));
    }

    #[Test]
    public function guest_email_column_survives_a_repeated_up(): void
    {
        $migration = require database_path('migrations/2026_08_10_000003_add_guest_email_to_carts_table.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('carts', 'guest_email'));
    }

    #[Test]
    public function newsletter_recipients_unique_index_survives_a_repeated_up(): void
    {
        $migration = require database_path('migrations/2026_08_10_000001_add_unique_index_to_newsletter_campaign_recipients.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('newsletter_campaign_recipients', ['campaign_id', 'subscriber_id'], 'unique'));
    }

    #[Test]
    public function products_performance_indexes_survive_a_repeated_up(): void
    {
        $migration = require database_path('migrations/2026_08_10_000008_add_scale_performance_indexes_to_products_table.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('products', ['created_at']));
        $this->assertTrue(Schema::hasIndex('products', ['is_active', 'is_in_stock']));
    }
}
