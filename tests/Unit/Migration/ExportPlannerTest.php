<?php

namespace Tests\Unit\Migration;

use App\Models\Product;
use App\Security\PackagePathPolicy;
use App\Services\Migration\Export\AllowlistWalker;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FileCounter;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Groups\FileRoot;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.6: the plan an export is run from. The rules (core always, system never, scrambling drops what
 * cannot be scrubbed, freeze refused when too long) and the estimates behind them. Durations are asserted
 * as ratios of one another, never as absolute milliseconds.
 */
class ExportPlannerTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, int>|null  $rows  table => rows, for tables not listed 0 */
    private function planner(?array $rows = null, ?int $free = PHP_INT_MAX, ?\Closure $live = null, ?int $bytesPerRow = null): ExportPlanner
    {
        $registry = new GroupRegistry;
        $counts = $rows === null ? null : function (array $tables) use ($rows, $bytesPerRow): array {
            $out = [];
            foreach ($tables as $table) {
                $out[$table] = ['rows' => $rows[$table] ?? 0, 'approximate' => true, 'bytes' => $bytesPerRow === null ? null : ($rows[$table] ?? 0) * $bytesPerRow];
            }

            return $out;
        };

        return new ExportPlanner(
            $registry,
            new DependencyResolver($registry, new FkGraph),
            new LogicalRefRegistry($registry),
            new ExportEstimator($counts),
            fn (): ?int => $free,
            $live,
            fn (): int => 1_800_000_000,
        );
    }

    // ---- selection rules ----------------------------------------------------------------------

    #[Test]
    public function the_full_live_preset_plans_every_shop_group_and_nothing_risky(): void
    {
        $plan = $this->planner()->plan(Preset::FullLive);

        $this->assertSame('full_live', $plan->preset);
        $this->assertCount(12, $plan->groups);
        $this->assertNotContains('system', $plan->groups);
        $this->assertNotContains('logs', $plan->groups);
        $this->assertFalse($plan->anonymise);
        $this->assertFalse($plan->includeSecrets);
        $this->assertFalse($plan->resetSequences);
        $this->assertFalse($plan->hold, 'a live copy has nothing to hold');
        $this->assertSame('live', $plan->consistency);
        $this->assertTrue($plan->isRunnable());
        $this->assertSame(ExportPlan::SEO_ALL, $plan->seoSlice);
        $this->assertContains('public:media', $plan->fileRoots);
        $this->assertContains('local:payment-proofs', $plan->fileRoots);
        $this->assertSame('1800000000', (string) $plan->createdAt);
    }

    #[Test]
    public function core_is_always_planned_and_system_tables_never_are(): void
    {
        $plan = $this->planner()->plan([GroupKey::Media]);

        $this->assertSame(['core', 'media'], $plan->groups);
        $this->assertContains('settings', $plan->tables);
        $this->assertContains('media_files', $plan->tables);

        foreach ((new GroupRegistry)->systemTables() as $system) {
            $this->assertNotContains($system, $plan->tables);
        }
    }

    #[Test]
    public function scrambling_forces_secrets_out_even_when_asked_for_and_drops_what_cannot_be_scrubbed(): void
    {
        $plan = $this->planner()->plan(
            [GroupKey::Catalog, GroupKey::Sales, GroupKey::SalesFiles, GroupKey::Carts, GroupKey::Logs, GroupKey::Customers],
            new ExportOptions(includeSecrets: true, anonymise: true)
        );

        $this->assertTrue($plan->anonymise);
        $this->assertFalse($plan->includeSecrets);
        $this->assertNotContains('logs', $plan->groups);
        $this->assertNotContains('carts', $plan->groups);
        $this->assertNotContains('sales_files', $plan->groups);
        $this->assertContains('sales', $plan->groups);
        $this->assertEqualsCanonicalizing(['sales_files', 'carts', 'logs'], array_column($plan->dropped, 'group'));
        $this->assertSame(['anonymise'], array_unique(array_column($plan->dropped, 'reason')));
    }

    #[Test]
    public function the_staging_preset_scrambles_and_leaves_out_invoices_and_proofs_but_a_person_can_add_invoices_back(): void
    {
        $plan = $this->planner()->plan(Preset::Staging, new ExportOptions(includeSecrets: true));

        $this->assertTrue($plan->anonymise);
        $this->assertFalse($plan->includeSecrets);
        $this->assertNotContains('invoicing', $plan->groups);
        $this->assertNotContains('sales_files', $plan->groups);

        $withInvoices = $this->planner()->plan([...Preset::Staging->groups(), GroupKey::Invoicing], new ExportOptions(anonymise: true));
        $this->assertContains('invoicing', $withInvoices->groups);
    }

    #[Test]
    public function secrets_travel_only_when_asked_for_and_not_scrambled(): void
    {
        $this->assertTrue($this->planner()->plan(Preset::FullLive, new ExportOptions(includeSecrets: true))->includeSecrets);
        $this->assertFalse($this->planner()->plan(Preset::FullLive)->includeSecrets);
    }

    #[Test]
    public function numbering_restarts_for_the_new_store_presets_and_can_be_overridden(): void
    {
        $this->assertTrue($this->planner()->plan(Preset::NewStoreSameCatalog)->resetSequences);
        $this->assertTrue($this->planner()->plan(Preset::CatalogOnly)->resetSequences);
        $this->assertFalse($this->planner()->plan(Preset::FullLive)->resetSequences);
        $this->assertFalse($this->planner()->plan([GroupKey::Catalog])->resetSequences);
        $this->assertTrue($this->planner()->plan([GroupKey::Catalog], new ExportOptions(resetSequences: true))->resetSequences);
        $this->assertFalse($this->planner()->plan(Preset::CatalogOnly, new ExportOptions(resetSequences: false))->resetSequences);
    }

    #[Test]
    public function the_shared_seo_table_is_cut_by_the_groups_that_travel(): void
    {
        $this->assertSame(ExportPlan::SEO_PRODUCT, $this->planner()->plan([GroupKey::Catalog])->seoSlice);
        $this->assertSame(ExportPlan::SEO_OTHER, $this->planner()->plan([GroupKey::Content])->seoSlice);
        $this->assertSame(ExportPlan::SEO_ALL, $this->planner()->plan([GroupKey::Catalog, GroupKey::Content])->seoSlice);
        $this->assertNull($this->planner()->plan([GroupKey::Media])->seoSlice);
    }

    #[Test]
    public function what_a_choice_drags_in_and_what_it_cuts_are_both_in_the_plan(): void
    {
        $plan = $this->planner()->plan([GroupKey::Carts]);

        $this->assertContains('catalog', $plan->groups);
        $this->assertSame('foreign_key', $plan->added['catalog'][0]['type']);
        $this->assertArrayHasKey('abandoned_carts', $plan->nullified);
    }

    #[Test]
    public function warnings_and_unclassified_tables_are_part_of_the_plan(): void
    {
        $product = Product::factory()->create();
        DB::table('product_images')->insert(['product_id' => $product->id, 'path' => 'p.jpg', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $plan = $this->planner(null, PHP_INT_MAX, fn (): array => ['users', 'zz_new_table'])->plan([GroupKey::Catalog]);

        $this->assertSame(['product_pictures'], array_column($plan->warnings, 'id'));
        $this->assertSame(['zz_new_table'], $plan->unclassified);
    }

    // ---- estimates ----------------------------------------------------------------------------

    #[Test]
    public function more_rows_take_proportionally_longer_and_the_range_is_ordered(): void
    {
        $small = $this->planner(['products' => 1_000_000])->plan([GroupKey::Catalog])->estimate;
        $large = $this->planner(['products' => 10_000_000])->plan([GroupKey::Catalog])->estimate;

        $this->assertEqualsWithDelta($small->minutesHigh * 10, $large->minutesHigh, 10, 'ten times the rows is ten times the time, give or take rounding');
        $this->assertLessThan($small->minutesHigh, $small->minutesLow);
        $this->assertLessThan($large->minutesHigh, $large->minutesLow, 'low is below high');
        $this->assertTrue($large->rowsApproximate);
        $this->assertSame(10_000_000, $large->tables['products']['rows']);
    }

    #[Test]
    public function files_add_time_and_size_and_an_unfinished_count_is_marked_so(): void
    {
        $without = $this->planner(['products' => 10_000])->plan([GroupKey::Catalog])->estimate;
        $with = $this->planner(['products' => 10_000])->plan([GroupKey::Catalog], files: ['count' => 200_000, 'bytes' => 20 * 1024 ** 3, 'complete' => false])->estimate;

        $this->assertGreaterThan($without->minutesHigh * 5, $with->minutesHigh);
        $this->assertGreaterThan($without->packageBytes(), $with->packageBytes());
        $this->assertFalse($with->files['complete']);
        $this->assertFalse($without->files['complete']);
    }

    #[Test]
    public function the_space_an_export_needs_is_a_margin_over_what_it_writes_plus_a_volume_and_headroom(): void
    {
        $estimate = $this->planner(['products' => 1_000_000], bytesPerRow: 500)->plan([GroupKey::Catalog], files: ['count' => 10, 'bytes' => 10 * 1024 ** 3, 'complete' => true])->estimate;
        $written = $estimate->packageBytes();
        $config = config('migration.export');

        $expected = (int) ceil($config['free_space_factor'] * $written) + $config['volume_bytes'] + $config['min_free_bytes'];

        $this->assertSame($expected, $estimate->requiredFreeBytes);
        $this->assertGreaterThan($written, $estimate->requiredFreeBytes);
    }

    #[Test]
    public function on_sqlite_the_row_counts_are_exact(): void
    {
        Product::factory()->count(3)->create();

        $estimate = (new ExportEstimator)->estimate((new DependencyResolver(new GroupRegistry, new FkGraph))->resolve([GroupKey::Catalog]));

        $this->assertSame(3, $estimate->tables['products']['rows']);
        $this->assertFalse($estimate->tables['products']['approximate']);
        $this->assertFalse($estimate->rowsApproximate);
    }

    // ---- freeze ------------------------------------------------------------------------------

    #[Test]
    public function a_freeze_cap_is_the_configured_minimum_for_a_small_site(): void
    {
        $plan = $this->planner(['products' => 1000])->plan([GroupKey::Catalog], new ExportOptions(consistency: 'freeze'));

        $this->assertSame(config('migration.export.freeze_max_minutes'), $plan->freezeCapMinutes);
        $this->assertTrue($plan->isFreeze());
        $this->assertTrue($plan->hold, 'a freeze holds after sealing by default');
        $this->assertSame(config('migration.export.hold_auto_release_minutes'), $plan->holdReleaseMinutes);
    }

    #[Test]
    public function a_freeze_cap_grows_with_the_estimate_to_one_and_a_half_times_the_pessimistic_figure(): void
    {
        $plan = $this->planner(['products' => 20_000_000])->plan([GroupKey::Catalog], new ExportOptions(consistency: 'freeze'));

        $this->assertGreaterThan(config('migration.export.freeze_max_minutes'), $plan->freezeCapMinutes);
        $this->assertSame((int) ceil(1.5 * $plan->estimate->minutesHigh), $plan->freezeCapMinutes);
        $this->assertTrue($plan->isRunnable());
    }

    #[Test]
    public function a_freeze_that_would_take_too_long_is_refused_but_the_live_copy_of_the_same_site_is_not(): void
    {
        $rows = ['products' => 80_000_000];

        $freeze = $this->planner($rows)->plan([GroupKey::Catalog], new ExportOptions(consistency: 'freeze'));
        $live = $this->planner($rows)->plan([GroupKey::Catalog]);

        $this->assertFalse($freeze->isRunnable());
        $this->assertSame('freeze_too_long', $freeze->blockers[0]['code']);
        $this->assertSame($freeze->estimate->minutesHigh, $freeze->blockers[0]['params']['minutes']);
        $this->assertTrue($live->isRunnable());
    }

    #[Test]
    public function the_hold_can_be_switched_off_or_given_its_own_time(): void
    {
        $off = $this->planner()->plan([GroupKey::Media], new ExportOptions(consistency: 'freeze', hold: false));
        $custom = $this->planner()->plan([GroupKey::Media], new ExportOptions(consistency: 'freeze', holdReleaseMinutes: 45));

        $this->assertFalse($off->hold);
        $this->assertSame(45, $custom->holdReleaseMinutes);
    }

    // ---- disk ---------------------------------------------------------------------------------

    #[Test]
    public function not_enough_free_disk_blocks_the_plan_and_says_how_much_is_needed(): void
    {
        $short = $this->planner(['products' => 1_000_000], free: 1024 * 1024)->plan([GroupKey::Catalog]);

        $this->assertFalse($short->isRunnable());
        $this->assertSame('preflight_disk', $short->blockers[0]['code']);
        $this->assertSame(1024 * 1024, $short->blockers[0]['params']['free']);
        $this->assertSame($short->estimate->requiredFreeBytes, $short->blockers[0]['params']['needed']);

        $enough = $this->planner(['products' => 1_000_000], free: $short->estimate->requiredFreeBytes)->plan([GroupKey::Catalog]);
        $this->assertTrue($enough->isRunnable(), 'exactly enough is enough');
    }

    #[Test]
    public function an_unknown_amount_of_free_disk_does_not_block(): void
    {
        $this->assertTrue($this->planner(['products' => 1_000_000], free: null)->plan([GroupKey::Catalog])->isRunnable());
    }

    // ---- the plan as a stored value ------------------------------------------------------------

    #[Test]
    public function a_plan_survives_json_and_comes_back_equal(): void
    {
        $plan = $this->planner(['products' => 5000])->plan(Preset::Staging, new ExportOptions(consistency: 'freeze'));

        $stored = json_decode((string) json_encode($plan->toArray()), true);
        $again = ExportPlan::fromArray($stored);

        $this->assertSame($plan->toArray(), $again->toArray());
        $this->assertSame($plan->estimate->minutesHigh, $again->estimate->minutesHigh);
    }

    #[Test]
    public function a_plan_from_another_version_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExportPlan::fromArray(['v' => 2]);
    }

    #[Test]
    public function a_nonsense_consistency_or_hold_time_is_refused(): void
    {
        foreach ([fn () => new ExportOptions(consistency: 'sometimes'), fn () => new ExportOptions(holdReleaseMinutes: -1)] as $make) {
            try {
                $make();
                $this->fail('accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- counting files without waiting ---------------------------------------------------------

    #[Test]
    public function files_are_counted_in_slices_that_continue_where_the_last_one_stopped(): void
    {
        $base = sys_get_temp_dir().'/oe-count-'.getmypid().'-'.uniqid();
        mkdir($base.'/media/nested', 0775, true);
        foreach (range(1, 30) as $i) {
            file_put_contents($base."/media/f{$i}.jpg", str_repeat('x', 10));
        }
        file_put_contents($base.'/media/nested/deep.jpg', 'abcde');
        file_put_contents($base.'/media/shell.php', '<?php');

        try {
            $counter = new FileCounter(new AllowlistWalker(new PackagePathPolicy, ['public' => ['path' => $base, 'prefix' => 'storage/app/public']]));
            $roots = [FileRoot::parse('public:media')];

            // A clock that jumps past the deadline after one loop turn: every call does one slice of 500 entries... use many calls.
            $ticks = 0;
            $clock = function () use (&$ticks): float {
                return (float) $ticks++ * 1.0;
            };

            $state = null;
            $calls = 0;
            do {
                $result = $counter->advance($roots, $state, 1.5, $clock);
                $state = json_decode((string) json_encode($result['state']), true);
                $this->assertLessThan(50, ++$calls);
            } while (! $result['done']);

            $this->assertSame(31, $result['count'], 'the PHP file is not counted');
            $this->assertSame(305, $result['bytes']);
        } finally {
            exec('rm -rf '.escapeshellarg($base));
        }
    }
}
