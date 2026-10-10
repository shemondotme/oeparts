<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SettingType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaidOrderCancelledNotification;
use App\Services\PaymentService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpireUnpaidBankTransferOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function bankOrder(int $hoursOld, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
            'created_at' => now()->subHours($hoursOld),
        ], $overrides));
    }

    #[Test]
    public function an_unpaid_bank_transfer_order_past_the_limit_is_cancelled_and_its_stock_released(): void
    {
        Queue::fake();
        $order = $this->bankOrder(49);
        $payment = Payment::factory()->create([
            'order_id' => $order->id, 'gateway' => PaymentGateway::BankTransfer,
            'status' => PaymentTransactionStatus::Pending,
        ]);
        $product = Product::factory()->create(['is_in_stock' => false]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Failed, $payment->fresh()->status);
        $this->assertTrue((bool) $product->fresh()->is_in_stock);
    }

    #[Test]
    public function orders_inside_the_limit_paid_or_on_another_method_are_left_alone(): void
    {
        Queue::fake();
        $recent = $this->bankOrder(10);
        $paid = $this->bankOrder(100, ['payment_status' => PaymentStatus::Paid]);
        $processing = $this->bankOrder(100, ['status' => OrderStatus::Processing]);
        // A card order is on the (shorter) online limit, not the bank one.
        $card = $this->bankOrder(10, ['payment_method' => PaymentMethod::Card]);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $recent->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $paid->fresh()->status);
        $this->assertSame(OrderStatus::Processing, $processing->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $card->fresh()->status);
    }

    #[Test]
    public function the_limit_comes_from_the_orders_policy_setting(): void
    {
        Queue::fake();
        Setting::updateOrCreate(
            ['group' => 'orders', 'key' => 'bank_transfer_expiry_hours'],
            ['value' => '12', 'type' => SettingType::String],
        );
        Cache::flush();
        $order = $this->bankOrder(13);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    #[Test]
    public function the_sweep_is_scheduled_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'oeparts:orders:expire-unpaid'));

        $this->assertNotNull($event, 'oeparts:orders:expire-unpaid is not scheduled');
        $this->assertSame('0 * * * *', $event->expression);
    }

    #[Test]
    public function an_abandoned_card_order_expires_after_the_online_limit_but_not_inside_it(): void
    {
        Queue::fake();
        $abandoned = $this->bankOrder(25, ['payment_method' => PaymentMethod::Card]);
        $paysera = $this->bankOrder(30, ['payment_method' => PaymentMethod::Paysera]);
        $recent = $this->bankOrder(5, ['payment_method' => PaymentMethod::Card]);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $abandoned->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $paysera->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $recent->fresh()->status);
    }

    #[Test]
    public function a_card_order_with_a_held_or_captured_payment_is_never_expired(): void
    {
        Queue::fake();
        $held = $this->bankOrder(100, ['payment_method' => PaymentMethod::Card]);
        Payment::factory()->create(['order_id' => $held->id, 'gateway' => PaymentGateway::Airwallex, 'status' => PaymentTransactionStatus::Authorized]);
        $captured = $this->bankOrder(100, ['payment_method' => PaymentMethod::Card]);
        Payment::factory()->create(['order_id' => $captured->id, 'gateway' => PaymentGateway::Airwallex, 'status' => PaymentTransactionStatus::Captured]);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $held->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $captured->fresh()->status);
    }

    #[Test]
    public function the_online_limit_can_be_switched_off_with_zero(): void
    {
        Queue::fake();
        Setting::updateOrCreate(
            ['group' => 'orders', 'key' => 'online_payment_expiry_hours'],
            ['value' => '0', 'type' => SettingType::String],
        );
        Cache::flush();
        $card = $this->bankOrder(500, ['payment_method' => PaymentMethod::Card]);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $card->fresh()->status);
    }

    #[Test]
    public function a_payment_that_still_arrives_for_an_expired_order_is_refunded_not_invoiced(): void
    {
        Queue::fake();
        Notification::fake();
        $admin = Admin::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        $order = $this->bankOrder(30, ['payment_method' => PaymentMethod::Card, 'user_id' => $user->id, 'invoice_number' => null]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway' => PaymentGateway::Airwallex, 'transaction_id' => 'pi_late_1']);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);

        app(PaymentService::class)->processSuccessfulPayment([
            'id' => 'evt_late_1', 'data' => ['object' => ['id' => 'pi_late_1']],
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNull($order->invoice_number, 'a cancelled order must not be invoiced');
        $this->assertSame(1, RefundRequest::where('order_id', $order->id)->count());
        Notification::assertSentTo($admin, PaidOrderCancelledNotification::class);
    }
}
