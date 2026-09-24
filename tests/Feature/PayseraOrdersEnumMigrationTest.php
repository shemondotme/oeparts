<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 5 (Data Integrity & Migration Safety) — verifies
 * 2026_09_16_000001_add_paysera_to_orders_payment_method_enum.php against the
 * exact failure mode CLAUDE.md rule #42 exists for (see
 * add_slug_to_products_table's doc comment / commit f1fc67f): a restore or
 * interrupted prior update can leave the `migrations` tracking table out of
 * sync with the live schema even though each individually looks consistent.
 *
 * Empirically re-verified against real MySQL during Phase 5 (not just this
 * SQLite-backed suite, which can't reproduce MySQL's own ENUM/ALTER
 * semantics): deleting this migration's tracking row and re-running
 * `artisan migrate` succeeds cleanly (MODIFY COLUMN to an identical enum
 * definition is naturally idempotent at the SQL level, unlike ADD COLUMN/
 * CREATE TABLE — this migration doesn't need SafeSchema's explicit
 * already-exists catch). Also verified live: rolling back with a real order
 * using payment_method='paysera' fails LOUDLY (a data-truncation
 * QueryException, migration marked FAIL) with zero data corruption — the
 * schema and the order's payment_method are both provably untouched
 * afterward. That's accepted, not a bug: matches this codebase's own
 * precedent (2026_08_09_000001_add_paysera_to_payments_gateway_enum.php's
 * down() comment: "a 'paysera' row present at rollback time would be a
 * genuine data conflict either way") — failing loudly beats silently
 * discarding which gateway a real order was paid through.
 */
class PayseraOrdersEnumMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_FILE = '2026_09_16_000001_add_paysera_to_orders_payment_method_enum.php';

    #[Test]
    public function up_is_idempotent(): void
    {
        // The migration already ran (RefreshDatabase). Re-running up() must
        // not throw — MODIFY COLUMN to an identical enum definition is a
        // no-op at the SQL level, so this migration needs no explicit guard.
        $migration = require database_path('migrations/'.self::MIGRATION_FILE);
        $migration->up();

        $this->assertTrue(Schema::hasColumn('orders', 'payment_method'));
    }

    #[Test]
    public function paysera_is_a_valid_payment_method_after_the_migration(): void
    {
        $order = Order::factory()->create(['payment_method' => 'paysera']);

        $this->assertSame('paysera', $order->fresh()->payment_method->value);
    }

    #[Test]
    public function down_reverts_cleanly_when_no_order_uses_paysera(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION_FILE);

        $migration->down();

        // Proof down() actually narrowed the enum back (not just "didn't
        // throw"): a 'paysera' order must now be rejected at the DB level.
        $this->expectException(QueryException::class);

        try {
            Order::factory()->create(['payment_method' => 'paysera']);
        } finally {
            // Re-apply regardless of outcome, so the rest of the suite isn't
            // left on the narrowed enum.
            $migration->up();
        }
    }

    #[Test]
    public function down_fails_loudly_without_corrupting_data_when_a_paysera_order_exists(): void
    {
        $order = Order::factory()->create(['payment_method' => 'paysera']);
        $migration = require database_path('migrations/'.self::MIGRATION_FILE);

        try {
            $migration->down();
            $this->fail('down() should have thrown on live paysera data, not succeeded silently.');
        } catch (QueryException) {
            // Expected — see class docblock. The real assertion is what
            // follows: nothing was corrupted by the failed attempt.
        }

        $this->assertSame('paysera', $order->fresh()->payment_method->value);
        $this->assertTrue(Schema::hasColumn('orders', 'payment_method'));
    }
}
