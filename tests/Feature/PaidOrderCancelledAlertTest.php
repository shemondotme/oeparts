<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaidOrderCancelledNotification;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cancelling an order that already holds the customer's money must tell staff a refund is owed. */
class PaidOrderCancelledAlertTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cancelling_a_paid_order_alerts_every_active_admin(): void
    {
        Queue::fake();
        Notification::fake();
        $active = Admin::factory()->create(['is_active' => true]);
        $inactive = Admin::factory()->create(['is_active' => false]);
        $order = Order::factory()->create(['status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid]);

        app(OrderService::class)->cancelOrder($order, 'Customer changed their mind');

        Notification::assertSentTo($active, PaidOrderCancelledNotification::class, fn ($n) => $n->order->is($order));
        Notification::assertNotSentTo($inactive, PaidOrderCancelledNotification::class);
    }

    #[Test]
    public function cancelling_an_order_with_a_held_card_authorization_also_alerts(): void
    {
        Queue::fake();
        Notification::fake();
        $admin = Admin::factory()->create(['is_active' => true]);
        $order = Order::factory()->create(['status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Pending]);
        Payment::factory()->create([
            'order_id' => $order->id, 'gateway' => PaymentGateway::Airwallex, 'status' => PaymentTransactionStatus::Authorized,
        ]);

        app(OrderService::class)->cancelOrder($order);

        Notification::assertSentTo($admin, PaidOrderCancelledNotification::class);
    }

    #[Test]
    public function cancelling_an_unpaid_order_does_not_alert(): void
    {
        Queue::fake();
        Notification::fake();
        Admin::factory()->create(['is_active' => true]);
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending]);

        app(OrderService::class)->cancelOrder($order);

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_customer_cancelling_their_paid_order_from_the_account_alerts_staff(): void
    {
        Queue::fake();
        Notification::fake();
        $admin = Admin::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id, 'status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.cancel', ['lang' => 'en', 'order' => $order]))
            ->assertRedirect();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        Notification::assertSentTo($admin, PaidOrderCancelledNotification::class);
    }

    #[Test]
    public function the_alert_renders_with_the_amount_and_next_step(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Paid]);

        $html = (string) (new PaidOrderCancelledNotification($order))->toMail($admin)->render();

        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('refund', strtolower($html));
    }
}
