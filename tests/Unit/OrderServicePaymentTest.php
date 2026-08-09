<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * markPaymentReceived()/markPaymentFailed() are not wired into any
 * controller/webhook yet, but had 3 latent bugs found in the checkout-order
 * audit: lockForUpdate() called outside any DB::transaction() (a no-op —
 * MySQL only honors a row lock inside an open transaction), a two-way
 * card/bank_transfer ternary that silently mapped 'paysera' to
 * BankTransfer, and an implicitly-nullable parameter type (deprecated as of
 * PHP 8.4).
 */
class OrderServicePaymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mark_payment_received_transitions_a_pending_order_to_paid(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending]);

        app(OrderService::class)->markPaymentReceived($order, 'REF-12345', 'card');

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('REF-12345', $order->payment_reference);
    }

    #[Test]
    public function mark_payment_received_maps_paysera_to_the_paysera_payment_method(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        app(OrderService::class)->markPaymentReceived($order, 'REF-PAYSERA', 'paysera');

        $this->assertSame(PaymentMethod::Paysera, $order->refresh()->payment_method);
    }

    #[Test]
    public function mark_payment_failed_accepts_a_null_reference(): void
    {
        $order = Order::factory()->create(['payment_reference' => 'OLD-REF']);

        app(OrderService::class)->markPaymentFailed($order, null);

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame('OLD-REF', $order->payment_reference);
    }
}
