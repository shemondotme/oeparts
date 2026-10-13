<?php

namespace Tests\Unit\Migration;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\Resolution;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.4: references the foreign keys cannot see (a logo id in settings, picture paths in a column)
 * produce a warning when the thing they point at is left out.
 */
class LogicalRefRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function resolution(array $groups): Resolution
    {
        return (new DependencyResolver(new GroupRegistry, new FkGraph))->resolve($groups);
    }

    private function registry(?\Closure $estimate = null, int $threshold = LogicalRefRegistry::EXACT_BELOW_ROWS): LogicalRefRegistry
    {
        return new LogicalRefRegistry(new GroupRegistry, $threshold, $estimate);
    }

    /** @return array<int, string> */
    private function ids(array $warnings): array
    {
        return array_map(fn ($w): string => $w->ref->id, $warnings);
    }

    private function setSetting(string $group, string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(['group' => $group, 'key' => $key], ['value' => $value, 'type' => 'string']);
    }

    private function product(): int
    {
        return Product::factory()->create()->id;
    }

    // ---- the entries are real ----------------------------------------------------------------

    #[Test]
    public function every_built_in_entry_names_a_column_and_a_setting_that_really_exist(): void
    {
        $this->seed(SettingsSeeder::class);
        $groups = new GroupRegistry;

        foreach (LogicalRefRegistry::builtIn() as $ref) {
            if ($ref->kind === 'setting') {
                [$group, $key] = explode('.', $ref->subject, 2);
                $this->assertTrue(DB::table('settings')->where('group', $group)->where('key', $key)->exists(), "setting {$ref->subject} is gone");
            } else {
                $this->assertTrue(Schema::hasColumn($ref->subject, (string) $ref->column), "{$ref->subject}.{$ref->column} is gone");
                $this->assertContains($ref->subject, $groups->get($ref->from)->tables, "{$ref->subject} is not in group {$ref->from->value}");
            }

            $this->assertNotSame($ref->from, $ref->target);
        }
    }

    #[Test]
    public function every_entry_has_plain_english_text_with_the_count_placeholder(): void
    {
        $catalogue = require lang_path('en/migration.php');

        foreach (LogicalRefRegistry::builtIn() as $ref) {
            $text = $catalogue['logical'][$ref->id] ?? '';
            $this->assertNotSame('', $text, $ref->id);
            $this->assertSame('migration.logical.'.$ref->id, $ref->messageKey());
        }

        $this->assertSame(array_map(fn ($r) => $r->id, LogicalRefRegistry::builtIn()), array_keys($catalogue['logical']), 'no orphan text');
    }

    // ---- warnings ------------------------------------------------------------------------------

    #[Test]
    public function nothing_to_warn_about_on_an_empty_site(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertSame([], $this->registry()->warningsFor($this->resolution([GroupKey::Catalog, GroupKey::Sales])));
    }

    #[Test]
    public function a_logo_that_points_into_a_media_library_that_is_not_travelling_warns(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->setSetting('general', 'logo_id', '12');

        $warnings = $this->registry()->warningsFor($this->resolution([GroupKey::Catalog]));
        $this->assertSame(['site_logo'], $this->ids($warnings));
        $this->assertSame(1, $warnings[0]->count);

        $this->assertSame([], $this->registry()->warningsFor($this->resolution([GroupKey::Catalog, GroupKey::Media])), 'with the media library the logo travels');
    }

    #[Test]
    public function an_empty_setting_is_not_a_reference(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->setSetting('general', 'logo_id', '   ');

        $this->assertSame([], $this->registry()->warningsFor($this->resolution([GroupKey::Catalog])));
    }

    #[Test]
    public function product_pictures_without_their_files_warn_with_an_exact_count(): void
    {
        $product = $this->product();
        DB::table('product_images')->insert([
            ['product_id' => $product, 'path' => 'products/a.jpg', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $product, 'path' => 'products/b.jpg', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $product, 'path' => '', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $warnings = $this->registry()->warningsFor($this->resolution([GroupKey::Catalog]));

        $this->assertSame(['product_pictures'], $this->ids($warnings));
        $this->assertSame(2, $warnings[0]->count, 'the empty path is not a picture');
        $this->assertFalse($warnings[0]->approximate);

        $this->assertSame([], $this->registry()->warningsFor($this->resolution([GroupKey::Catalog, GroupKey::CatalogFiles])));
    }

    #[Test]
    public function no_warning_when_the_group_holding_the_reference_is_not_exported_at_all(): void
    {
        $product = $this->product();
        DB::table('product_images')->insert(['product_id' => $product, 'path' => 'p.jpg', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([], $this->registry()->warningsFor($this->resolution([GroupKey::Media])));
    }

    #[Test]
    public function a_big_table_is_reported_by_its_estimate_and_marked_approximate(): void
    {
        $product = $this->product();
        DB::table('product_images')->insert(['product_id' => $product, 'path' => 'p.jpg', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $registry = $this->registry(fn (string $table): int => $table === 'product_images' ? 3_500_000 : 0);
        $warnings = $registry->warningsFor($this->resolution([GroupKey::Catalog]));

        $this->assertSame(3_500_000, $warnings[0]->count);
        $this->assertTrue($warnings[0]->approximate);
        $this->assertSame('about ', $warnings[0]->params()['approximate']);
    }

    #[Test]
    public function the_threshold_between_exact_and_approximate_is_the_configured_one(): void
    {
        $product = $this->product();
        DB::table('product_images')->insert(['product_id' => $product, 'path' => 'p.jpg', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $below = $this->registry(fn () => 199_999)->warningsFor($this->resolution([GroupKey::Catalog]));
        $at = $this->registry(fn () => 200_000)->warningsFor($this->resolution([GroupKey::Catalog]));

        $this->assertFalse($below[0]->approximate);
        $this->assertSame(1, $below[0]->count);
        $this->assertTrue($at[0]->approximate);
    }

    #[Test]
    public function refund_pictures_and_payment_proofs_warn_when_sales_files_are_left_out(): void
    {
        $order = Order::factory()->create();
        RefundRequest::factory()->create(['order_id' => $order->id, 'return_images' => ['refunds/a.jpg', 'refunds/b.jpg']]);
        RefundRequest::factory()->create(['order_id' => $order->id, 'return_images' => []]);
        RefundRequest::factory()->create(['order_id' => $order->id, 'return_images' => null]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => ['method' => 'bank_transfer', 'proof_path' => 'proofs/p1.pdf']]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => ['method' => 'bank_transfer', 'proof_path' => null]]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => ['method' => 'bank_transfer', 'proof_path' => '']]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => null]);

        $warnings = $this->registry()->warningsFor($this->resolution([GroupKey::Sales]));
        $byId = array_column(array_map(fn ($w) => ['id' => $w->ref->id, 'count' => $w->count], $warnings), 'count', 'id');

        $this->assertSame(['refund_pictures' => 1, 'payment_proofs' => 1], $byId, 'only rows that really carry a file path count');

        $this->assertSame([], $this->registry()->warningsFor($this->resolution([GroupKey::Sales, GroupKey::SalesFiles])));
    }

    #[Test]
    public function an_operator_can_add_a_reference_in_config_and_a_bad_entry_is_refused(): void
    {
        config(['migration.export.json_refs' => [[
            'id' => 'custom_banner', 'table' => 'sections', 'column' => 'content', 'json_path' => 'image', 'from' => 'content', 'target' => 'media',
        ]]]);

        $ids = array_map(fn ($r) => $r->id, $this->registry()->all());
        $this->assertContains('custom_banner', $ids);
        $this->assertSame('image', collect($this->registry()->all())->firstWhere('id', 'custom_banner')->jsonPath);

        foreach ([
            ['id' => 'x', 'table' => 'no_such_table', 'column' => 'c', 'from' => 'content', 'target' => 'media'],
            ['id' => 'X Y', 'table' => 'sections', 'column' => 'content', 'from' => 'content', 'target' => 'media'],
            ['id' => 'x', 'table' => 'sections', 'column' => 'content; drop table users', 'from' => 'content', 'target' => 'media'],
            ['id' => 'x', 'table' => 'sections', 'column' => 'content', 'json_path' => 'a.b', 'from' => 'content', 'target' => 'media'],
            ['id' => 'x', 'table' => 'sections', 'column' => 'content'],
        ] as $bad) {
            config(['migration.export.json_refs' => [$bad]]);

            try {
                $this->registry()->all();
                $this->fail('accepted '.json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
