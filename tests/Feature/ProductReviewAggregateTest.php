<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Services\ProductSlugService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 9 (Business/Domain Edge Cases) — review moderation flow and rating
 * aggregate correctness. SearchController::detail() computes both the
 * average rating and review count via withAvg()/withCount() scoped to
 * status='approved' (not a cached column), so a pending or rejected
 * review must never skew either figure shown on the real product page —
 * this pins that end-to-end, through the actual HTTP route, rather than
 * just the query in isolation.
 */
class ProductReviewAggregateTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Raw Eloquent write + SettingsService::forget() — a raw write never
        // busts SettingsService::getGroup()'s own cache the way ::set() does
        // (see ProductDetailPageTest::enableDetailPages(), the established
        // pattern for this exact setting in this test suite).
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'detail_pages_enabled'],
            ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('seo');

        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create([
            'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Test Manufacturer'),
            'slug' => 'test-manufacturer', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'REVAGG001', 'normalized_oem' => 'REVAGG001',
            'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Review Aggregate Test Product'),
            'description' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Test description'),
            'price' => 50.00, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);
    }

    private function detailUrl(): string
    {
        $idSlug = app(ProductSlugService::class)->buildIdSlug($this->product, 'en');

        return "/en/parts/{$this->product->oem_number}/{$idSlug}";
    }

    private function makeReview(string $name, int $rating, string $status): Review
    {
        return Review::create([
            'product_id' => $this->product->id,
            'reviewer_name' => $name,
            'comment' => 'Test review comment.',
            'rating' => $rating,
            'status' => $status,
        ]);
    }

    #[Test]
    public function pending_and_rejected_reviews_are_excluded_from_the_displayed_average_and_count(): void
    {
        // Approved: 5 and 3 -> average 4.0, count 2.
        $this->makeReview('Approved One', 5, 'approved');
        $this->makeReview('Approved Two', 3, 'approved');
        // Would drag the average down to 3.0 and inflate the count to 3/4
        // if either leaked into the aggregate.
        $this->makeReview('Still Pending', 1, 'pending');
        $this->makeReview('Got Rejected', 1, 'rejected');

        $response = $this->get($this->detailUrl());

        $response->assertOk();
        $response->assertSee('4.0', false);
    }

    #[Test]
    public function approving_a_pending_review_immediately_changes_the_displayed_average(): void
    {
        $this->makeReview('Approved One', 4, 'approved');
        $pending = $this->makeReview('Newly Approved', 2, 'pending');

        // Before approval: only the 4-star review counts.
        $this->get($this->detailUrl())->assertSee('4.0', false);

        $pending->update(['status' => 'approved']);

        // No cache to invalidate — withAvg() is computed live per request,
        // so this must reflect the new average (4+2)/2 = 3.0 immediately.
        $this->get($this->detailUrl())->assertSee('3.0', false);
    }

    #[Test]
    public function a_product_with_no_approved_reviews_shows_a_zero_average_not_an_error(): void
    {
        $this->makeReview('Still Pending', 5, 'pending');

        $response = $this->get($this->detailUrl());

        $response->assertOk();
        $response->assertSee('0.0', false);
    }
}
