<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * markPaymentFailed() is not wired into any controller/webhook, but had an
 * implicitly-nullable parameter type (deprecated as of PHP 8.4).
 * (markPaymentReceived() was removed: it moved an order to Paid without
 * sending the confirmation, and every real payment path goes through
 * PaymentService, which also assigns the invoice number.)
 */
class OrderServicePaymentTest extends TestCase
{
    use RefreshDatabase;

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
