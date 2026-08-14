<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Services\ProductSlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;
    private Condition $condition;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'],
            'slug' => 'bosch',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);
    }

    private function reviewStoreUrl(): string
    {
        $idSlug = app(ProductSlugService::class)->buildIdSlug($this->product, 'en');

        return "/en/parts/{$this->product->normalized_oem}/{$idSlug}/review";
    }

    private function setReviewRateLimit(int $perHour): void
    {
        Setting::updateOrCreate(
            ['group' => 'pdp', 'key' => 'review_rate_limit_per_hour'],
            ['value' => (string) $perHour, 'type' => 'integer', 'is_encrypted' => false]
        );
        app(\App\Services\SettingsService::class)->forget('pdp');
    }

    #[Test]
    public function guest_can_submit_a_pending_review(): void
    {
        $response = $this->post($this->reviewStoreUrl(), [
            'reviewer_name' => 'Jane Doe',
            'title' => 'Great fit',
            'comment' => 'Fit perfectly on my car, arrived fast.',
            'rating' => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $review = Review::where('product_id', $this->product->id)->first();
        $this->assertNotNull($review);
        $this->assertSame('Jane Doe', $review->reviewer_name);
        $this->assertSame('pending', $review->status);
        $this->assertNotNull($review->ip_address);
    }

    #[Test]
    public function review_requires_rating_between_1_and_5(): void
    {
        $response = $this->post($this->reviewStoreUrl(), [
            'reviewer_name' => 'Jane Doe',
            'comment' => 'Not great honestly.',
            'rating' => 9,
        ]);

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('product_reviews', 0);
    }

    #[Test]
    public function honeypot_field_rejects_bot_submission(): void
    {
        $response = $this->post($this->reviewStoreUrl(), [
            'reviewer_name' => 'Bot',
            'comment' => 'buy cheap watches at spam-site.com',
            'rating' => 5,
            'website' => 'https://spam-site.com',
        ]);

        $response->assertSessionHasErrors('website');
        $this->assertDatabaseCount('product_reviews', 0);
    }

    #[Test]
    public function review_submission_is_rate_limited(): void
    {
        $this->setReviewRateLimit(1);

        $this->post($this->reviewStoreUrl(), [
            'reviewer_name' => 'First',
            'comment' => 'First review.',
            'rating' => 5,
        ])->assertRedirect();

        $response = $this->post($this->reviewStoreUrl(), [
            'reviewer_name' => 'Second',
            'comment' => 'Second review, should be throttled.',
            'rating' => 4,
        ]);

        $response->assertStatus(429);
    }

    #[Test]
    public function submitting_review_when_toggle_off_returns_404(): void
    {
        Setting::updateOrCreate(
            ['group' => 'pdp', 'key' => 'show_reviews'],
            ['value' => '0', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(\App\Services\SettingsService::class)->forget('pdp');

        $response = $this->post($this->reviewStoreUrl(), [
            'reviewer_name' => 'Jane Doe',
            'comment' => 'Fit perfectly.',
            'rating' => 5,
        ]);

        $response->assertStatus(404);
    }
}
