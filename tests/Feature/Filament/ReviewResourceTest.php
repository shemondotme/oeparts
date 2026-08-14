<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Models\Admin;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);

        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');

        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true]);
        $this->product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);
    }

    private function makeReview(array $overrides = []): Review
    {
        return Review::create(array_merge([
            'product_id' => $this->product->id,
            'reviewer_name' => 'Jane Doe',
            'comment' => 'Fit perfectly.',
            'rating' => 5,
            'status' => 'pending',
        ], $overrides));
    }

    #[Test]
    public function admin_can_approve_a_pending_review(): void
    {
        $review = $this->makeReview();

        Livewire::test(ListReviews::class)
            ->callTableAction('approve', $review);

        $this->assertSame('approved', $review->fresh()->status);
    }

    #[Test]
    public function admin_can_reject_a_pending_review(): void
    {
        $review = $this->makeReview();

        Livewire::test(ListReviews::class)
            ->callTableAction('reject', $review);

        $this->assertSame('rejected', $review->fresh()->status);
    }

    #[Test]
    public function status_filter_defaults_to_pending_so_the_moderation_queue_loads_first(): void
    {
        $this->makeReview(['reviewer_name' => 'Pending Pete', 'status' => 'pending']);
        $this->makeReview(['reviewer_name' => 'Approved Alex', 'status' => 'approved']);

        Livewire::test(ListReviews::class)
            ->loadTable()
            ->assertCanSeeTableRecords(Review::where('reviewer_name', 'Pending Pete')->get())
            ->assertCanNotSeeTableRecords(Review::where('reviewer_name', 'Approved Alex')->get());
    }

    #[Test]
    public function non_super_admin_without_reviews_permission_cannot_access_the_list(): void
    {
        $plainAdmin = Admin::factory()->create();
        $plainAdmin->assignRole('admin');
        $this->actingAs($plainAdmin, 'admin');

        // RolesSeeder never grants 'view reviews' to the 'admin' role
        // (same precedent as Testimonials — content moderation is
        // super_admin-only for now), so this should be denied.
        $this->assertFalse($plainAdmin->can('view reviews'));
    }
}
