<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bulletproof-testing backlog item 5 (guest cart API CSRF gap), revisited.
 * /api/cart/* and /api/checkout/* sit in the stateless `api` group (no CSRF
 * token) and identify an unauthenticated visitor purely via a plain,
 * automatically-sent guest_token cookie. SameSite=Lax already blocks the
 * practical cross-site POST/fetch vector for this cookie in modern
 * browsers (confirmed, Phase 1) — VerifySameOriginForStatefulCookies adds
 * an independent second layer: reject a state-changing request whose
 * Origin/Referer is PRESENT but doesn't match this request's own host,
 * never reject for an ABSENT header (so a genuine mobile app, which
 * typically sends neither, is unaffected).
 */
class ApiCartCsrfOriginTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create([
            'name' => 'Test Manufacturer', 'slug' => 'origin-test-mfr',
            'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'ORIGIN001', 'normalized_oem' => 'ORIGIN001',
            'name' => 'Test Product', 'description' => 'Test description',
            'price' => 100.00, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
    }

    #[Test]
    public function a_cross_origin_add_to_cart_request_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Origin' => 'https://evil.com'])
            ->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $this->product->id]);
    }

    #[Test]
    public function a_cross_origin_request_identified_only_by_referer_is_also_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Referer' => 'https://evil.com/attack-page'])
            ->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response->assertStatus(403);
    }

    #[Test]
    public function a_same_origin_add_to_cart_request_succeeds(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Origin' => 'http://localhost'])
            ->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response->assertOk();
        $this->assertDatabaseHas('cart_items', ['product_id' => $this->product->id]);
    }

    /**
     * The whole point: a genuine native mobile HTTP client typically sends
     * neither Origin nor Referer at all — this must NOT be treated as
     * suspicious, or the fix would break real mobile app checkout.
     */
    #[Test]
    public function a_request_with_no_origin_or_referer_header_succeeds_mobile_app_compatibility(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response->assertOk();
        $this->assertDatabaseHas('cart_items', ['product_id' => $this->product->id]);
    }

    #[Test]
    public function the_checkout_api_is_also_protected(): void
    {
        $this->actingAs($this->user)->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['Origin' => 'https://evil.com'])
            ->postJson('/api/checkout/start', []);

        $response->assertStatus(403);
    }

    #[Test]
    public function a_cross_origin_get_request_is_also_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['Origin' => 'https://evil.com'])
            ->getJson('/api/cart/summary');

        $response->assertStatus(403);
    }
}
