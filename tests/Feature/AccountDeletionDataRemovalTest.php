<?php

namespace Tests\Feature;

use App\Enums\LogStatus;
use App\Models\AbandonedCart;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\ContactMessage;
use App\Models\FailedSearchLog;
use App\Models\LoginLog;
use App\Models\Manufacturer;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\PartInquiry;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\SearchLog;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 11 (Compliance/Legal). AccountController::destroy() only ever
 * touched the users row (anonymize + soft-delete) — every other table with
 * a user_id foreign key was left completely alone, because their own
 * cascadeOnDelete()/nullOnDelete() migration declarations never actually
 * fire: User uses SoftDeletes, so $user->delete() is an UPDATE
 * (deleted_at), not a real SQL DELETE, and a DB-level FK constraint only
 * cascades on an actual DELETE. This meant a "deleted" account's saved
 * addresses, cart, search history, and login history (with the real
 * original email/IP) all survived indefinitely, fully intact. This spec
 * pins the fix, table by table, rather than trusting the migrations'
 * declared-but-inert cascade behavior.
 */
class AccountDeletionDataRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $manufacturer = Manufacturer::create([
            'name' => json_encode(['en' => 'Test Mfr']),
            'slug' => 'test-mfr-'.uniqid(),
            'country_code' => 'DE',
        ]);
        $condition = Condition::first() ?? Condition::create([
            'name' => 'New', 'slug' => 'new', 'bg_color' => '#fff', 'text_color' => '#000',
        ]);

        return Product::factory()->create([
            'manufacturer_id' => $manufacturer->id,
            'condition_id' => $condition->id,
        ]);
    }

    private function deleteAccount(User $user, string $password = 'Password123!'): TestResponse
    {
        $this->actingAs($user, 'web');

        return $this->delete('/en/account', ['current_password' => $password]);
    }

    #[Test]
    public function deleting_the_account_removes_the_saved_address_book(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        UserAddress::create([
            'user_id' => $user->id, 'label' => 'Home', 'first_name' => 'Jane', 'last_name' => 'Doe',
            'address_line1' => '1 Test St', 'city' => 'Berlin', 'postal_code' => '10115',
            'country_code' => 'DE', 'is_default' => true,
        ]);

        $this->deleteAccount($user);

        $this->assertDatabaseCount('user_addresses', 0);
    }

    #[Test]
    public function deleting_the_account_removes_its_cart_and_cart_items(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $product = $this->makeProduct();
        $cart = Cart::create(['user_id' => $user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price_at_add' => '10.00']);

        $this->deleteAccount($user);

        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    #[Test]
    public function deleting_the_account_unlinks_search_history_but_keeps_it_for_aggregate_analytics(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $log = SearchLog::factory()->create(['user_id' => $user->id, 'search_query' => 'brake pad']);
        $failedLog = FailedSearchLog::factory()->create(['user_id' => $user->id, 'search_query' => 'no such part']);

        $this->deleteAccount($user);

        $this->assertNull($log->fresh()->user_id);
        $this->assertSame('brake pad', $log->fresh()->search_query);
        $this->assertNull($failedLog->fresh()->user_id);
        $this->assertSame('no such part', $failedLog->fresh()->search_query);
    }

    #[Test]
    public function deleting_the_account_removes_its_abandoned_cart_recovery_record(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        AbandonedCart::create([
            'user_id' => $user->id,
            'guest_email' => $user->email,
            'cart_snapshot' => ['items' => []],
            'last_active_at' => now(),
            'recovery_email_sent' => false,
        ]);

        $this->deleteAccount($user);

        $this->assertDatabaseCount('abandoned_carts', 0);
    }

    #[Test]
    public function deleting_the_account_anonymizes_its_login_history_instead_of_leaking_the_real_email(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!'), 'email' => 'real-person@example.com']);
        $log = LoginLog::create([
            'user_id' => $user->id, 'user_type' => 'customer', 'email' => 'real-person@example.com',
            'status' => 'success', 'ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0 Real Browser',
        ]);

        $this->deleteAccount($user);

        $log->refresh();
        $this->assertNotSame('real-person@example.com', $log->email);
        $this->assertStringContainsString('deleted_', $log->email);
        $this->assertNotSame('203.0.113.7', $log->ip_address);
        $this->assertNull($log->user_agent);
        // The success/failure signal itself is retained — it's a security
        // audit trail, not identifying on its own once email/IP are scrubbed.
        $this->assertSame(LogStatus::Success, $log->status);
    }

    #[Test]
    public function deleting_the_account_does_not_touch_financial_records_needed_for_tax_and_audit_retention(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $this->deleteAccount($user);

        $this->assertSame($user->id, $order->fresh()->user_id);
        $this->assertSame($user->id, $refund->fresh()->user_id);
    }

    #[Test]
    public function deleting_the_account_does_not_touch_standalone_email_matched_business_records(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!'), 'email' => 'standalone@example.com']);
        ContactMessage::factory()->create(['email' => 'standalone@example.com']);
        PartInquiry::factory()->create(['email' => 'standalone@example.com']);
        NewsletterSubscriber::factory()->create(['email' => 'standalone@example.com']);

        $this->deleteAccount($user);

        $this->assertDatabaseHas('contact_messages', ['email' => 'standalone@example.com']);
        $this->assertDatabaseHas('part_inquiries', ['email' => 'standalone@example.com']);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'standalone@example.com']);
    }

    #[Test]
    public function wrong_current_password_deletes_nothing(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        UserAddress::create([
            'user_id' => $user->id, 'label' => 'Home', 'first_name' => 'Jane', 'last_name' => 'Doe',
            'address_line1' => '1 Test St', 'city' => 'Berlin', 'postal_code' => '10115',
            'country_code' => 'DE', 'is_default' => true,
        ]);

        $response = $this->deleteAccount($user, 'WrongPassword!');

        $response->assertSessionHasErrors(['current_password']);
        $this->assertDatabaseCount('user_addresses', 1);
        $this->assertNotNull($user->fresh());
        $this->assertNull($user->fresh()->deleted_at);
    }
}
