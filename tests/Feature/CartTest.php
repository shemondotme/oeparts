<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Manufacturer;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private Product $product1;
    private Product $product2;
    private User $user;
    private Manufacturer $manufacturer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create manufacturer
        $this->manufacturer = Manufacturer::create([
            'name' => 'Test Manufacturer',
            'slug' => 'test-manufacturer',
            'country_code' => 'DE',
            'is_active' => true,
        ]);

        // Create test products
        $this->product1 = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => 'Test Product 1',
            'description' => 'Test description',
            'price' => 100.00,
            'condition' => 'new',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036M',
            'normalized_oem' => '06L906036M',
            'name' => 'Test Product 2',
            'description' => 'Test description',
            'price' => 200.00,
            'condition' => 'new',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    #[Test]
    public function guest_can_add_item_to_cart(): void
    {
        $response = $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'cart.item_added',
            ])
            ->assertJsonPath('cart_summary.item_count', 2);

        // Verify cart was created
        $this->assertDatabaseHas('carts', [
            'user_id' => null,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product1->id,
            'quantity' => 2,
        ]);
    }

    #[Test]
    public function authenticated_user_can_add_item_to_cart(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function cannot_add_item_with_insufficient_stock(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => 'TEST123',
            'normalized_oem' => 'TEST123',
            'name' => 'Out of Stock Product',
            'description' => 'Test description',
            'price' => 50.00,
            'condition' => 'new',
            'is_in_stock' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson('/en/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Product is out of stock.',
            ]);
    }

    #[Test]
    public function can_update_item_quantity(): void
    {
        $this->actingAs($this->user);

        // First add item
        $response = $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(200);
        $itemId = $response->json('item.id');

        // Update quantity
        $response = $this->putJson("/en/cart/update/{$itemId}", [
            'quantity' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('cart_summary.item_count', 3);

        $this->assertDatabaseHas('cart_items', [
            'id' => $itemId,
            'quantity' => 3,
        ]);
    }

    #[Test]
    public function can_remove_item_from_cart(): void
    {
        $this->actingAs($this->user);

        // Add item
        $response = $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(200);
        $itemId = $response->json('item.id');

        // Remove item
        $response = $this->deleteJson("/en/cart/remove/{$itemId}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('cart_summary.item_count', 0);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $itemId,
        ]);
    }

    #[Test]
    public function guest_cart_merges_with_user_cart_after_login(): void
    {
        // Add item as guest
        $response = $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2,
        ]);

        $guestCart = Cart::first();
        $guestToken = $guestCart->guest_token;

        // Login and merge
        $this->actingAs($this->user);

        $response = $this->postJson('/en/cart/merge', [
            'guest_token' => $guestToken,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('cart_summary.item_count', 2);

        // Verify guest cart is deleted
        $this->assertDatabaseMissing('carts', [
            'guest_token' => $guestToken,
        ]);

        // Verify user cart has the item
        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product1->id,
            'quantity' => 2,
        ]);
    }

    #[Test]
    public function price_change_detection_works(): void
    {
        $this->actingAs($this->user);

        // Add item with initial price
        $response = $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);

        // Update product price (increase by 30%)
        $this->product1->update(['price' => 130.00]);

        // Get cart summary to check price changes
        $response = $this->getJson('/en/cart/summary');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json();
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('price_changes', $data['summary']);
        
        // Price change should be detected (30% > 20% threshold)
        $priceChanges = $data['summary']['price_changes'];
        $this->assertNotEmpty($priceChanges);
        $this->assertEquals(30, round($priceChanges[0]['change_percent']));
    }

    #[Test]
    public function cart_nudge_shows_free_shipping_progress(): void
    {
        $this->actingAs($this->user);

        // Set free shipping threshold
        \App\Models\Setting::create([
            'group' => 'shipping',
            'key' => 'free_threshold',
            'value' => '500',
            'type' => 'string',
        ]);

        app(SettingsService::class)->forget('shipping');

        // Add item with price 100
        $response = $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/en/cart/summary');

        $response->assertStatus(200)
            ->assertJsonPath('summary.shipping_needed', 400)
            ->assertJsonPath('summary.free_shipping_threshold', 500);
    }

    #[Test]
    public function cart_page_loads_successfully(): void
    {
        $response = $this->get('/en/cart');

        $response->assertStatus(200)
            ->assertViewIs('frontend.cart.index');
    }

    #[Test]
    public function empty_cart_shows_empty_state(): void
    {
        $response = $this->get('/en/cart');

        $response->assertStatus(200)
            ->assertSee(__('cart.empty_title'));
    }

    #[Test]
    public function cart_with_items_shows_correct_totals(): void
    {
        $this->actingAs($this->user);

        // Add two items
        $this->postJson('/en/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2,
        ]);

        $this->postJson('/en/cart/add', [
            'product_id' => $this->product2->id,
            'quantity' => 1,
        ]);

        $response = $this->get('/en/cart');

        $response->assertStatus(200)
            ->assertViewHas('summary', fn ($s) => $s['item_count'] === 3 && $s['subtotal'] == 400.0)
            ->assertSee('cart.items');
    }
}