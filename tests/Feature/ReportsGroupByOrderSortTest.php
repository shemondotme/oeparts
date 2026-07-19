<?php

namespace Tests\Feature;

use App\Filament\Widgets\Reports\CustomersTop;
use App\Filament\Widgets\Reports\SalesTopProducts;
use App\Filament\Widgets\Reports\SearchFailedQueries;
use App\Filament\Widgets\Reports\SearchTopSearches;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Confirmed live: visiting the Sales Report page threw a real 500 under MySQL
 * (SQLSTATE[42000] ... sql_mode=only_full_group_by) — Filament's TableWidget
 * appends "ORDER BY {table}.id" for pagination-stable sorting unless told not
 * to (Table::hasDefaultKeySort(), default true), but these widgets' GROUP BY
 * queries only SELECT an aggregated MIN(id)/COALESCE(...) alias, not the raw
 * id column, so the appended ORDER BY is a genuine MySQL syntax error.
 *
 * The test suite runs on SQLite (.env.testing), which does not enforce
 * ONLY_FULL_GROUP_BY — Livewire::test($page)->assertOk() (already covered by
 * ReportsWidgetCacheTest) therefore cannot catch this class of bug at all;
 * these assertions check the actual mechanism (hasDefaultKeySort() disabled)
 * instead of relying on a specific database engine's strictness.
 */
class ReportsGroupByOrderSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\SettingsSeeder::class, \Database\Seeders\RolesSeeder::class]);

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function group_by_widgets_disable_the_default_id_order_that_mysql_would_reject(): void
    {
        $widgets = [SalesTopProducts::class, SearchFailedQueries::class, SearchTopSearches::class];
        $failures = [];

        foreach ($widgets as $widget) {
            $hasDefaultKeySort = Livewire::test($widget)->instance()->getTable()->hasDefaultKeySort();

            if ($hasDefaultKeySort) {
                $failures[] = $widget;
            }
        }

        $this->assertSame([], $failures, "These widgets still have Filament's default id-based ORDER BY enabled, which MySQL's only_full_group_by rejects since their GROUP BY doesn't include the raw id column:\n" . implode("\n", $failures));
    }

    #[Test]
    public function customers_top_is_safe_without_the_fix_because_it_groups_by_the_real_id_column(): void
    {
        // Not asserting defaultKeySort here — this widget's GROUP BY includes
        // users.id itself (the real primary key), so Filament's appended
        // "ORDER BY users.id" is always valid regardless. Documents why this
        // sibling widget, which has the exact same shape otherwise, was
        // correctly left untouched by the fix.
        Livewire::test(CustomersTop::class)->assertOk();
    }
}
