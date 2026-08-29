<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Jobs\BulkGenerateProductSeoMeta;
use App\Models\Admin;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\SeoMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SeoMetaResource was edit-one-row-at-a-time with no bulk path — on a
 * catalog with 1M+ products, fixing a templated title pattern across even
 * a filtered few thousand SKUs meant opening each one individually.
 */
class BulkGenerateProductSeoMetaTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;

    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition->id, 'price' => '129.99',
            'is_in_stock' => true, 'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function it_generates_seo_meta_from_a_template_for_every_given_product(): void
    {
        $product = $this->makeProduct();

        (new BulkGenerateProductSeoMeta(
            productIds: [$product->id],
            titleTemplate: 'Buy {oem} — {brand} — From €{min} | {site}',
            descriptionTemplate: 'Genuine {brand} part {oem}, {name}.',
            overwriteExisting: false,
            triggeredBy: 'Test Admin',
        ))->handle();

        $meta = SeoMeta::where('metable_type', Product::class)->where('metable_id', $product->id)->first();

        $this->assertNotNull($meta);
        $this->assertSame('Buy 06L906036L — Bosch — From €129.99 | OeParts', $meta->meta_title);
        $this->assertSame('Genuine Bosch part 06L906036L, Brake Pad Front.', $meta->meta_description);
    }

    #[Test]
    public function it_skips_products_with_existing_custom_meta_unless_overwrite_is_enabled(): void
    {
        $product = $this->makeProduct();
        SeoMeta::create([
            'metable_type' => Product::class, 'metable_id' => $product->id,
            'meta_title' => 'Hand-written custom title',
        ]);

        (new BulkGenerateProductSeoMeta(
            productIds: [$product->id],
            titleTemplate: 'Buy {oem}',
            descriptionTemplate: null,
            overwriteExisting: false,
            triggeredBy: 'Test Admin',
        ))->handle();

        $meta = SeoMeta::where('metable_type', Product::class)->where('metable_id', $product->id)->first();
        $this->assertSame('Hand-written custom title', $meta->meta_title);
    }

    #[Test]
    public function overwrite_existing_true_replaces_a_previously_hand_written_title(): void
    {
        $product = $this->makeProduct();
        SeoMeta::create([
            'metable_type' => Product::class, 'metable_id' => $product->id,
            'meta_title' => 'Hand-written custom title',
        ]);

        (new BulkGenerateProductSeoMeta(
            productIds: [$product->id],
            titleTemplate: 'Buy {oem}',
            descriptionTemplate: null,
            overwriteExisting: true,
            triggeredBy: 'Test Admin',
        ))->handle();

        $meta = SeoMeta::where('metable_type', Product::class)->where('metable_id', $product->id)->first();
        $this->assertSame('Buy 06L906036L', $meta->meta_title);
    }

    #[Test]
    public function only_updating_the_title_leaves_an_existing_description_untouched(): void
    {
        $product = $this->makeProduct();

        (new BulkGenerateProductSeoMeta(
            productIds: [$product->id],
            titleTemplate: null,
            descriptionTemplate: 'Original description.',
            overwriteExisting: false,
            triggeredBy: 'Test Admin',
        ))->handle();

        (new BulkGenerateProductSeoMeta(
            productIds: [$product->id],
            titleTemplate: 'A new title for {oem}',
            descriptionTemplate: null,
            overwriteExisting: true,
            triggeredBy: 'Test Admin',
        ))->handle();

        $meta = SeoMeta::where('metable_type', Product::class)->where('metable_id', $product->id)->first();
        $this->assertSame('A new title for 06L906036L', $meta->meta_title);
        $this->assertSame('Original description.', $meta->meta_description);
    }

    #[Test]
    public function the_bulk_action_dispatches_the_job_with_the_selected_product_ids(): void
    {
        Bus::fake();
        $productA = $this->makeProduct(['oem_number' => 'AAA111', 'normalized_oem' => 'AAA111']);
        $productB = $this->makeProduct(['oem_number' => 'BBB222', 'normalized_oem' => 'BBB222']);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkGenerateSeoMeta', [$productA, $productB], data: [
                'title_template' => 'Buy {oem}',
                'description_template' => '',
                'overwrite_existing' => false,
            ]);

        Bus::assertDispatched(BulkGenerateProductSeoMeta::class, fn ($job) => $job->titleTemplate === 'Buy {oem}'
            && $job->descriptionTemplate === null
            && in_array($productA->id, $job->productIds, true)
            && in_array($productB->id, $job->productIds, true));
    }

    #[Test]
    public function the_bulk_action_warns_instead_of_dispatching_when_both_templates_are_blank(): void
    {
        Bus::fake();
        $product = $this->makeProduct();

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkGenerateSeoMeta', [$product], data: [
                'title_template' => '',
                'description_template' => '',
                'overwrite_existing' => false,
            ]);

        Bus::assertNotDispatched(BulkGenerateProductSeoMeta::class);
    }
}
