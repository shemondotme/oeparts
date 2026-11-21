<?php

namespace Tests\Unit;

use App\Services\Migration\Import\Recovery\RecoveryPoint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Just before it closes the site an import re-counts every table of the way back and stops when one grew. A shop that
 * still has visitors always writes log rows meanwhile, so the 2026-11-21 live import stopped on search_logs until the
 * site was put in maintenance mode first. Logs, snapshots, sessions, caches and queue tables are not compared; business
 * tables always are.
 */
class RecoveryPointVolatileTablesTest extends TestCase
{
    #[Test]
    public function log_and_system_tables_are_not_compared(): void
    {
        $volatile = RecoveryPoint::volatileTables();

        foreach (['search_logs', 'not_found_logs', 'cron_logs', 'ai_crawler_hits', 'health_check_snapshots', 'sessions', 'jobs', 'cache'] as $table) {
            $this->assertContains($table, $volatile, "{$table} changes by itself on a live site");
        }
    }

    #[Test]
    public function business_tables_are_always_compared(): void
    {
        $volatile = RecoveryPoint::volatileTables();

        foreach (['products', 'orders', 'order_items', 'payments', 'users', 'admins', 'settings', 'pages', 'blog_posts', 'invoices', 'inventory_logs'] as $table) {
            $this->assertNotContains($table, $volatile, "{$table} must still stop an import when it changes");
        }
    }
}
