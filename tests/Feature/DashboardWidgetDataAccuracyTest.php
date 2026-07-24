<?php

namespace Tests\Feature;

use App\Filament\Resources\PartInquiryResource\Pages\CreatePartInquiry;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Widgets\AbandonedCartWidget;
use App\Filament\Widgets\RefundsPendingList;
use App\Jobs\SendAbandonedCartEmail;
use App\Models\AbandonedCart;
use App\Models\Admin;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\CartRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for §5q widget-truth findings: the fake "Send Reminder"
 * (touched a nonexistent column, sent nothing), the refund widget's
 * grand_total-as-exposure, blank "prefill" links, and the abandoned-cart
 * pipeline command that fataled on every scheduled run.
 */
class DashboardWidgetDataAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    private function abandonedCartFor(?User $user, array $overrides = []): AbandonedCart
    {
        return AbandonedCart::create(array_merge([
            'user_id' => $user?->id,
            'guest_email' => null,
            'cart_snapshot' => [
                'items' => [['product_id' => 1, 'quantity' => 2, 'price_at_add' => '10.00', 'total_price' => '20.00']],
                'total' => '20.00',
                'customer_name' => $user?->name,
            ],
            'last_active_at' => now()->subHours(5),
            'recovery_email_sent' => false,
        ], $overrides));
    }

    // ── CartRecoveryService ──────────────────────────────────────────────

    #[Test]
    public function recovery_service_queues_the_email_and_flags_the_record(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $record = $this->abandonedCartFor($user);

        $this->assertTrue(app(CartRecoveryService::class)->send($record));
        $this->assertTrue($record->refresh()->recovery_email_sent);

        Queue::assertPushed(SendAbandonedCartEmail::class, fn (SendAbandonedCartEmail $job) => $job->email === $user->email);
    }

    #[Test]
    public function recovery_service_refuses_records_with_no_email(): void
    {
        Queue::fake();

        $record = $this->abandonedCartFor(null);

        $this->assertFalse(app(CartRecoveryService::class)->send($record));
        $this->assertFalse($record->refresh()->recovery_email_sent);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function widget_send_recovery_action_uses_the_real_recovery_path(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $record = $this->abandonedCartFor($user);

        Livewire::test(AbandonedCartWidget::class)
            ->callTableAction('send_recovery', $record);

        $this->assertTrue($record->refresh()->recovery_email_sent);
        Queue::assertPushed(SendAbandonedCartEmail::class);
    }

    // ── abandoned-cart:process command ───────────────────────────────────

    #[Test]
    public function process_command_snapshots_stale_user_carts_and_queues_recovery(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $product = \App\Models\Product::factory()->create();

        $cartId = DB::table('carts')->insertGetId([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(5),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(1),
        ]);
        DB::table('cart_items')->insert([
            'cart_id' => $cartId,
            'product_id' => $product->id,
            'quantity' => 2,
            'price_at_add' => '15.50',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        // Guest cart (no user): must be skipped — no address to recover to.
        $guestCartId = DB::table('carts')->insertGetId([
            'user_id' => null,
            'guest_token' => 'guest-token-1',
            'expires_at' => now()->addDays(5),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(1),
        ]);
        DB::table('cart_items')->insert([
            'cart_id' => $guestCartId,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_at_add' => '9.99',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        $this->artisan('abandoned-cart:process')->assertSuccessful();

        $record = AbandonedCart::where('user_id', $user->id)->first();
        $this->assertNotNull($record);
        $this->assertTrue($record->recovery_email_sent);
        $this->assertSame('31.00', $record->cart_snapshot['total']);
        $this->assertSame(1, AbandonedCart::count(), 'guest cart must not be snapshotted');

        Queue::assertPushed(SendAbandonedCartEmail::class, 1);

        // Second run within the 7-day window: no duplicate recovery.
        $this->artisan('abandoned-cart:process')->assertSuccessful();
        $this->assertSame(1, AbandonedCart::count());
        Queue::assertPushed(SendAbandonedCartEmail::class, 1);
    }

    // ── RefundsPendingList ───────────────────────────────────────────────

    #[Test]
    public function refunds_widget_lists_pending_requests_with_the_requested_amount(): void
    {
        $pending = RefundRequest::factory()->create([
            'status' => \App\Enums\RefundStatus::Pending,
            'amount_requested' => '42.50',
        ]);
        $processed = RefundRequest::factory()->create([
            'status' => \App\Enums\RefundStatus::Processed,
            'amount_requested' => '99.99',
        ]);

        Livewire::test(RefundsPendingList::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$processed])
            ->assertSee('42.50');
    }

    // ── FillsFromQuery prefill ───────────────────────────────────────────

    #[Test]
    public function create_product_prefills_the_oem_number_from_the_query_string(): void
    {
        Livewire::withQueryParams(['data' => ['oem_number' => 'A2038202685']])
            ->test(CreateProduct::class)
            ->assertSet('data.oem_number', 'A2038202685');
    }

    #[Test]
    public function create_part_inquiry_prefills_the_oem_number_from_the_query_string(): void
    {
        Livewire::withQueryParams(['data' => ['oem_number' => '11427566327']])
            ->test(CreatePartInquiry::class)
            ->assertSet('data.oem_number', '11427566327');
    }

    #[Test]
    public function prefill_ignores_fields_outside_the_whitelist(): void
    {
        Livewire::withQueryParams(['data' => ['price' => '0.01', 'oem_number' => 'X1']])
            ->test(CreateProduct::class)
            ->assertSet('data.oem_number', 'X1')
            ->assertNotSet('data.price', '0.01');
    }
}
